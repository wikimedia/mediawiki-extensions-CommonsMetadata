<?php

namespace CommonsMetadata;

/**
 * Takes a license name string, and splits it up into various license elements (version, etc).
 * The string is typically a Commons category name, or template name, or license shortname
 * (see {@link https://commons.wikimedia.org/wiki/Commons:Machine-readable_data})
 */
class LicenseParser {

	/**
	 * Nonstandard license name patterns used in categories/templates/shortnames
	 */
	public static $licenseAliases = array(
		'cc-by-sa-3.0-migrated' => 'cc-by-sa-3.0',
		'cc-by-sa-3.0-migrated-with-disclaimers' => 'cc-by-sa-3.0',
		'cc-by-sa-3.0-2.5-2.0-1.0' => 'cc-by-sa-3.0',
		'cc-by-sa-2.5-2.0-1.0' => 'cc-by-sa-2.5',
		'cc-by-2.0-stma' => 'cc-by-2.0',
		'cc-by-sa-1.0+' => 'cc-by-sa-3.0',
	);

	/**
	 * Takes a CC license string (could be a category name, template name etc)
	 * and returns template information (or null if the license was not recognized).
	 * The returned array can have the following keys:
	 * - family: e.g. cc, gfdl
	 * - type: e.g. cc-by-sa
	 * - version: e.g. 2.5
	 * - region: e.g. nl
	 * - name: all the above put together, e.g. cc-by-sa-2.5-nl
	 * Only name is required.
	 * @param string $str
	 * @return array|null
	 */
	public function parseLicenseString( $str ) {
		return
			$this->parseCreativeCommonsLicenseString( $str )
			?: $this->parsePublicDomainLicenseString( $str );
	}

	/**
	 * Takes a CC license string and returns template information.
	 * @see parseLicenceString()
	 * @param string $str
	 * @return array|null
	 */
	public function parseCreativeCommonsLicenseString( $str ) {
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

	/**
	 * Takes a PD license string and returns template information.
	 * @see parseLicenceString()
	 * @param string $str
	 * @return array|null
	 */
	protected function parsePublicDomainLicenseString( $str ) {
		// A very simple approach, but should work most of the time with licence shortnames.
		if ( strtolower( $str ) === 'public domain' ) {
			return array(
				'family' => 'pd',
				'name' => 'pd',
			);
		}
		return null;
	}
}