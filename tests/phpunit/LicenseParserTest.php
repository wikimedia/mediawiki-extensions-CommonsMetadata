<?php
/**
 * Created by PhpStorm.
 * User: Gergő
 * Date: 2013.11.12.
 * Time: 16:56
 */

class LicenseParserTest extends MediaWikiTestCase {
	/**
	 * @covers CommonsMetadata::parseLicenseString
	 * @group Extensions/CommonsMetadata
	 */
	public function testEmptyString() {
		$licenseString = '';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	/**
	 * @covers CommonsMetadata::parseLicenseString
	 * @group Extensions/CommonsMetadata
	 */
	public function testTotallyWrongString() {
		$licenseString = 'foo';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	/**
	 * @covers CommonsMetadata::parseLicenseString
	 * @group Extensions/CommonsMetadata
	 */
	public function testCCLicenseWithoutVersion() {
		$licenseString = 'cc-by-sa';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	/**
	 * @covers CommonsMetadata::parseLicenseString
	 * @group Extensions/CommonsMetadata
	 */
	public function testNonFreeLicense() {
		$licenseString = 'cc-by-nc-sa-3.0';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );

		$licenseString = 'cc-by-nd-2.1';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	/**
	 * @covers CommonsMetadata::parseLicenseString
	 * @group Extensions/CommonsMetadata
	 */
	public function testNormalCCLicense() {
		$licenseString = 'cc-by-sa-1.0';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
		$this->assertLicenseFamilyEquals( 'cc', $data );
		$this->assertLicenseTypeEquals( 'cc-by-sa', $data );
		$this->assertLicenseVersionEquals( '1.0', $data );
		$this->assertLicenseRegionEquals( null, $data );
		$this->assertLicenseNameEquals( 'cc-by-sa-1.0', $data );
	}

	/**
	 * @covers CommonsMetadata::parseLicenseString
	 * @group Extensions/CommonsMetadata
	 */
	public function testNormalCCLicenseInUppercase() {
		$licenseString = 'CC-BY-SA-1.0';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
		$this->assertLicenseFamilyEquals( 'cc', $data );
		$this->assertLicenseTypeEquals( 'cc-by-sa', $data );
		$this->assertLicenseNameEquals( 'cc-by-sa-1.0', $data );
	}

	/**
	 * @covers CommonsMetadata::parseLicenseString
	 * @group Extensions/CommonsMetadata
	 */
	public function testCCSALicense() {
		$licenseString = 'CC-SA-1.0';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
		$this->assertLicenseFamilyEquals( 'cc', $data );
		$this->assertLicenseTypeEquals( 'cc-sa', $data );
		$this->assertLicenseNameEquals( 'cc-sa-1.0', $data );
	}

	/**
	 * @covers CommonsMetadata::parseLicenseString
	 * @group Extensions/CommonsMetadata
	 */
	public function testRegionalCCLicense() {
		$licenseString = 'cc-by-2.0-fr';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
		$this->assertLicenseTypeEquals( 'cc-by', $data );
		$this->assertLicenseRegionEquals( 'fr', $data );
		$this->assertLicenseNameEquals( 'cc-by-2.0-fr', $data );
	}

	/**
	 * @covers CommonsMetadata::parseLicenseString
	 * @group Extensions/CommonsMetadata
	 */
	public function testRegionalCCLicenseWithInvalidRegion() {
		$licenseString = 'cc-by-2.0-foo';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
		$this->assertLicenseIsNotRecognized( $data );
	}

	/**
	 * @covers CommonsMetadata::parseLicenseString
	 * @group Extensions/CommonsMetadata
	 */
	public function testRegionalCCLicenseWithSpecialRegion() {
		$licenseString = 'cc-by-2.0-scotland';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
		$this->assertLicenseTypeEquals( 'cc-by', $data );
		$this->assertLicenseRegionEquals( 'scotland', $data );
		$this->assertLicenseNameEquals( 'cc-by-2.0-scotland', $data );
	}

	public function testCC0() {
		$licenseString = 'CC0';
		$data = CommonsMetadata::parseLicenseString( $licenseString );
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
