<?php

use CommonsMetadata\DataCollector;

/**
 * @covers DataCollector::parseLicenseString
 * @group Extensions/CommonsMetadata
 */
class LicenseParserTest extends MediaWikiTestCase {
	/** @var DataCollector */
	protected $dataCollector;

	public function setUp() {
		parent::setUp();
		$this->dataCollector = new DataCollector();
	}

	public function testEmptyString() {
		$licenseString = '';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	public function testTotallyWrongString() {
		$licenseString = 'foo';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	public function testCCLicenseWithoutVersion() {
		$licenseString = 'cc-by-sa';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	public function testNonFreeLicense() {
		$licenseString = 'cc-by-nc-sa-3.0';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );

		$licenseString = 'cc-by-nd-2.1';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	public function testNormalCCLicense() {
		$licenseString = 'cc-by-sa-1.0';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseFamilyEquals( 'cc', $data );
		$this->assertLicenseTypeEquals( 'cc-by-sa', $data );
		$this->assertLicenseVersionEquals( '1.0', $data );
		$this->assertLicenseRegionEquals( null, $data );
		$this->assertLicenseNameEquals( 'cc-by-sa-1.0', $data );
	}

	public function testNormalCCLicenseInUppercase() {
		$licenseString = 'CC-BY-SA-1.0';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseFamilyEquals( 'cc', $data );
		$this->assertLicenseTypeEquals( 'cc-by-sa', $data );
		$this->assertLicenseNameEquals( 'cc-by-sa-1.0', $data );
	}

	public function testCCSALicense() {
		$licenseString = 'CC-SA-1.0';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseFamilyEquals( 'cc', $data );
		$this->assertLicenseTypeEquals( 'cc-sa', $data );
		$this->assertLicenseNameEquals( 'cc-sa-1.0', $data );
	}

	public function testRegionalCCLicense() {
		$licenseString = 'cc-by-2.0-fr';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseTypeEquals( 'cc-by', $data );
		$this->assertLicenseRegionEquals( 'fr', $data );
		$this->assertLicenseNameEquals( 'cc-by-2.0-fr', $data );
	}

	public function testRegionalCCLicenseWithInvalidRegion() {
		$licenseString = 'cc-by-2.0-foo';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	public function testRegionalCCLicenseWithSpecialRegion() {
		$licenseString = 'cc-by-2.0-scotland';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseTypeEquals( 'cc-by', $data );
		$this->assertLicenseRegionEquals( 'scotland', $data );
		$this->assertLicenseNameEquals( 'cc-by-2.0-scotland', $data );
	}

	public function testCC0() {
		$licenseString = 'CC0';
		$data = $this->dataCollector->parseLicenseString( $licenseString );
		$this->assertLicenseTypeEquals( 'cc0', $data );
		$this->assertLicenseVersionEquals( null, $data );
		$this->assertLicenseNameEquals( 'cc0', $data );
	}

	/**********************************************************************/

	protected function assertLicenseIsRecognized( $licenseData ) {
		$this->assertNotNull( $licenseData );
	}

	protected function assertLicenseIsNotRecognized( $licenseData ) {
		$this->assertNull( $licenseData );
	}

	protected function assertLicenseElementEquals( $expected, $element, $licenseData ) {
		$this->assertInternalType( 'array', $licenseData );
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
}
