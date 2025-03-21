<?php

namespace CommonsMetadata;

/**
 * Takes a license name string, and splits it up into various license elements (version, etc).
 * The string is typically a Commons category name, or template name, or license shortname
 * (see {@link https://commons.wikimedia.org/wiki/Commons:Machine-readable_data})
 */
class LicenseParser {

	/**
	 * @var string[] Nonstandard license name patterns used in categories/templates/shortnames
	 */
	public static $licenseAliases = [
		'cc-by-sa-3.0-migrated' => 'cc-by-sa-3.0',
		'cc-by-sa-3.0-migrated-with-disclaimers' => 'cc-by-sa-3.0',
		'cc-by-sa-3.0-2.5-2.0-1.0' => 'cc-by-sa-3.0',
		'cc-by-sa-2.5-2.0-1.0' => 'cc-by-sa-2.5',
		'cc-by-2.0-stma' => 'cc-by-2.0',
		'cc-by-sa-1.0+' => 'cc-by-sa-3.0',
	];

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
	 * @param string|null $str
	 * @return array|null
	 */
	public function parseLicenseString( ?string $str ): ?array {
		if ( $str === null ) {
			return null;
		}

		return $this->parseCreativeCommonsLicenseString( $str )
			?: $this->parsePublicDomainLicenseString( $str );
	}

	/**
	 * Takes an array width license data and sorts it by license priority.
	 * The sort is stable, and the input array is not changed.
	 * The method will call $getLicenseStringCallback( $data[$key], $key ) and expect a license name
	 * @param string[] $data
	 * @param callable $getLicenseStringCallback
	 * @return array
	 */
	public function sortDataByLicensePriority( array $data, $getLicenseStringCallback ) {
		$licensePriorities = [];
		$i = 0;
		foreach ( $data as $key => $value ) {
			$license = $getLicenseStringCallback( $value, $key );
			$licenseDetails = $this->parseLicenseString( $license );
			$priority = $this->getLicensePriority( $licenseDetails );
			$licensePriorities[$key] = [ $priority, $i ];
			$i++;
		}

		uksort( $data, static function ( $a, $b ) use ( $licensePriorities ) {
			// equal priority, keep original order
			if ( $licensePriorities[$a][0] === $licensePriorities[$b][0] ) {
				return $licensePriorities[$a][1] - $licensePriorities[$b][1];
			} else {
				// higher priority means smaller wrt sorting
				return $licensePriorities[$b][0] - $licensePriorities[$a][0];
			}
		} );

		return $data;
	}

	/**
	 * Takes a CC license string and returns template information.
	 * @see parseLicenceString()
	 * @param string $str
	 * @return array|null
	 */
	protected function parseCreativeCommonsLicenseString( $str ) {
		$data = [
			'family' => 'cc',
			'type' => null,
			'version' => null,
			'region' => null,
			'name' => null,
		];

		$str = strtolower( trim( $str ) );
		if ( isset( self::$licenseAliases[$str] ) ) {
			$str = self::$licenseAliases[$str];
		}

		// some special cases first
		if ( in_array( $str, [ 'cc0', 'cc-pd' ], true ) ) {
			$data['type'] = $data['name'] = $str;
			return $data;
		}

		$parts = preg_split( '/[- ]/', $str );
		if ( $parts[0] != 'cc' ) {
			return null;
		}

		$countParts = count( $parts );
		for ( $i = 1; $i < $countParts; $i++ ) {
			if ( !in_array( $parts[$i], [ 'by', 'sa', 'nc', 'nd' ] ) ) {
				break;
			}
			if ( in_array( $parts[$i], [ 'nc', 'nd' ] ) ) {
				// ignore non-free licenses
				return null;
			}
		}
		$data['type'] = implode( '-', array_slice( $parts, 0, $i ) );

		if ( $i < $countParts && is_numeric( $parts[$i] ) ) {
			$data['version'] = $parts[$i];
			$i++;
		} else {
			return null;
		}

		if ( $i < $countParts && (
				preg_match( '/^\w\w$/', $parts[$i] )
				|| $parts[$i] == 'scotland'
			)
		) {
			$data['region'] = $parts[$i];
			$i++;
		}

		if ( $i != $countParts ) {
			return null;
		}

		$data['name'] = implode( '-',
			array_filter( [ $data['type'], $data['version'], $data['region'] ] ) );
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
		if ( strtolower( $str ) === 'public domain' || strtolower( $str ) === 'pd' ) {
			return [
				'family' => 'pd',
				'name' => 'pd',
			];
		}
		return null;
	}

	/**
	 * Returns a priority value for this license. The license with the highest priority will be
	 * returned in the GetExtendedMetadata hook.
	 * @param array|null $licenseData data from LicenseParser::parseLicenseString()
	 * @return int
	 */
	protected function getLicensePriority( $licenseData ) {
		$priority = 0;
		if ( isset( $licenseData[ 'family' ] ) ) {
			if ( $licenseData[ 'family' ] === 'pd' ) {
				$priority = 2000;
			} elseif ( $licenseData[ 'family' ] === 'cc' ) {
				// ignore non-free CC licenses for now; an image with such a license probably
				// won't have a better license anyway
				$priority += 1000;
				if ( isset( $licenseData['type'] ) && $licenseData['type'] === 'cc-by' ) {
					// prefer the less restrictive CC-BY over CC-BY-SA
					$priority += 100;
				}
				if ( isset( $licenseData['version'] ) ) {
					// prefer newer licenses
					$priority += (int)( 10 * (float)$licenseData['version'] );
				}
			}
		}
		return $priority;
	}

}
