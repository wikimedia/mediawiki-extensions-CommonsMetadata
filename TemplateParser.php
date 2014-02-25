<?php

namespace CommonsMetadata;

use DOMNodeList;
use DOMNode;
use DOMElement;

/**
 * Class to parse metadata from commons formatted wiki pages.
 * Relies on the attributes set by {{Information}} and similar templates - see
 * https://commons.wikimedia.org/wiki/Commons:Machine-readable_data
 */
class TemplateParser {
	/**
	 * Normally, if a property appears multiple times, the old value is overwritten.
	 * For these properties, all values are returned in an array.
	 * FIXME this is BC behavior, multivalued fields should be handled in a more clever way - bug 57259
	 * @var array
	 */
	protected static $multivaluedProperties = array(
		'LicenseShortName',
	);

	/**
	 * HTML element class name => metadata field name mapping for license data.
	 * @var array
	 */
	protected static $licenseFieldClasses = array(
		'licensetpl_short' => 'LicenseShortName',
		'licensetpl_long' => 'UsageTerms',
		// 'licensetpl_attr_req',
		// 'licensetpl_attr',
		// 'licensetpl_link_req',
		'licensetpl_link' => 'LicenseUrl',
	);

	/**
	 * HTML element id => metadata field name mapping for information template data.
	 * @var array
	 */
	protected static $informationFieldClasses = array(
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
		'fileinfotpl_perm' => 'Permission',
	);

	/** @var array */
	protected $priorityLanguages = array( 'en' );

	/** @var bool */
	protected $multiLanguage = false;

	/**
	 * Normally, if a property appears multiple times, the old value is overwritten.
	 * For these properties, all values are returned in an array.
	 * @return array
	 */
	public function getMultivaluedProperties() {
		return self::$multivaluedProperties;
	}

	/**
	 * When parsing multi-language text, use the first available language from this array.
	 * (Order matters - try to use the first element, if not available the second etc.)
	 * When set to false, will return all languages.
	 * @param array $priorityLanguages
	 */
	public function setPriorityLanguages( $priorityLanguages ) {
		$this->priorityLanguages = $priorityLanguages;
	}

	/**
	 * When true, the parser will ignore $priorityLanguages and return all available languages.
	 * @param bool $multiLanguage
	 */
	public function setMultiLanguage( $multiLanguage ) {
		$this->multiLanguage = $multiLanguage;
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
		if ( !$html ) { // DOMDocument does not like empty strings
			return array();
		}

		$domNavigator = new DomNavigator( $html );

		$data = array();
		$data += $this->parseCoordinates( $domNavigator );
		$data += $this->parseInformationFields( $domNavigator );
		$data += $this->parseLicenses( $domNavigator );

		return $data;
	}

	/**
	 * Parses geocoded coordinates.
	 * @param DomNavigator $domNavigator
	 * @return array
	 */
	protected function parseCoordinates( DomNavigator $domNavigator ) {
		$data = array();
		foreach ( $domNavigator->findElementsWithClass( 'span', 'geo' ) as $geoNode ) {
			$coords = explode( ';', trim( $geoNode->textContent ) );
			if ( count( $coords ) == 2 && is_numeric( $coords[0] ) && is_numeric( $coords[1] ) ) {
				$data['GPSLatitude'] = $coords[0];
				$data['GPSLongitude'] = $coords[1];
				$data['GPSMapDatum'] = 'WGS-84';
				break; // multiple coordinates for a single image would not be meaningful, so we just return the first valid one
			}
		}
		return $data;
	}

	protected function parseInformationFields( DomNavigator $domNavigator ) {
		$data = array();
		foreach ( $domNavigator->findElementsWithIdPrefix( 'td', 'fileinfotpl_' ) as $labelField ) {
			$id = $labelField->getAttribute( 'id' );
			if ( !isset( self::$informationFieldClasses[$id] ) ) {
				continue;
			}
			$fieldName = self::$informationFieldClasses[$id];

			$informationField = $domNavigator->nextElementSibling( $labelField );
			if ( !$informationField ) {
				continue;
			}

			// group fields coming from the same template
			$table = $domNavigator->closest( $labelField, 'table' );
			$groupName = $table ? $table->getNodePath() : '-';

			$method = 'parseField' . $fieldName;

			if ( !method_exists( $this, $method ) ) {
				$method = 'parseText';
			}

			$data[$groupName][$fieldName] = $this->{$method}( $domNavigator, $informationField );
		}
		//return $this->arrayTranspose( $data );
		// FIXME bug 57259 - for now select the first information template if there are more than one
		return $data ? reset($data) : array();
	}

	/**
	 * Parses the artist, which might be an hCard
	 * @param DomNavigator $domNavigator
	 * @param DOMNode $node
	 * @returns string
	 */
	protected function parseFieldArtist( DomNavigator $domNavigator, DOMNode $node ) {
		if ( $field = $this->extractHCardProperty(  $domNavigator, $node, 'fn' ) ) {
			return $this->innerHtml( $field );
		}

		return $this->parseText( $domNavigator, $node );
	}

	/**
	 * Extracts an hCard property from a DOMNode that contains an hCard
	 * @param DomNavigator $domNavigator
	 * @param DOMNode $node
	 * @param string $property hCard property to be extracted
	 * @return DOMNode
	 */
	protected function extractHCardProperty( DomNavigator $domNavigator, DOMNode $node, $property ) {
		foreach ( $domNavigator->findElementsWithClass( '*', 'vcard', $node ) as $vcard ) {
			foreach ( $domNavigator->findElementsWithClass( '*', $property, $vcard ) as $name ) {
				return $name;
			}
		}
	}

	/**
	 * @param DomNavigator $domNavigator
	 * @return array
	 */
	protected function parseLicenses( DomNavigator $domNavigator ) {
		$data = array();
		foreach ( $domNavigator->findElementsWithClass( '*', 'licensetpl' ) as $licenseNode ) {
			$licenseData = $this->parseLicenseNode( $domNavigator, $licenseNode );
			if ( isset( $licenseData['UsageTerms'] ) ) {
				$licenseData['Copyrighted'] = ( $licenseData['UsageTerms'] === 'Public domain' ) ? 'False' : 'True';
			}
			$data[] = $licenseData;
		}
		$data = $this->arrayTranspose( $data );

		// FIXME for backwards compatibility / to make it easier to compare output with old parser
		foreach ( $data as $fieldName => $value ) {
			if ( empty( $value ) ) {
				unset( $data[$fieldName] );
			} elseif ( !in_array( $fieldName, self::getMultivaluedProperties() ) ) {
				$data[$fieldName] = end( $value );
			} else {
				$data[$fieldName] = array_values( $value );
			}
		}
		return $data;
	}

	/**
	 * @param DomNavigator $domNavigator
	 * @param DOMNode $licenseNode
	 * @return array
	 */
	protected function parseLicenseNode( DomNavigator $domNavigator, DOMNode $licenseNode ) {
		$data = array();
		foreach ( self::$licenseFieldClasses as $class => $fieldName ) {
			foreach ( $domNavigator->findElementsWithClass( '*', $class, $licenseNode ) as $node) {
				$data[$fieldName] = $this->innerHtml( $node );
				break;
			}
		}
		return $data;
	}

	/**
	 * Get the text of a node. The result might be a string, or an array of strings if the node has multiple
	 * languages (resulting from {{en}} and similar templates).
	 * @param DomNavigator $domNavigator
	 * @param DOMNode $node
	 * @return string|array
	 */
	protected function parseText( DomNavigator $domNavigator, DOMNode $node ) {
		$languageNodes = $domNavigator->findElementsWithClassAndLang( 'div', 'description', $node );
		if ( !$languageNodes->length ) { // no language templates at all
			return $this->innerHtml( $node );
		}
		$languages = array();
		foreach ( $languageNodes as $node ) {
			//$node = $this->removeLanguageName( $domNavigator, $node );
			$languageCode = $node->getAttribute( 'lang' );
			$languages[$languageCode] = $node;
		}
		if ( !$this->multiLanguage ) {
			return $this->innerHtml( $this->selectLanguage( $languages ) );
		} else {
			$languages = array_map( array( $this, 'innerHtml' ), $languages );
			$languages['_type'] = 'lang';
			return $languages;
		}
	}

	/**
	 * Language templates like {{en}} put the language name at the beginning of the text;
	 * this function removes it.
	 * @param DomNavigator $domNavigator
	 * @param DOMElement $node
	 * @return DOMElement a clone of the input node, with the language name removed
	 */
	protected function removeLanguageName( DomNavigator $domNavigator, DOMElement $node ) {
		$node = $node->cloneNode( true );
		$languageNames = $domNavigator->findElementsWithClass( 'span', 'language', $node );
		foreach ( $languageNames as $languageName ) {
			if ( ! $node->isSameNode( $languageName->parentNode ) ) {
				continue; // language names are direct children
			}
			$node->removeChild( $languageName );
		}
		return $node;
	}

	/**
	 * Takes an array indexed with language codes, and returns the best match.
	 * @param array $languages
	 * @return mixed
	 */
	protected function selectLanguage( array $languages ) {
		foreach ( $this->priorityLanguages as $languageCode ) {
			if ( array_key_exists( $languageCode, $languages ) ) {
				return $languages[$languageCode];
			}
		}
		return reset($languages);
	}

	/**
	 * Turns a node into a HTML string
	 * @param DOMNode $node
	 * @return string
	 */
	protected function toHtml( DOMNode $node ) {
		if ( version_compare( phpversion(), '5.3.6', '>=') ) { // node parameter was added to saveHTML in 5.3.6
			return $node->ownerDocument->saveHTML( $node );
		} else {
			return $node->ownerDocument->saveXML( $node ); // uglier output; still better than nothing
		}
	}

	/**
	 * Turns a node into plain text
	 * @param DOMNode $node
	 * @return string
	 */
	protected function toText( DOMNode $node ) {
		return trim( $node->textContent );
	}

	/**
	 * Turns a node into HTML, except for the enclosing tag.
	 * @param DOMNode $node
	 * @return string
	 */
	protected function innerHtml( DOMNode $node ) {
		if ( ! $node instanceof DOMElement ) {
			return $this->toHtml( $node );
		}

		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $this->toHtml( $child );
		}
		return $html;
	}

	/**
	 * Switch rows and columns. Usually it is easier to collect data grouped by source template,
	 * but the extmetadata API needs grouping by field name, this function turns around the grouping.
	 * @param array $data
	 * @return array
	 */
	protected function arrayTranspose( array $data ) {
		$transposedData = array();
		foreach ( $data as $groupName => $group ) {
			foreach ( $group as $fieldName => $value ) {
				$transposedData[$fieldName][$groupName] = $value;
			}
		}
		return $transposedData;
	}
}
