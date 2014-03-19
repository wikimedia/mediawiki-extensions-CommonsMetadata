<?php

namespace CommonsMetadata;

use Language;
use Title;
use File;
use LocalFile;
use ForeignAPIFile;
use WikiFilePage;
use ScopedCallback;

/**
 * Class to handle metadata collection and formatting, and manage more specific data extraction classes.
 */
class DataCollector {

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
	 * Language in which data should be collected. Can be null, which means collect all languages.
	 * @var Language
	 */
	protected $language;

	/**
	 * If true, ignore $language and collect metadata in all languages.
	 * @var bool
	 */
	protected $multiLang;

	/** @var TemplateParser */
	protected $templateParser;

	/** @var  LicenseParser */
	protected $licenseParser;

	/**
	 * @param Language $language
	 */
	public function setLanguage( $language ) {
		$this->language = $language;
	}

	/**
	 * @param boolean $multiLang
	 */
	public function setMultiLang( $multiLang ) {
		$this->multiLang = $multiLang;
	}

	/**
	 * @param TemplateParser $templateParser
	 */
	public function setTemplateParser( TemplateParser $templateParser ) {
		$this->templateParser = $templateParser;
	}

	/**
	 * @param LicenseParser $licenseParser
	 */
	public function setLicenseParser( LicenseParser $licenseParser ) {
		$this->licenseParser = $licenseParser;
	}

	/**
	 * Collects metadata from a file, and adds it to a metadata array. The array has the following format:
	 *
	 * '<metadata field name>' => array(
	 *     'value' => '<value>',
	 *     'source' => '<where did the data come from>',
	 * )
	 *
	 * For fields with multiple values and/or in multiple languages the format is more complex;
	 * see the documentation for the extmetadata API.
	 *
	 * @param array $previousMetadata metadata collected so far; new metadata will be added to this array
	 * @param File $file
	 */
	public function collect( array &$previousMetadata, File $file ) {
		$descriptionText = $this->getDescriptionText( $file, $this->language );

		$templateMetadata = $this->templateParser->parsePage( $descriptionText );

		$categories = $this->getCategories( $file, $previousMetadata );

		$assessments = $this->getAssessmentsAndRemoveFromCategories( $categories );
		$licenses = $this->getLicensesAndRemoveFromCategories( $categories );
		if ( !$licenses && isset( $templateMetadata['LicenseShortName'] ) ) {
			$license = $this->filterShortnamesAndGetLicense( $templateMetadata['LicenseShortName'] );
			$licenses = array( $license );
		}

		$previousMetadata['Categories'] = array(
			'value' => implode( '|', $categories ),
			'source' => 'commons-categories',
		);

		$previousMetadata['Assessments'] = array(
			'value' => implode('|', $assessments),
			'source' => 'commons-categories',
		);

		if ( $licenses ) {
			$previousMetadata['License'] = array(
				'value' => $licenses[0],
				'source' => 'commons-templates',
			);
		}

		foreach( $templateMetadata as $name => $value ) {
			if ( in_array( $name, $this->templateParser->getMultivaluedProperties() ) ) {
				// the GetExtendedMetadata hook expects the property value to be a string,
				// so we have to throw away all values but one here.
				$value = end( $value );
			}
			$previousMetadata[ $name ] = array(
				'value' => $value,
				'source' => 'commons-desc-page'
			);
		}
	}

	/**
	 * Gets the text of the file's description page.
	 * @param File $file
	 * @param Language $language
	 * @return string
	 */
	protected function getDescriptionText( File $file, Language $language ) {
		# Note: If this is a local file, there is no caching here.
		# However, the results of this module have longer caching for local
		# files to help compensate. For foreign files, this method is cached
		# via parser cache, and possibly a second cache depending on
		# descriptionCacheExpiry (disabled on Wikimedia).

		if ( get_class( $file ) == 'LocalFile' ) {
			// LocalFile gets the text in a different way, and ends up with different output
			// (specifically, relative instead of absolute URLs). There is no proper way to
			// influence this process (see the end of Title::getLocalURL for details), so
			// we mess with one of the hooks.
			// The ScopedCallback object will unmess it once this method returns and the object is destructed.

			global $wgHooks;
			$makeAbsolute = function( Title $title, &$url, $query ) {
				global $wgServer, $wgRequest;
				if (
					substr( $url, 0, 1 ) === '/' && substr( $url, 1, 2 ) !== '/' // relative URL
					&& $wgRequest->getVal( 'action' ) != 'render' // for action=render $wgServer will be added in getLocalURL
				) {
					$url = $wgServer . $url;
				}
				return true;
			};
			$wgHooks['GetLocalURL::Internal']['CommonsMetadata::getDescriptionText'] = $makeAbsolute;

			$sc = new ScopedCallback( function() {
				global $wgHooks;
				unset( $wgHooks['GetLocalURL::Internal']['CommonsMetadata::getDescriptionText'] );
			} );
		}
		$text = $file->getDescriptionText( $language );
		return $text;
	}

	/**
	 * @param File $file
	 * @param array $data metadata passed to the onGetExtendedMetadata hook
	 * @return array list of category names in human-readable format
	 */
	protected function getCategories( File $file, array $data ) {
		$categories = array();

		if ( $file instanceof LocalFile ) {
			// for local or shared DB files (which are also LocalFile subclasses)
			// categories can be queried directly from the database

			$page = new WikiFilePage( $file->getOriginalTitle() );
			$page->setFile( $file );

			$categoryTitles = $page->getForeignCategories();

			foreach ( $categoryTitles as $title ) {
				$categories[] = $title->getText();
			}
		} elseif (
			$file instanceof ForeignAPIFile
			&& isset( $data['Categories'] )
		) {
			// getting categories for a ForeignAPIFile is not supported, but in case
			// CommonsMetadata is installed on the remote repository as well, its output
			// (including categories) is sent together with the extended file metadata,
			// when the file is loaded. onGetExtendedMetadata hooks get that metadata
			// when they are invoked.
			$categories = explode( '|', $data['Categories']['value'] );
		} else {
			// out of luck - file is probably from a ForeignAPIRepo with CommonsMetadata not installed there
			wfDebug( 'CommonsMetadata: cannot read category data' );
		}

		return $categories;
	}

	/**
	 * Matches category names to a category => license mapping, removes the matching categories
	 * and returns the corresponding licenses.
	 * @param array $categories a list of human-readable category names.
	 * @return array
	 */
	protected function getLicensesAndRemoveFromCategories( &$categories ) {
		$licenses = array();
		foreach ( $categories as $i => $category ) {
			$licenseData = $this->licenseParser->parseLicenseString( $category );
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
	protected function getAssessmentsAndRemoveFromCategories( &$categories ) {
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
	 * @return string|null something like 'cc-by-sa-3.0-nl', or null if not recognized
	 * @see https://commons.wikimedia.org/wiki/Commons:Machine-readable_data#Machine_readable_data_set_by_license_templates
	 */
	protected function filterShortnamesAndGetLicense( &$shortNames ) {
		foreach ( $shortNames as $name ) {
			$licenseData = $this->licenseParser->parseLicenseString( $name );
			if ( $licenseData ) {
				$shortNames = array( $name );
				return $licenseData['name'];
			}
		}
		return null;
	}
}
