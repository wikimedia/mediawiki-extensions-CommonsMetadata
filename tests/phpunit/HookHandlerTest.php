<?php

namespace CommonsMetadata;

use CommonsMetadata\Hooks\SkinAfterBottomScriptsHandler;
use File;
use LocalRepo;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/ParserTestHelper.php";

/**
 * @covers \CommonsMetadata\HookHandler
 * @group Extensions/CommonsMetadata
 */
class HookHandlerTest extends TestCase {
	/** @var ParserTestHelper */
	protected $parserTestHelper;

	public function setUp(): void {
		$this->parserTestHelper = new ParserTestHelper();
		$this->parserTestHelper->setTestCase( $this );
	}

	public function testLocalFile() {
		$description = 'foo';
		$categories = [ 'Bar', 'Baz' ];

		$metadata = [ 'OldKey' => 'OldValue',
			'Categories' => [ 'value' => 'I_will_be_overwritten' ] ];
		$maxCache = 3600;
		$file = $this->parserTestHelper->getLocalFile( $description, $categories );
		$context = $this->parserTestHelper->getContext( 'en' );

		HookHandler::onGetExtendedMetadata( $metadata, $file, $context, true, $maxCache );

		// cache interval was not changed
		$this->assertEquals( 3600, $maxCache );

		// metdata from other sources is kept but overwritten on conflict
		$this->assertArrayHasKey( 'OldKey', $metadata );
		$this->assertEquals( 'OldValue', $metadata['OldKey'] );
		$this->assertMetadataFieldEquals( 'Bar|Baz', 'Categories', $metadata );
	}

	public function testForeignApiFile() {
		$description = 'foo';

		$metadata = [ 'OldKey' => 'OldValue',
			'Categories' => [ 'value' => 'I_will_remain' ] ];
		$maxCache = 3600;
		$file = $this->parserTestHelper->getForeignApiFile( $description );
		$context = $this->parserTestHelper->getContext( 'en' );

		HookHandler::onGetExtendedMetadata( $metadata, $file, $context, true, $maxCache );

		// cache interval was not changed
		$this->assertEquals( 3600, $maxCache );

		// metdata from other sources is kept but overwritten on conflict
		$this->assertArrayHasKey( 'OldKey', $metadata );
		$this->assertEquals( 'OldValue', $metadata['OldKey'] );
		$this->assertMetadataFieldEquals( 'I_will_remain', 'Categories', $metadata );
	}

	public function testForeignDBFile() {
		$description = 'foo';
		$categories = [ 'Bar', 'Baz' ];

		$metadata = [ 'OldKey' => 'OldValue',
			'Categories' => [ 'value' => 'I_will_be_overwritten' ] ];
		$maxCache = 3600;
		$file = $this->parserTestHelper->getForeignDbFile( $description, $categories );
		$context = $this->parserTestHelper->getContext( 'en' );

		HookHandler::onGetExtendedMetadata( $metadata, $file, $context, true, $maxCache );

		// cache interval is 12 hours for all remote files
		$this->assertEquals( 3600 * 12, $maxCache );

		// metdata from other sources is kept but overwritten on conflict
		$this->assertArrayHasKey( 'OldKey', $metadata );
		$this->assertEquals( 'OldValue', $metadata['OldKey'] );
		$this->assertMetadataFieldEquals( 'Bar|Baz', 'Categories', $metadata );
	}

	/*----------------------------------------------------------*/

	/**
	 * @dataProvider provideDescriptionData
	 * @param string $testName a test name from ParserTestHelper::$testHTMLFiles
	 */
	public function testDescription( $testName ) {
		$maxCache = 3600;
		$actualMetadata = [];
		$description = $this->parserTestHelper->getTestHTML( $testName );
		$file = $this->parserTestHelper->getLocalFile( $description, [] );
		$context = $this->parserTestHelper->getContext( 'en' );

		HookHandler::onGetExtendedMetadata( $actualMetadata, $file, $context, true, $maxCache );

		$expectedMetadata = $this->parserTestHelper->getMetadata( $testName );
		foreach ( $expectedMetadata as $key => $val ) {
			$this->assertArrayHasKey( $key, $actualMetadata, "Field $key missing from metadata" );
			$this->assertEquals( $expectedMetadata[$key], $actualMetadata[$key],
				"Value for field $key does not match" );
		}
	}

	public static function provideDescriptionData() {
		return [
			[ 'noinfo' ],
			[ 'simple' ],
			[ 'singlelang' ],
		];
	}

	/*----------------------------------------------------------*/

	/**
	 * @param mixed $expected metadata field value
	 * @param string $field metadata field name
	 * @param array $metadata metadata array as returned by GetExtendedMetadata hook
	 */
	protected function assertMetadataFieldEquals( $expected, $field, $metadata ) {
		$this->assertArrayHasKey( $field, $metadata );
		$this->assertArrayHasKey( 'value', $metadata[$field] );
		$this->assertEquals( $expected, $metadata[$field]['value'] );
	}

	/*----------------------------------------------------------*/

	public function testDoSkinAfterBottomScripts() {
		$url = 'https://commons.wikimedia.org/image/0/0f/Schema_test.jpg';
		$title = $this->getMockTitle( $url );
		$localRepo = $this->getMockLocalRepo( $title );

		$handler = $this->createMock( SkinAfterBottomScriptsHandler::class );
		$handler->expects( $this->once() )
			->method( 'getSchemaElement' )
			->with( $title, $localRepo->newFile( $title ) )
			->will( $this->returnValue( 'Script with URL: ' . $localRepo->newFile( $title )->getFullUrl() ) );

		$expected = 'Script with URL: ' . $url;
		$hooksObject = new HookHandler();
		$actual = $hooksObject->doSkinAfterBottomScripts( $localRepo, $handler, $title );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * @param Title $title
	 * @return LocalRepo
	 */
	private function getMockLocalRepo( $title ) {
		$file = $this->createMock( File::class );
		$file->expects( $this->any() )
			->method( 'getFullUrl' )
			->will( $this->returnValue( $title->getFullUrl() ) );

		$localRepo = $this->createMock( LocalRepo::class );
		$localRepo->expects( $this->any() )
			->method( 'newFile' )
			->will( $this->returnValue( $file ) );

		return $localRepo;
	}

	/**
	 * @param string $fullUrl
	 * @return Title
	 */
	private function getMockTitle( $fullUrl ) {
		$mock = $this->createMock( Title::class );
		$mock->expects( $this->any() )
			->method( 'getFullUrl' )
			->will( $this->returnValue( $fullUrl ) );
		return $mock;
	}
}
