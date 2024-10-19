<?php

namespace CommonsMetadata;

use File;
use MediaWiki\Language\Language;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group Database
 * @covers \CommonsMetadata\DataCollector
 * @group Extensions/CommonsMetadata
 */
class DataCollectorTest extends \MediaWikiIntegrationTestCase {
	/** @var MockObject */
	protected $templateParser;

	/** @var MockObject */
	protected $licenseParser;

	/** @var DataCollector */
	protected $dataCollector;

	/** @var MockObject */
	protected $file;

	public function setUp(): void {
		parent::setUp();

		$language = $this->createMock( Language::class );

		$this->templateParser = $this->createMock( \CommonsMetadata\TemplateParser::class );
		$this->licenseParser = $this->createMock( \CommonsMetadata\LicenseParser::class );
		$this->licenseParser->expects( $this->any() )
			->method( 'sortDataByLicensePriority' )
			->willReturnArgument( 0 );
		$this->file = $this->createMock( File::class );

		$this->dataCollector = new DataCollector();
		$this->dataCollector->setLanguage( $language );
		$this->dataCollector->setTemplateParser( $this->templateParser );
		$this->dataCollector->setLicenseParser( $this->licenseParser );
	}

	/*------------------------------- Format tests --------------------------*/

	public function testEmptyMetadata() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->willReturn( [
				TemplateParser::COORDINATES_KEY => [],
				TemplateParser::INFORMATION_FIELDS_KEY => [],
				TemplateParser::LICENSES_KEY => [],
				TemplateParser::DELETION_KEY => [],
				TemplateParser::RESTRICTIONS_KEY => [],
			] );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->willReturn( null );
		$metadata = [];

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'Categories', '', $metadata );
		$this->assertMetadataValue( 'Assessments', '', $metadata );
	}

	public function testNoMetadata() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->willReturn( [] );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->willReturn( null );
		$metadata = [];

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'Categories', '', $metadata );
		$this->assertMetadataValue( 'Assessments', '', $metadata );
	}

	public function testMissingMetadata() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->willReturn( null );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->willReturn( null );
		$metadata = [];

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'Categories', '', $metadata );
		$this->assertMetadataValue( 'Assessments', '', $metadata );
	}

	public function testTemplateMetadataFormatForSingleValuedProperty() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->willReturn( [
				TemplateParser::LICENSES_KEY => [
					[ 'UsageTerms' => 'foo' ],
				],
			] );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->willReturn( null );
		$metadata = [];

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'UsageTerms', 'foo', $metadata );
	}

	public function testTemplateMetadataFormatForMultiValuedProperty() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->willReturn( [
				TemplateParser::LICENSES_KEY => [
					[ 'UsageTerms' => 'foo' ],
				],
			] );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->willReturn( null );
		$metadata = [];

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'UsageTerms', 'foo', $metadata );
	}

	public function testMetadataTimestampNormalization() {
		$metadata = [ 'DateTime' => [ 'value' => '2014:12:08 16:04:26' ],
			'DateTimeOriginal' => [ 'value' => '2014:12:08 16:04:26' ] ];

		$this->dataCollector->collect( $metadata, $this->file );

		$this->assertMetadataValue( 'DateTime', '2014-12-08 16:04:26', $metadata );
		$this->assertMetadataValue( 'DateTimeOriginal', '2014-12-08 16:04:26', $metadata );
	}

	public function testFilePageAbsoluteUrls() {
		$this->overrideConfigValue( MainConfigNames::Server, '//TESTING_SERVER' );
		$this->editPage( __METHOD__, '[[Test]]', '', NS_FILE );
		$file = new \LocalFile(
			Title::makeTitle( NS_FILE, __METHOD__ ),
			$this->getServiceContainer()->getRepoGroup()->getLocalRepo()
		);

		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->willReturnCallback( function ( string $html ) {
				$this->assertStringContainsString( '//TESTING_SERVER/', $html );
			} );

		$metadata = [];
		$this->dataCollector->collect( $metadata, $file );
	}

	/*------------------------------- Logic tests --------------------------*/

	public function testGetCategoryMetadata() {
		$getCategoryMetadataMethod = new \ReflectionMethod(
			$this->dataCollector, 'getCategoryMetadata' );
		$getCategoryMetadataMethod->setAccessible( true );

		$categories = [ 'Foo', 'Bar',
			'Pictures of the year (2012)', 'Pictures of the day (2012)', 'CC-BY-SA-2.0' ];

		$this->licenseParser->expects( $this->any() )
			->method( 'parseLicenseString' )
			->willReturnMap( [
				[ 'CC-BY-SA-2.0', [
					'family' => 'cc',
					'type' => 'cc-by-sa',
					'version' => 2.0,
					'region' => null,
					'name' => 'cc-by-sa-2.0',
				] ],
			] );

		$categoryData = $getCategoryMetadataMethod->invokeArgs(
			$this->dataCollector, [ $categories ] );

		$this->assertMetadataValue( 'Categories', 'Foo|Bar', $categoryData );
		$this->assertMetadataValue( 'Assessments', 'poty|potd', $categoryData );
	}

	public function testGetTemplateMetadata() {
		$getTemplateMetadataMethod = new \ReflectionMethod(
			$this->dataCollector, 'getTemplateMetadata' );
		$getTemplateMetadataMethod->setAccessible( true );

		$this->licenseParser->expects( $this->any() )
			->method( 'parseLicenseString' )
			->willReturnMap( [
				[ 'quux', [
					'family' => 'quux.family',
					'name' => 'quux.name',
				] ],
			] );

		$templateData = $getTemplateMetadataMethod->invokeArgs( $this->dataCollector, [ [
			TemplateParser::COORDINATES_KEY => [ [ 'Foo' => 'bar' ] ],
			TemplateParser::INFORMATION_FIELDS_KEY => [ [ 'Baz' => 'boom' ] ],
			TemplateParser::LICENSES_KEY => [ [ 'LicenseShortName' => 'quux' ] ],
			TemplateParser::DELETION_KEY => [ [ 'DeletionReason' => 'quuux' ] ],
		] ] );

		$this->assertMetadataValue( 'Foo', 'bar', $templateData );
		$this->assertMetadataValue( 'Baz', 'boom', $templateData );
		$this->assertMetadataValue( 'LicenseShortName', 'quux', $templateData );
		$this->assertMetadataValue( 'License', 'quux.name', $templateData );
		$this->assertMetadataValue( 'DeletionReason', 'quuux', $templateData );
	}

	public function testGetTemplateMetadataForMultipleInfoTemplates() {
		$getTemplateMetadataMethod = new \ReflectionMethod(
			$this->dataCollector, 'getTemplateMetadata' );
		$getTemplateMetadataMethod->setAccessible( true );

		$template1 = [ 'Artist' => 'a1', 'Foo' => 'x' ];
		$template2 = [ 'Artist' => 'a2', 'Bar' => 'y' ];
		$templateData = $getTemplateMetadataMethod->invokeArgs( $this->dataCollector, [ [
			TemplateParser::INFORMATION_FIELDS_KEY => [ $template1, $template2 ],
		] ] );

		$this->assertMetadataValue( 'Artist', 'a1', $templateData );
		$this->assertMetadataValue( 'Foo', 'x', $templateData );
		$this->assertArrayNotHasKey( 'Bar', $templateData );
		$this->assertMetadataValue( 'AuthorCount', 2, $templateData );
	}

	public function testNonfreeFlag() {
		// T131896 - NonFree flag cannot be overwritten
		$getTemplateMetadataMethod = new \ReflectionMethod( $this->dataCollector, 'getTemplateMetadata' );
		$getTemplateMetadataMethod->setAccessible( true );

		$template1 = [ 'LicenseShortName' => 'Fair Use' ];
		$template2 = [ 'LicenseShortName' => 'Fair Use', 'NonFree' => '1' ];

		$templateData = $getTemplateMetadataMethod->invokeArgs( $this->dataCollector, [ [
			TemplateParser::LICENSES_KEY => [ $template1 ],
		] ] );
		$this->assertArrayNotHasKey( 'NonFree', $templateData );

		$templateData = $getTemplateMetadataMethod->invokeArgs( $this->dataCollector, [ [
			TemplateParser::LICENSES_KEY => [ $template1, $template2 ],
		] ] );
		$this->assertMetadataValue( 'NonFree', '1', $templateData );

		$templateData = $getTemplateMetadataMethod->invokeArgs( $this->dataCollector, [ [
			TemplateParser::LICENSES_KEY => [ $template2, $template1 ],
		] ] );
		$this->assertMetadataValue( 'NonFree', '1', $templateData );
	}

	/*-------------------- verifyAttributionMetadata tests -------------*/

	public function testVerifyAttributionMetadata() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->willReturn( [
				TemplateParser::INFORMATION_FIELDS_KEY => [ [
					'ImageDescription' => 'blah',
					'Artist' => 'blah blah',
					'Credit' => 'blah blah blah',
				] ],
				TemplateParser::LICENSES_KEY => [ [ 'LicenseShortName' => 'quux' ] ],
			] );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->willReturn( null );

		$problems = $this->dataCollector->verifyAttributionMetadata( new ParserOutput(), $this->file );
		$this->assertSame( [], $problems );
	}

	public function testVerifyAttributionMetadataWithAttribution() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->willReturn( [
				TemplateParser::INFORMATION_FIELDS_KEY => [ [
					'ImageDescription' => 'blah',
					'Attribution' => 'blah blah',
				] ],
				TemplateParser::LICENSES_KEY => [ [ 'LicenseShortName' => 'quux' ] ],
			] );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->willReturn( null );

		$problems = $this->dataCollector->verifyAttributionMetadata( new ParserOutput(), $this->file );
		$this->assertSame( [], $problems );
	}

	public function testVerifyWithEmptyMetadata() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->willReturn( [
				TemplateParser::COORDINATES_KEY => [],
				TemplateParser::INFORMATION_FIELDS_KEY => [],
				TemplateParser::LICENSES_KEY => [],
				TemplateParser::DELETION_KEY => [],
			] );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->willReturn( null );

		$problems = $this->dataCollector->verifyAttributionMetadata( new ParserOutput(), $this->file );

		$this->assertContains( 'no-license', $problems );
		$this->assertContains( 'no-description', $problems );
		$this->assertContains( 'no-author', $problems );
		$this->assertContains( 'no-source', $problems );
	}

	public function testVerifyWithNoMetadata() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->willReturn( [] );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->willReturn( null );

		$problems = $this->dataCollector->verifyAttributionMetadata( new ParserOutput(), $this->file );

		$this->assertContains( 'no-license', $problems );
		$this->assertContains( 'no-description', $problems );
		$this->assertContains( 'no-author', $problems );
		$this->assertContains( 'no-source', $problems );
	}

	public function testVerifyWithMissingMetadata() {
		$this->templateParser->expects( $this->once() )->method( 'parsePage' )
			->willReturn( null );
		$this->licenseParser->expects( $this->any() )->method( 'parseLicenseString' )
			->willReturn( null );

		$problems = $this->dataCollector->verifyAttributionMetadata( new ParserOutput(), $this->file );

		$this->assertContains( 'no-license', $problems );
		$this->assertContains( 'no-description', $problems );
		$this->assertContains( 'no-author', $problems );
		$this->assertContains( 'no-source', $problems );
	}

	public function testVerifyPatentProvided() {
		$title = Title::newFromText( '3dpatent', NS_TEMPLATE );
		$parserOutput = new ParserOutput();
		$parserOutput->addTemplate( $title, 1, 1 );

		$parserTestHelper = new ParserTestHelper();
		$parserTestHelper->setTestCase( $this );
		$file = $parserTestHelper->getLocalFile( '3D file with patent template', [], 'application/sla' );

		$problems = $this->dataCollector->verifyAttributionMetadata( $parserOutput, $file );

		$this->assertNotContains( 'no-patent', $problems );
	}

	public function testVerifyPatentMissing() {
		$parserTestHelper = new ParserTestHelper();
		$parserTestHelper->setTestCase( $this );
		$file = $parserTestHelper->getLocalFile( '3D file w/o patent template', [], 'application/sla' );

		$problems = $this->dataCollector->verifyAttributionMetadata( new ParserOutput(), $file );

		$this->assertContains( 'no-patent', $problems );
	}

	public function testVerifyNoPatentNeeded() {
		$parserTestHelper = new ParserTestHelper();
		$parserTestHelper->setTestCase( $this );
		$file = $parserTestHelper->getLocalFile( 'Not 3D = no patent needed', [], 'image/jpeg' );

		$problems = $this->dataCollector->verifyAttributionMetadata( new ParserOutput(), $file );

		$this->assertNotContains( 'no-patent', $problems );
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
