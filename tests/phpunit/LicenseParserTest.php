<?php

namespace CommonsMetadata;

/**
 * @covers \CommonsMetadata\LicenseParser
 * @group Extensions/CommonsMetadata
 */
class LicenseParserTest extends \MediaWikiIntegrationTestCase {
	/** @var LicenseParser */
	protected $licenseParser;

	public function setUp(): void {
		parent::setUp();
		$this->licenseParser = new LicenseParser();
	}

	/**
	 * @dataProvider provideUnrecognizedLicenses
	 */
	public function testUnrecognizedLicenses( ?string $licenseString ): void {
		$data = $this->licenseParser->parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	public static function provideUnrecognizedLicenses(): iterable {
		yield 'null license string' => [ null ];
		yield 'empty license string' => [ '' ];
		yield 'wrong license string' => [ 'foo' ];
		yield 'CC license without version' => [ 'cc-by-sa' ];
		yield 'non-free license (CC-BY-NC-SA 3.0)' => [ 'cc-by-nc-sa-3.0' ];
		yield 'non-free license (CC-BY-ND 2.1)' => [ 'cc-by-nd-2.1' ];
	}

	public function testNormalCCLicense() {
		$licenseString = 'cc-by-sa-1.0';
		$data = $this->licenseParser->parseLicenseString( $licenseString );
		$this->assertLicenseFamilyEquals( 'cc', $data );
		$this->assertLicenseTypeEquals( 'cc-by-sa', $data );
		$this->assertLicenseVersionEquals( '1.0', $data );
		$this->assertLicenseRegionEquals( null, $data );
		$this->assertLicenseNameEquals( 'cc-by-sa-1.0', $data );
	}

	public function testNormalCCLicenseInUppercase() {
		$licenseString = 'CC-BY-SA-1.0';
		$data = $this->licenseParser->parseLicenseString( $licenseString );
		$this->assertLicenseFamilyEquals( 'cc', $data );
		$this->assertLicenseTypeEquals( 'cc-by-sa', $data );
		$this->assertLicenseNameEquals( 'cc-by-sa-1.0', $data );
	}

	public function testNormalCCLicenseWithSpaces() {
		$licenseString = 'CC BY-SA 1.0';
		$data = $this->licenseParser->parseLicenseString( $licenseString );
		$this->assertLicenseFamilyEquals( 'cc', $data );
		$this->assertLicenseTypeEquals( 'cc-by-sa', $data );
		$this->assertLicenseNameEquals( 'cc-by-sa-1.0', $data );
	}

	public function testCCSALicense() {
		$licenseString = 'CC-SA-1.0';
		$data = $this->licenseParser->parseLicenseString( $licenseString );
		$this->assertLicenseFamilyEquals( 'cc', $data );
		$this->assertLicenseTypeEquals( 'cc-sa', $data );
		$this->assertLicenseNameEquals( 'cc-sa-1.0', $data );
	}

	public function testRegionalCCLicense() {
		$licenseString = 'cc-by-2.0-fr';
		$data = $this->licenseParser->parseLicenseString( $licenseString );
		$this->assertLicenseTypeEquals( 'cc-by', $data );
		$this->assertLicenseRegionEquals( 'fr', $data );
		$this->assertLicenseNameEquals( 'cc-by-2.0-fr', $data );
	}

	public function testRegionalCCLicenseWithInvalidRegion() {
		$licenseString = 'cc-by-2.0-foo';
		$data = $this->licenseParser->parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	public function testRegionalCCLicenseWithSpecialRegion() {
		$licenseString = 'cc-by-2.0-scotland';
		$data = $this->licenseParser->parseLicenseString( $licenseString );
		$this->assertLicenseTypeEquals( 'cc-by', $data );
		$this->assertLicenseRegionEquals( 'scotland', $data );
		$this->assertLicenseNameEquals( 'cc-by-2.0-scotland', $data );
	}

	public function testCC0() {
		$licenseString = 'CC0';
		$data = $this->licenseParser->parseLicenseString( $licenseString );
		$this->assertLicenseTypeEquals( 'cc0', $data );
		$this->assertLicenseVersionEquals( null, $data );
		$this->assertLicenseNameEquals( 'cc0', $data );
	}

	public function testPD() {
		$licenseString = 'Public Domain';
		$data = $this->licenseParser->parseLicenseString( $licenseString );
		$this->assertLicenseFamilyEquals( 'pd', $data );
		$this->assertLicenseNameEquals( 'pd', $data );
	}

	/**
	 * @dataProvider provideGetLicensePriorityData
	 * @param array $greaterLicenseData
	 * @param array $smallerLicenseData
	 */
	public function testGetLicensePriority( $greaterLicenseData, $smallerLicenseData ) {
		$this->assertLicenseHasGreaterPriority( $greaterLicenseData, $smallerLicenseData );
	}

	public static function provideGetLicensePriorityData() {
		// phpcs:disable MediaWiki.WhiteSpace.SpaceBeforeSingleLineComment.NewLineComment
		return [
			[ // PD wins over CC
				[ // this should have higher priority...
					'family' => 'pd',
					'type' => null,
					'version' => null,
					'region' => null,
					'name' => 'pd',
				],
				[ // ...than this
					'family' => 'cc',
					'type' => 'cc-by-sa',
					'version' => '2.0',
					'region' => null,
					'name' => 'cc-by-sa-2.0',
				],
			],
			[ // CC wins over unknown
				[ // this should have higher priority...
					'family' => 'cc',
					'type' => 'cc-by-sa',
					'version' => '2.5',
					'region' => null,
					'name' => 'cc-by-sa-2.5',
				],
				[ // ...than this
					'family' => 'gfdl',
					'type' => null,
					'version' => null,
					'region' => null,
					'name' => 'gfdl',
				],
			],
			[ // BY wins over BY-SA
				[ // this should have higher priority...
					'family' => 'cc',
					'type' => 'cc-by',
					'version' => '2.5',
					'region' => null,
					'name' => 'cc-by-sa-2.5',
				],
				[ // ...than this
					'family' => 'cc',
					'type' => 'cc-by-sa',
					'version' => '2.0',
					'region' => null,
					'name' => 'cc-by-sa-2.0',
				],
			],
			[ // higher CC wins
				[ // this should have higher priority...
					'family' => 'cc',
					'type' => 'cc-by-sa',
					'version' => '2.5',
					'region' => null,
					'name' => 'cc-by-sa-2.5',
				],
				[ // ...than this
					'family' => 'cc',
					'type' => 'cc-by-sa',
					'version' => '2.0',
					'region' => null,
					'name' => 'cc-by-sa-2.0',
				],
			],
		];
		// phpcs:enable MediaWiki.WhiteSpace.SpaceBeforeSingleLineComment.NewLineComment
	}

	public function testSortDataByLicensePriority() {
		$licenses = [ 'gfdl', 'public domain', '', 'cc-by-sa-2.0', 'cc-by-2.0',
			'cc-by-sa-3.5', 'cc-by-3.5', 'foobar' ];
		$expectedSortedLicenses = [ 'public domain', 'cc-by-3.5', 'cc-by-2.0', 'cc-by-sa-3.5',
			'cc-by-sa-2.0', 'gfdl', '', 'foobar' ];
		$actualSortedLicenses = $this->licenseParser->sortDataByLicensePriority( $licenses,
			static function ( $v ) {
				return $v;
			}
		);
		$this->assertArrayEquals( $expectedSortedLicenses, $actualSortedLicenses, true );

		// test that array keys are kept
		$licenses = [ 'a' => 'cc-by-2.0', 'b' => 'cc-by-3.5' ];
		$expectedSortedLicenses = [ 'b' => 'cc-by-3.5', 'a' => 'cc-by-2.0' ];
		$actualSortedLicenses = $this->licenseParser->sortDataByLicensePriority( $licenses,
			static function ( $v ) {
				return $v;
			}
		);
		$this->assertArrayEquals( $expectedSortedLicenses, $actualSortedLicenses, true, true );

		// test with the same data structure that's used by the collector
		$licenseData = [
			[
				'UsageTerms' => 'foo',
			],
			[
				'LicenseShortName' => 'cc-by-sa-2.0',
				'UsageTerms' => 'Creative Commons',
			],
			[
				'LicenseShortName' => 'cc-by-sa-4.0',
				'UsageTerms' => 'Creative Commons',
			],
		];
		$expectedSortOrder = [ 2, 1, 0 ];
		$sortedLicenseData = $this->licenseParser->sortDataByLicensePriority( $licenseData,
			static function ( $license ) {
				if ( !isset( $license['LicenseShortName'] ) ) {
					return '';
				}
				return $license['LicenseShortName'];
			}
		);
		$this->assertArrayEquals( $licenseData, $sortedLicenseData, false, true );
		$this->assertArrayEquals( $expectedSortOrder, array_keys( $sortedLicenseData ), true );
	}

	protected function assertLicenseIsRecognized( $licenseData ) {
		$this->assertNotNull( $licenseData );
	}

	protected function assertLicenseIsNotRecognized( $licenseData ) {
		$this->assertNull( $licenseData );
	}

	protected function assertLicenseElementEquals( $expected, $element, $licenseData ) {
		$this->assertIsArray( $licenseData );
		$this->assertArrayHasKey( $element, $licenseData );
		$this->assertEquals( $expected, $licenseData[$element] );
	}

	protected function assertLicenseFamilyEquals( $family, $licenseData ) {
		$this->assertLicenseElementEquals( $family, 'family', $licenseData );
	}

	protected function assertLicenseTypeEquals( $type, $licenseData ) {
		$this->assertLicenseElementEquals( $type, 'type', $licenseData );
	}

	protected function assertLicenseVersionEquals( $version, $licenseData ) {
		$this->assertLicenseElementEquals( $version, 'version', $licenseData );
	}

	protected function assertLicenseRegionEquals( $region, $licenseData ) {
		$this->assertLicenseElementEquals( $region, 'region', $licenseData );
	}

	protected function assertLicenseNameEquals( $name, $licenseData ) {
		$this->assertLicenseElementEquals( $name, 'name', $licenseData );
	}

	protected function assertLicenseHasGreaterPriority( $greaterLicenseData, $smallerLicenseData ) {
		$getLicensePriorityMethod = new \ReflectionMethod(
			$this->licenseParser, 'getLicensePriority' );
		$getLicensePriorityMethod->setAccessible( true );
		$greaterLicensePriority = $getLicensePriorityMethod->invokeArgs(
			$this->licenseParser, [ $greaterLicenseData ] );
		$smallerLicensePriority = $getLicensePriorityMethod->invokeArgs(
			$this->licenseParser, [ $smallerLicenseData ] );
		$this->assertGreaterThan( $smallerLicensePriority, $greaterLicensePriority );
	}
}
