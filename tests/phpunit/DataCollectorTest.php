<?php

use CommonsMetadata\DataCollector;
use CommonsMetadata\TemplateParser;

/**
 * @covers CommonsMetadata\DataCollector
 * @group Extensions/CommonsMetadata
 */
class DataCollectorTest extends MediaWikiTestCase {
	/** @var PHPUnit_Framework_MockObject_MockObject */
	protected $templateParser;

	/** @var PHPUnit_Framework_MockObject_MockObject */
	protected $licenseParser;

	/** @var DataCollector */
	protected $dataCollector;

	/** @var PHPUnit_Framework_MockObject_MockObject */
	protected $file;

	public function setUp() {
		parent::setUp();

		$language = $this->getMock( 'Language', array(), array(), '', false /* do not call constructor */ );

		$this->templateParser = $this->getMock( 'CommonsMetadata\TemplateParser' );
		$this->licenseParser = $this->getMock( 'CommonsMetadata\LicenseParser' );
		$this->licenseParser->expects( $this->any() )
			->method( 'sortDataByLicensePriority' )
			->will( $this->returnArgument( 0 ) );
		$this->file = $this->getMock( 'File', array(), array(), '', false /* do not call constructor */ );

		$this->dataCollector = new DataCollector();
		$this->dataCollector->setLanguage( $language );
		$this->dataCollector->setTemplateParser( $this->templateParser );
		$this->dataCollector->setLicenseParser( $this->licenseParser );
	}

	/*------------------------------- Format tests --------------------------*/

	public function testNoMetadata() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->will( $this->returnValue( array(
				TemplateParser::COORDINATES_KEY => array(),
				TemplateParser::INFORMATION_FIELDS_KEY => array(),
				TemplateParser::LICENSES_KEY => array(),
			) ) );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->will( $this->returnValue( null ) );
		$metadata = array();

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'Categories', '', $metadata );
		$this->assertMetadataValue( 'Assessments', '', $metadata );
	}

	public function testTemplateMetadataFormatForSingleValuedProperty() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->will( $this->returnValue( array(
				TemplateParser::COORDINATES_KEY => array(),
				TemplateParser::INFORMATION_FIELDS_KEY => array(),
				TemplateParser::LICENSES_KEY => array(
					array( 'UsageTerms' => 'foo' ),
				),
			) ) );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->will( $this->returnValue( null ) );
		$metadata = array();

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'UsageTerms', 'foo', $metadata );
	}

	public function testTemplateMetadataFormatForMultiValuedProperty() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->will( $this->returnValue( array(
				TemplateParser::COORDINATES_KEY => array(),
				TemplateParser::INFORMATION_FIELDS_KEY => array(),
				TemplateParser::LICENSES_KEY => array(
					array( 'UsageTerms' => 'foo' ),
				),
			) ) );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->will( $this->returnValue( null ) );
		$metadata = array();

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'UsageTerms', 'foo', $metadata );
	}

	/*------------------------------- Logic tests --------------------------*/

	public function testGetCategoryMetadata() {
		$getCategoryMetadataMethod = new ReflectionMethod( $this->dataCollector, 'getCategoryMetadata' );
		$getCategoryMetadataMethod->setAccessible( true );

		$categories = array( 'Foo', 'Bar', 'Pictures of the year (2012)', 'Pictures of the day (2012)', 'CC-BY-SA-2.0' );

		$this->licenseParser->expects( $this->any() )
			->method( 'parseLicenseString' )
			->will( $this->returnValueMap( array(
				array( 'CC-BY-SA-2.0', array(
					'family' => 'cc',
					'type' => 'cc-by-sa',
					'version' => 2.0,
					'region' => null,
					'name' => 'cc-by-sa-2.0',
				) ),
			) ) );

		$categoryData = $getCategoryMetadataMethod->invokeArgs( $this->dataCollector, array( $categories ) );

		$this->assertMetadataValue( 'Categories', 'Foo|Bar', $categoryData );
		$this->assertMetadataValue( 'Assessments', 'poty|potd', $categoryData );
	}

	public function testGetTemplateMetadata() {
		$getTemplateMetadataMethod = new ReflectionMethod( $this->dataCollector, 'getTemplateMetadata' );
		$getTemplateMetadataMethod->setAccessible( true );

		$this->licenseParser->expects( $this->any() )
			->method( 'parseLicenseString' )
			->will( $this->returnValueMap( array(
				array( 'quux', array(
					'family' => 'quux.family',
					'name' => 'quux.name',
				) ),
			) ) );

		$templateData = $getTemplateMetadataMethod->invokeArgs( $this->dataCollector, array( array(
			TemplateParser::COORDINATES_KEY => array( array( 'Foo' => 'bar' ) ),
			TemplateParser::INFORMATION_FIELDS_KEY => array( array( 'Baz' => 'boom' ) ),
			TemplateParser::LICENSES_KEY => array( array( 'LicenseShortName' => 'quux' ) ),
		) ) );

		$this->assertMetadataValue( 'Foo', 'bar', $templateData );
		$this->assertMetadataValue( 'Baz', 'boom', $templateData );
		$this->assertMetadataValue( 'LicenseShortName', 'quux', $templateData );
		$this->assertMetadataValue( 'License', 'quux.name', $templateData );
	}

	/*------------------------------- Helpers --------------------------*/

	protected function assertMetadataValue( $field, $expected, $metadata, $message = '' ) {
		$this->assertArrayHasKey( $field, $metadata,
			$message ?: "Failed to assert that field $field exists" );
		$this->assertArrayHasKey( 'value', $metadata[$field],
			$message ?: "Failed to assert that 'value' key exists for field $field" );
		$actual = $metadata[$field]['value'];
		$this->assertEquals( $expected, $actual,
			$message ?: "Failed to assert that the actual value \"$actual\" for field $field "
			. "equals the expected value \"$expected\"" );
	}
}
