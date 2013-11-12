<?php

/**
 * Hook handler
 *
 * @note lots of methods in this class are public, despite not really
 * being "public" in order that the XMLParser can call them
 */
class CommonsMetadata {
	/**
	 * Nonstandard license name patterns used in categories/templates/shortnames
	 */
	static $licenseAliases = array(
		'cc-by-sa-3.0-migrated' => 'cc-by-sa-3.0',
		'cc-by-sa-3.0-migrated-with-disclaimers' => 'cc-by-sa-3.0',
		'cc-by-sa-3.0-2.5-2.0-1.0' => 'cc-by-sa-3.0',
		'cc-by-sa-2.5-2.0-1.0' => 'cc-by-sa-2.5',
		'cc-by-2.0-stma' => 'cc-by-2.0',
		'cc-by-sa-1.0+' => 'cc-by-sa-3.0',
	);

	/**
	 * Mapping of category names to assesment levels. Array keys are regexps which will be
	 * matched case-insensitively against category names; the first match is returned.
	 * @var array
	 */
	protected static $assessmentCategories = array(
		'poty' => '/^pictures of the year \(.*\)/',
		'potd' => '/^pictures of the day \(.*\)/',
		'featured' => '/^featured (pictures|sounds) on wikimedia commons/',
		'quality' => '/^quality images/',
		'valued' => '/^valued images/',
	);

	/**
	 * @param $doc String The html to parse
	 * @param String|boolean $lang Language code or false for all langs.
	 * @return Array The properties extracted from the page.
	 */
	public static function getMetadata( $doc, $lang = false ) {
		$obj = new CommonsMetadata_InformationParser();
		$obj->setLanguage( $lang );
		return $obj->parsePage( $doc );
	}

	/**
	 * Hook handler for extended metadata
	 *
	 * @param $combinedMeta Array Metadata so far
	 * @param $file File The file object in question
	 * @param $context IContextSource context. Used to select language
	 * @param $singleLang Boolean Get only target language, or all translations
	 * @param &$maxCache Integer How many seconds to cache the result
	 * @return bool this hook handler always returns true.
	 */
	public static function onGetExtendedMetadata( &$combinedMeta, File $file, IContextSource $context, $singleLang, &$maxCache ) {
		$lang = $context->getLanguage();

		if ( isset( $combinedMeta['CommonsMedadataExtension'] ) ) {
			// this is a file from a remote repo, and CommonsMedadata is installed on
			// the remote as well. We assume data collected at the repo is superior
			// to what we could collect here, so don't do anything.
			return true;
		} else {
			$combinedMeta['CommonsMedadataExtension'] = array(
				'value' => 1,
				'source' => 'extension',
			);
		}

		# Note: If this is a local file, there is no caching here.
		# However, the results of this module have longer caching for local
		# files to help compensate. For foreign files, this method is cached
		# via parser cache, and possibly a second cache depending on
		# descriptionCacheExpiry (disabled on Wikimedia).
		$descriptionText = $file->getDescriptionText( $lang );

		if ( $singleLang ) {
			$data = self::getMetadata( $descriptionText, $lang->getCode() );
		} else {
			$data = self::getMetadata( $descriptionText );
		}

		$categories = array();
		// getting categories for a ForeignAPIFile is not supported for now,
		// but in that case the extended metadata should be available via
		// $file->getExtendedMetadata, assuming CommonsMetadata is installed
		// on the remote repository.
		if ( $file instanceof LocalFile ) {
			$categories = self::getCategories( $file );
		}

		$assessments = self::getAssessmentsAndRemoveFromCategories( $categories );
		$licenses = self::getLicensesAndRemoveFromCategories( $categories );
		if ( !$licenses && isset( $data['LicenseShortName'] ) ) {
			$license = self::filterShortnamesAndGetLicense( $data['LicenseShortName'] );
			$licenses = array( $license );
		}

		$combinedMeta['Categories'] = array(
			'value' => implode( '|', $categories ),
			'source' => 'commons-categories',
		);

		$combinedMeta['Assessments'] = array(
			'value' => implode('|', $assessments),
			'source' => 'commons-categories',
		);

		if ( $licenses ) {
			$combinedMeta['License'] = array(
				'value' => $licenses[0],
				'source' => 'commons-templates',
			);
		}

		foreach( $data as $name => $value ) {
			if ( in_array( $name, CommonsMetadata_InformationParser::$multivaluedProperties ) ) {
				// the GetExtendedMetadata hook expects the property value to be a string,
				// so we have to throw away all values but one here.
				$value = end( $value );
			}
			$combinedMeta[ $name ] = array(
				'value' => $value,
				'source' => 'commons-desc-page'
			);
		}

		if ( !$file->isLocal() ) {
			// Foreign files don't have explicit cache purging
			// In theory, if this became an issue, we could do
			// a db query to the foreign wiki to look at page_touched.
			$maxCache = 60 * 60 * 12;
		}

		return true;
	}

	/**
	 * Hook to check if cache is stale
	 *
	 * @param $timestamp String Timestamp of when cache taken
	 * @param $file File The file metadata is for
	 * @return boolean Is metadata still valid
	 */
	public static function onValidateExtendedMetadataCache( $timestamp, $file ) {
		if ( $file->isLocal() ) {
			$lastTouched = $file->getTitle()->getTouched();
			if ( $lastTouched !== false ) {
				$lastTouchedUnix = wfTimestamp( TS_UNIX, $file->getTitle()->getTouched() );
				$tsUnix = wfTimestamp( TS_UNIX, $timestamp );
				if ( $lastTouchedUnix > $tsUnix ) {
					// Page has been edited. Cache invalid.
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Hook to add unit tests
	 * @param array $files
	 * @return bool
	 */
	public static function onUnitTestsList( &$files ) {
		$testDir = __DIR__ . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'phpunit';
		$files = array_merge( $files, glob( $testDir . DIRECTORY_SEPARATOR . '*Test.php' ) );
		return true;
	}

	/**
	 * @param LocalFile $file
	 * @return array list of category names in human-readable format
	 */
	protected static function getCategories( LocalFile $file ) {
		$page = new WikiFilePage( $file->getOriginalTitle() );
		$page->setFile( $file );

		$categoryTitles = $page->getForeignCategories();

		$categories = array();
		foreach ( $categoryTitles as $title ) {
			$categories[] = $title->getText();
		}
		return $categories;
	}

	/**
	 * Matches category names to a category => license mapping, removes the matching categories
	 * and returns the corresponding licenses.
	 * @param array $categories a list of human-readable category names.
	 * @return array
	 */
	protected static function getLicensesAndRemoveFromCategories( &$categories ) {
		$licenses = array();
		foreach ( $categories as $i => $category ) {
			$licenseData = self::parseLicenseString( $category );
			if ( $licenseData ) {
				$licenses[] = $licenseData['name'];
				unset( $categories[$i] );
			}
		}
		$categories = array_merge( $categories ); // renumber to avoid holes in array
		return $licenses;
	}

	/**
	 * Matches category names to a category => assessment mapping, removes the matching categories
	 * and returns the corresponding assessments (valued image, picture of the day etc).
	 * @param array $categories a list of human-readable category names.
	 * @return array
	 */
	protected static function getAssessmentsAndRemoveFromCategories( &$categories ) {
		$assessments = array();
		foreach ( $categories as $i => $category ) {

			foreach ( self::$assessmentCategories as $assessmentType => $regexp ) {
				if ( preg_match( $regexp . 'i', $category ) ) {
					$assessments[] = $assessmentType;
					unset( $categories[$i] );
				}
			}
		}
		$categories = array_merge( $categories ); // renumber to avoid holes in array
		return array_unique($assessments); // potd/poty can happen multiple times
	}

	/**
	 * Tries to identify the license based on its short name.
	 * Will also rename all names but one from $shortName - this is done because the
	 * GetExtendedMetadata hook expects the property value to be a string, so only one name
	 * will be used anyway, and we want that one to match the license type.
	 * @param array $shortNames
	 * @return string|null one of the values from self::$licenses, or null if not recognized
	 * @see https://commons.wikimedia.org/wiki/Commons:Machine-readable_data#Machine_readable_data_set_by_license_templates
	 */
	protected static function filterShortnamesAndGetLicense( &$shortNames ) {
		foreach ( $shortNames as $name ) {
			$licenseData = self::parseLicenseString( $name );
			if ( $licenseData ) {
				$shortNames = array( $name );
				return $licenseData['name'];
			}
		}
		return null;
	}

	/**
	 * @param String|boolean $lang Language code or false for all langs.
	 *
	 * @throws MWException on invalid langcode
	 */
	public static function validateLanguage( $lang ) {
		if ( !Language::isValidCode( $lang ) ) {
			// Maybe do more strict isValidBuiltinCode?
			throw new MWException( 'Invalid language code specified' );
		}
	}

	/**
	 * Takes a license string (could be a category name, template name etc)
	 * and returns template information (or null if the license was not recognized).
	 * Only handles CC licenses for now.
	 * The returned array will have the following keys:
	 * - family: e.g. cc, gfdl
	 * - type: e.g. cc-by-sa
	 * - version: e.g. 2.5
	 * - region: e.g. nl
	 * - name: all the above put together, e.g. cc-by-sa-2.5-nl
	 * @param string $str
	 * @return array|null
	 */
	public static function parseLicenseString( $str ) {
		$data = array(
			'family' => 'cc',
			'type' => null,
			'version' => null,
			'region' => null,
			'name' => null,
		);

		$str = strtolower( trim( $str ) );
		if ( isset( self::$licenseAliases[$str] ) ) {
			$str = self::$licenseAliases[$str];
		}

		// some special cases first
		if ( in_array( $str, array( 'cc0', 'cc-pd' ), true ) ) {
			$data['type'] = $data['name'] = $str;
			return $data;
		}

		$parts = explode( '-', $str );
		if ( $parts[0] != 'cc' ) {
			return null;
		}

		for ( $i = 1; isset( $parts[$i] ) && in_array( $parts[$i], array( 'by', 'sa', 'nc', 'nd' ) ); $i++ ) {
			if ( in_array( $parts[$i], array( 'nc', 'nd' ) ) ) {
				// ignore non-free licenses
				return null;
			}
		}
		$data['type'] = implode( '-', array_slice( $parts, 0, $i ) );

		if ( isset( $parts[$i] ) && is_numeric( $parts[$i] ) ) {
			$data['version'] = $parts[$i];
			$i++;
		} else {
			return null;
		}

		if ( isset( $parts[$i] ) && (
			preg_match( '/^\w\w$/', $parts[$i] )
			|| $parts[$i] == 'scotland'
		) ) {
			$data['region'] = $parts[$i];
			$i++;
		}

		if ( $i != count( $parts ) ) {
			return null;
		}

		$data['name'] = implode( '-', array_filter( array( $data['type'], $data['version'], $data['region'] ) ) );
		return $data;
	}
}

/**
 * Class to parse metadata from commons formatted wiki pages.
 * Relies on the attributes set by {{Information}} and similar templates - see
 * https://commons.wikimedia.org/wiki/Commons:Machine-readable_data
 *
 * @note lots of methods in this class are public, despite not really
 * being "public" in order that the XMLParser can call them
 */
class CommonsMetadata_InformationParser {
	public static $multivaluedProperties = array(
		'LicenseShortName',
	);

	private $xmlParser;
	private $state = self::STATE_INITIAL;
	private $text;
	private $propName;
	private $tdDepth = 0;
	private $divDepth = 0;
	private $spanDepth = 0;
	private $tableDepth = 0;
	private $finalProps = array();
	private $curExtractionLang = '';
	private $extractionLang;
	private $langText = '';
	private $allLangTexts = array();
	private $curLangText = '';
	private $fallbackLangs;
	private $targetLang = false; // /< false for all, language code otherwise.
	private $langTextPriority = 2000;

	const STATE_INITIAL = 1;
	const STATE_NEXTTD = 2; // /< Next <td> contains element we care about
	const STATE_CAPTURE_TEXT = 3; // /< Capture text (from {{information}})
	const STATE_CAPTURE_LANG = 4; // /< Capture text of a language tag
	const STATE_LICENSE = 5; // /< We are in a license table
	const STATE_CAPTURE_SPAN = 6; // /< Capture metadata from a license tag

	const XML_ERR_TAG_NAME_MISMATCH = 76;

	/**
	 * Constructor.
	 */
	function __construct() {
		$this->resetParser();
	}

	/**
	 * @param String|boolean $lang Language code or false for all langs.
	 */
	function setLanguage($lang) {
		$this->targetLang = $lang;
	}

	/**
	 * Clear and re-initialize the xml parser.
	 *
	 * Used after initially and after encountering an error.
	 */
	private function resetParser() {
		if ( !function_exists( 'xml_parser_create' ) ) {
			throw new MWException( 'No XML parser support' );
		}
		$this->xmlParser = xml_parser_create( 'UTF-8' );
		if ( !$this->xmlParser ) {
			throw new MWException( 'Could not create parser' );
		}
		xml_set_character_data_handler( $this->xmlParser, array( $this, 'char' ) );
		xml_set_element_handler( $this->xmlParser, array( $this, 'elmStart' ), array( $this, 'elmEnd' ) );
	}

	/**
	 * Parse an html string for metadata.
	 *
	 * This is the main entry point to the class.
	 *
	 * @param $html String The html to parse
	 * @return Array The properties extracted from the page.
	 */
	public function parsePage( $html ) {
		$xml = "<renderout>$html</renderout>";
		$success = false;
		// If there's an error (for example html5 syntax), we try and skip over it.
		while ( !$success && strlen( $xml ) > 11 ) {
			$success = xml_parse( $this->xmlParser, $xml );
			if ( !$success ) {
				if ( xml_get_error_code( $this->xmlParser ) !== self::XML_ERR_TAG_NAME_MISMATCH ) {
					wfDebug( __METHOD__ . " Error reading xml: " . $this->getError() . "\n" );
					break;
				}

				$offset = xml_get_current_byte_index( $this->xmlParser );
				if ( $offset <= 11 ) {
					// We want to remove at least as much as we are adding
					// back in, when we concatenate '<renderout>'.
					wfDebug( __METHOD__ . " Loop\n" );
					break;
				}
				$xml = '<renderout>' . substr( $xml, $offset );
				$this->resetParser();
			}
		}
		return $this->finalProps;
	}

	/**
	 * Event handler for an opening element.
	 *
	 * @param $parser XMLParser
	 * @param $name String Name of element (all capitals)
	 * @param $attribs Array Array of attributes (attribute names all capital)
	 */
	public function elmStart( $parser, $name, array $attribs ) {
		switch ( $this->state ) {
			case self::STATE_INITIAL:
				if ( $name === 'TD' ) {
					$this->elmStartInitial( $attribs );
				} elseif( $name === 'TABLE' ) {
					$this->elmStartTable( $attribs );
				} elseif( $name === 'SPAN'
					&& isset( $attribs['CLASS'] )
					&& $attribs['CLASS'] === 'geo'
				) {
					// Special case for 'geo'
					$this->elmStartSpan( $attribs );
				}
				break;
			case self::STATE_NEXTTD:
				if ( $name === 'TD' ) {
					if ( $this->tdDepth <= 0 ) {
						$this->state = self::STATE_CAPTURE_TEXT;
						$this->tdDepth = 1;
						$this->text = '';
						$this->langText = '';
						$this->extractionLang = '';
					} else {
						$this->tdDepth++;
					}
				}
				break;
			case self::STATE_LICENSE:
				if ( $name === 'TABLE' ) {
					$this->tableDepth++;
				} elseif( $name === 'SPAN' ) {
					$this->elmStartSpan( $attribs );
				}
				break;
			case self::STATE_CAPTURE_TEXT:
				if ( $name === 'TD' ) {
					$this->tdDepth++;
				}
				// @todo May want to strip off the leading "<language>:" of the language
				// tag if only extracting one language.
				if ( $name === 'DIV' && isset( $attribs['CLASS'] ) && isset( $attribs['LANG'] ) ) {
					if ( preg_match( '/(?:^|\s)description(?:\s|$)/', $attribs['CLASS'] ) ) {
						$this->state = self::STATE_CAPTURE_LANG;
						$this->curExtractionLang = $attribs['LANG'];
						$this->curLangText = '';
						$this->divDepth = 1;
					}
				}
				$this->text .= Html::openElement( $name, $attribs );
				break;
			case self::STATE_CAPTURE_SPAN:
				if ( $name === 'SPAN' ) {
					$this->spanDepth++;
				}
				$this->text .= Html::openElement( $name, $attribs );
				break;
			case self::STATE_CAPTURE_LANG:
				if ( $name === 'TD' ) {
					$this->tdDepth++;
				} elseif( $name === 'DIV' ) {
					$this->divDepth++;
				}
				$this->curLangText .= Html::openElement( $name, $attribs );
				break;
		}
	}

	private function elmStartInitial( $attribs ) {
		$nextTdIds = array(
			'fileinfotpl_desc' => 'ImageDescription',
			# For date: Open question - should we parse the commons
			# date field better to deal with templates like
			# {{Taken on}} et al. along with extracting a time stamp
			# from the human readable field?
			'fileinfotpl_date' => 'DateTimeOriginal',
			'fileinfotpl_aut' => 'Artist',
			# For "source" field of {{information}} there are two closely
			# related fields we could map it to. Credit (iptc 2:110) is
			# "Identifies the provider of the media, not necessarily the
			# owner/creator." Source (iptc 2:115) "Identifies the
			# original owner of the intellectual content of the media. This
			# could be an agency, a member of an agency or an individual."
			# I think "Credit" fits much more closely to the commons notion
			# of source than "Source" does.
			'fileinfotpl_src' => 'Credit',
			'fileinfotpl_art_title' => 'ObjectName',
			'fileinfotpl_book_title' => 'ObjectName',
		);
		if ( isset( $attribs['ID'] ) ) {
			if ( isset( $nextTdIds[ $attribs['ID'] ] ) ) {
				$this->propName = $nextTdIds[ $attribs['ID'] ];
				$this->state = self::STATE_NEXTTD;
				$this->tdDepth = 1;
			}
		}
	}

	/**
	 * We may have hit a license table.
	 *
	 * @param $attribs Array List of attributes.
	 */
	private function elmStartTable( $attribs ) {
		if ( isset( $attribs['CLASS'] ) ) {
			if ( preg_match( '/(?:^|\s)licensetpl(?:\s|$)/', $attribs['CLASS'] ) ) {
				$this->state = self::STATE_LICENSE;
				$this->tableDepth = 1;
			}
		}
	}

	/**
	 * Maybe hit license info or geo data.
	 *
	 * @todo Pages with multiple licenses aren't handled properly
	 * @param $attribs Array List of attributes
	 */
	private function elmStartSpan( $attribs ) {
		$mapping = array(
			'licensetpl_link' => 'LicenseUrl',
			'licensetpl_long' => 'UsageTerms',
			'licensetpl_short' => 'LicenseShortName',
			'geo' => 'GPS', // Not final property name
		);

		if ( isset( $attribs['CLASS'] ) && isset( $mapping[ $attribs['CLASS'] ] ) ) {
			$this->state = self::STATE_CAPTURE_SPAN;
			$this->propName = $mapping[ $attribs['CLASS'] ];
			$this->spanDepth = 1;
		}

		// Possible later todo: Web statement could be link to file page
	}

	/**
	 * Handler for a closing element.
	 *
	 * @param $parser XMLParser
	 * @param $name String Name of element (all caps)
	 */
	public function elmEnd( $parser, $name ) {
		switch ( $this->state ) {
			case self::STATE_INITIAL:
				break;
			case self::STATE_NEXTTD:
				if ( $name === 'TD' ) {
					$this->tdDepth--;
				}
				break;
			case self::STATE_LICENSE:
				if ( $name === 'TABLE' ) {
					$this->tableDepth--;
					if ( $this->tableDepth <= 0 ) {
						$this->state = self::STATE_INITIAL;
					}
				}
				break;
			case self::STATE_CAPTURE_SPAN:
				if ( $name === 'SPAN' ) {
					$this->spanDepth--;
					if ( $this->spanDepth <= 0 ) {
						$this->state = self::STATE_LICENSE;
						$this->addPropertyValue( $this->finalProps, $this->propName, $this->text );
						if ( $this->propName === 'UsageTerms' ) {
							$copyrighted = ( $this->text === 'Public domain' ) ? 'False' : 'True';
							$this->addPropertyValue( $this->finalProps, 'Copyrighted', $copyrighted );
						} elseif ( $this->propName === 'GPS' ) {
							$coord = explode( ';', $this->text );
							if ( count( $coord ) === 2 &&
								is_numeric( $coord[0] ) &&
								is_numeric( $coord[1] )
							) {
								$this->addPropertyValue( $this->finalProps, 'GPSLatitude', $coord[0] );
								$this->addPropertyValue( $this->finalProps, 'GPSLongitude', $coord[1] );
								$this->addPropertyValue( $this->finalProps, 'GPSMapDatum', 'WGS-84' );
								unset( $this->finalProps['GPS'] );
							}
							$this->state = self::STATE_INITIAL;
						}
						$this->text = '';
						$this->propName = '';
					}
				} else {
					$this->text .= Html::closeElement( $name );
				}
				break;
			case self::STATE_CAPTURE_TEXT:
				if ( $name === 'TD' ) {
					$this->tdDepth--;
				}
				if ( $this->tdDepth <= 0 ) {
					$this->state = self::STATE_INITIAL;

					if ( $this->langText !== '' ) {
						if ( $this->targetLang ) {
							$propValue = Html::rawElement(
								'span',
								// FIXME dir too?
								array( 'lang' => $this->extractionLang ),
								$this->langText
							);
						} else {
							$propValue = array( '_type' => 'lang' );
							foreach ( $this->allLangTexts as $lang => $text ) {
								$propValue [$lang] = Html::rawElement(
									'span',
									// FIXME dir too?
									array( 'lang' => $lang ),
									$text
								);
							}
						}
					} else {
						$propValue = $this->text;
					}
					$this->addPropertyValue( $this->finalProps, $this->propName, $propValue );

					$this->langText = '';
					$this->extractionLang = '';
					$this->text = '';
					$this->tdDepth = 0;
					$this->propName = '';
				} else {
					$this->text .= Html::closeElement( $name );
				}
				break;
			case self::STATE_CAPTURE_LANG:
				if ( $name === 'TD' ) {
					$this->tdDepth--;
				} elseif( $name === 'DIV' ) {
					$this->divDepth--;
				}

				if ( $name === 'DIV' && $this->divDepth <= 0 ) {
					// We are done the lang section
					$this->allLangTexts[$this->curExtractionLang] = $this->curLangText;
					$fallbacks = $this->getFallbacks();
					if ( isset( $fallbacks[ $this->curExtractionLang ] ) ) {
						$priority = $fallbacks[ $this->curExtractionLang ];
					} else {
						$priority = 1000;
					}
					if ( $priority < $this->langTextPriority ) {
						// This is a more important extraction then previous
						$this->langText = $this->curLangText;
						$this->extractionLang = $this->curExtractionLang;
						$this->langTextPriority = $priority;
					} else {
						// Throw away this translation.
						$this->curLangText = '';
						$this->curExtractionLang = '';
					}
					$this->state = self::STATE_CAPTURE_TEXT;
				} else {
					$this->curLangText .= Html::closeElement( $name );
				}

				break;
		}
	}

	/**
	 * Handler for character data
	 *
	 * @param $parser XMLParser
	 * @param $text String The character data.
	 */
	public function char( $parser, $text ) {
		if ( $this->state === self::STATE_CAPTURE_TEXT
			|| $this->state === self::STATE_CAPTURE_SPAN
		) {
			$this->text .= htmlspecialchars( $text );
		} elseif ( $this->state === self::STATE_CAPTURE_LANG ) {
			$this->curLangText .= htmlspecialchars( $text );
		}
	}

	/**
	 * Get list of language fallbacks.
	 *
	 * If given a choice of multiple languages, this is used to decide
	 * which language is closest to the target language
	 *
	 * @return Array List of language codes
	 */
	private function getFallbacks() {
		if ( $this->fallbackLangs ) {
			return $this->fallbackLangs;
		}

		if ( !$this->targetLang ) {
			return array();
		}

		$fallbacks = Language::getFallbacksFor( $this->targetLang );
		array_unshift( $fallbacks, $this->targetLang );

		$this->fallbackLangs = array_flip( $fallbacks );
		return $this->fallbackLangs;
	}

	/**
	 * Get last error. Primarily used for debugging.
	 */
	public function getError() {
		return xml_get_current_byte_index( $this->xmlParser ) . ': ' . xml_error_string( xml_get_error_code( $this->xmlParser ));
	}

	/**
	 * Checks configuration and decides whether to store a single value and override old ones or store an array of
	 * values.
	 * @param array $properties array used to collect the property values
	 * @param string $name
	 * @param string $value
	 */
	private function addPropertyValue( &$properties, $name, $value ) {
		if ( in_array( $name, self::$multivaluedProperties ) ) {
			$properties[$name][] = $value;
		} else {
			$properties[$name] = $value;
		}
	}
}
