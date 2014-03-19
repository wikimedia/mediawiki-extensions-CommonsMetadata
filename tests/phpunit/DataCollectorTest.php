<?php

use CommonsMetadata\DataCollector;

/**
 * @covers DataCollector
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
		$this->file = $this->getMock( 'File', array(), array(), '', false /* do not call constructor */ );

		$this->dataCollector = new DataCollector();
		$this->dataCollector->setLanguage( $language );
		$this->dataCollector->setTemplateParser( $this->templateParser );
		$this->dataCollector->setLicenseParser( $this->licenseParser );
	}

	public function testNoMetadata() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->will( $this->returnValue( array() ) );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->will( $this->returnValue( null ) );
		$metadata = array();

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'Categories', '', $metadata );
		$this->assertMetadataValue( 'Assessments', '', $metadata );
	}

	public function testTemplateMetadataFormatForSingleValuedProperty() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->will( $this->returnValue( array( 'UsageTerms' => 'foo' ) ) );
		$this->templateParser->expects( $this->once() )->method( 'getMultivaluedProperties' )
			->will( $this->returnValue( array() ) );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->will( $this->returnValue( null ) );
		$metadata = array();

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'UsageTerms', 'foo', $metadata );
	}

	public function testTemplateMetadataFormatForMultiValuedProperty() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->will( $this->returnValue( array( 'UsageTerms' => array( 'foo' ) ) ) );
		$this->templateParser->expects( $this->once() )->method( 'getMultivaluedProperties' )
			->will( $this->returnValue( array( 'UsageTerms' ) ) );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->will( $this->returnValue( null ) );
		$metadata = array();

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'UsageTerms', 'foo', $metadata );
	}

	protected function assertMetadataValue( $field, $expected, $metadata, $message = '' ) {
		$this->assertArrayHasKey( $field, $metadata,
			$message ?: "Failed to assert that field $field exists" );
		$this->assertArrayHasKey( 'value', $metadata[$field],
			$message ?: "Failed to assert that 'value' key exists for field $field" );
		$actual = $metadata[$field]['value'];
		$this->assertEquals( $expected, $actual,
			$message ?: "Failed to assert that the actual value >>>$actual<<< for field $field "
			. "equals the expected value $expected" );
	}
}
