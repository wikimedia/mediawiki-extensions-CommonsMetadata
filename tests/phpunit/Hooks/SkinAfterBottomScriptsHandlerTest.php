<?php

namespace CommonsMetadata\Hooks;

use FormatMetadata;
use MediaWiki\FileRepo\File\File;
use MediaWiki\Title\Title;

/**
 * @covers \CommonsMetadata\Hooks\SkinAfterBottomScriptsHandler
 * @group Extensions/CommonsMetadata
 */
class SkinAfterBottomScriptsHandlerTest extends \MediaWikiIntegrationTestCase {
	/** @var string */
	private $publicDomainUrl;

	protected function setUp(): void {
		parent::setUp();
		$this->publicDomainUrl = 'https://commons.wikimedia.org/wiki/Help:Public_domain';
	}

	/**
	 * @dataProvider provideImageWithLicenseData
	 */
	public function testGetSchemaElementWithValidFile( $metadata, $expectedSchema ) {
		$handler = new SkinAfterBottomScriptsHandler(
			$this->getMockFormat( $metadata ),
			$this->publicDomainUrl
		);
		$title = $this->getMockTitle();
		$file = $this->getMockFile( true, MEDIATYPE_BITMAP );

		$result = $handler->getSchemaElement( $title, $file );
		$actualSchema = json_decode( strip_tags( $result ), true );
		$this->assertEquals( $expectedSchema, $actualSchema );
	}

	public static function provideImageWithLicenseData() {
		$metadata = [
			'LicenseUrl' => [
				'value' => 'https://creativecommons.org/licenses/by-sa/4.0',
				'source' => 'commons-desc-page',
				'hidden' => ''
			],
			'License' => [
				'value' => 'cc-by-sa-4.0',
				'source' => 'commons-templates',
				'hidden' => ''
			],
			'DateTime' => [
				'value' => '2020-05-06 22:04:01',
				'source' => 'mediawiki-metadata',
				'hidden' => ''
			]
		];
		$schema = [
			'@context' => 'https://schema.org',
			'@type' => 'ImageObject',
			'contentUrl' => 'https://commons.wikimedia.org/image/0/0f/Schema_test.jpg',
			'license' => 'https://creativecommons.org/licenses/by-sa/4.0',
			'acquireLicensePage' => 'https://commons.wikimedia.org/wiki/File:Schema_test.jpg',
			'uploadDate' => '2020-05-06 22:04:01'
		];

		return [
			'Image with license' => [ $metadata, $schema ]
		];
	}

	/**
	 * @dataProvider provideInvalidFiles
	 */
	public function testGetSchemaElementWithInvalidFiles( $mockExists, $mockMediaType ) {
		$file = $mockExists === null ? null : $this->getMockFile( $mockExists, $mockMediaType );
		// We'll set up the mock format's fetchExtendedMetadata method to return
		// an empty array so we can test the scenario of a valid file that gets
		// back no extended metadata.
		$handler = new SkinAfterBottomScriptsHandler(
			$this->getMockFormat( [] ),
			$this->publicDomainUrl
		);
		$title = $this->getMockTitle();
		$result = $handler->getSchemaElement( $title, $file );
		$this->assertSame( '', $result );
	}

	public static function provideInvalidFiles() {
		return [
			'Null value' => [ null, null ],
			'Nonexistent file' => [ false, null ],
			'Wrong media type' => [ true, MEDIATYPE_AUDIO ],
			'No extended metadata' => [ true, MEDIATYPE_BITMAP ]
		];
	}

	/**
	 * @dataProvider provideImageWithLicenseData
	 * @dataProvider providePublicDomainImageData
	 * @dataProvider provideImageWithMissingUploadDateData
	 */
	public function testGetSchema( $extendedMetadata, $expected ) {
		$handler = new SkinAfterBottomScriptsHandler(
			$this->getMockFormat(),
			$this->publicDomainUrl
		);
		$title = $this->getMockTitle();
		$file = $this->getMockFile( true, MEDIATYPE_BITMAP );

		$actual = $handler->getSchema( $title, $file, $extendedMetadata );
		$this->assertEquals( $expected, $actual );
	}

	public static function providePublicDomainImageData() {
		$metadata = [
			'License' => [
				'value' => 'pd',
				'source' => 'commons-templates',
				'hidden' => ''
			],
			'DateTime' => [
				'value' => '2020-05-06 22:04:01',
				'source' => 'mediawiki-metadata',
				'hidden' => ''
			]
		];
		$schema = [
			'@context' => 'https://schema.org',
			'@type' => 'ImageObject',
			'contentUrl' => 'https://commons.wikimedia.org/image/0/0f/Schema_test.jpg',
			'license' => 'https://commons.wikimedia.org/wiki/Help:Public_domain',
			'acquireLicensePage' => 'https://commons.wikimedia.org/wiki/File:Schema_test.jpg',
			'uploadDate' => '2020-05-06 22:04:01'
		];

		return [
			'Public domain image' => [ $metadata, $schema ]
		];
	}

	public static function provideImageWithMissingUploadDateData() {
		$metadata = [
			'LicenseUrl' => [
				'value' => 'https://creativecommons.org/licenses/by-sa/4.0',
				'source' => 'commons-desc-page',
				'hidden' => ''
			],
			'License' => [
				'value' => 'cc-by-sa-4.0',
				'source' => 'commons-templates',
				'hidden' => ''
			]
		];
		$schema = [
			'@context' => 'https://schema.org',
			'@type' => 'ImageObject',
			'contentUrl' => 'https://commons.wikimedia.org/image/0/0f/Schema_test.jpg',
			'license' => 'https://creativecommons.org/licenses/by-sa/4.0',
			'acquireLicensePage' => 'https://commons.wikimedia.org/wiki/File:Schema_test.jpg'
		];

		return [
			'Missing upload date' => [ $metadata, $schema ]
		];
	}

	/**
	 * @param array $extendedMetadata
	 * @return FormatMetadata
	 */
	private function getMockFormat( $extendedMetadata = [] ) {
		$mock = $this->createMock( FormatMetadata::class );
		$mock->expects( $this->any() )
			->method( 'fetchExtendedMetadata' )
			->willReturn( $extendedMetadata );
		return $mock;
	}

	/**
	 * @param bool $exists
	 * @param MEDIATYPE_BITMAP|MEDIATYPE_DRAWING|MEDIATYPE_AUDIO|null $mediaType
	 * @return File
	 */
	private function getMockFile( $exists, $mediaType ) {
		$mock = $this->createMock( File::class );
		$mock->expects( $this->any() )
			->method( 'getFullURL' )
			->willReturn( 'https://commons.wikimedia.org/image/0/0f/Schema_test.jpg' );
		$mock->expects( $this->any() )
			->method( 'exists' )
			->willReturn( $exists );
		$mock->expects( $this->any() )
			->method( 'getMediaType' )
			->willReturn( $mediaType );
		return $mock;
	}

	/**
	 * @return Title
	 */
	private function getMockTitle() {
		$mock = $this->createMock( Title::class );
		$mock->expects( $this->any() )
			->method( 'getFullURL' )
			->willReturn( 'https://commons.wikimedia.org/wiki/File:Schema_test.jpg' );
		return $mock;
	}

}
