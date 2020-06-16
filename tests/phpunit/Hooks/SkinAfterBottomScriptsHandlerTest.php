<?php

namespace CommonsMetadata\Hooks;

use File;
use FormatMetadata;
use Title;

/**
 * @covers \CommonsMetadata\Hooks\SkinAfterBottomScriptsHandler
 * @group Extensions/CommonsMetadata
 */
class SkinAfterBottomScriptsHandlerTest extends \MediaWikiTestCase {
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

	public function provideImageWithLicenseData() {
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
	public function testGetSchemaElementWithInvalidFiles( $file ) {
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

	public function provideInvalidFiles() {
		$nonexistentFile = $this->getMockFile( false, null );
		$wrongMediaTypeFile = $this->getMockFile( true, MEDIATYPE_AUDIO );
		$validFile = $this->getMockFile( true, MEDIATYPE_BITMAP );

		return [
			'Null value' => [ null ],
			'Nonexistent file' => [ $nonexistentFile ],
			'Wrong media type' => [ $wrongMediaTypeFile ] ,
			'No extended metadata' => [ $validFile ]
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

	public function providePublicDomainImageData() {
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

	public function provideImageWithMissingUploadDateData() {
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
	 * @param array $extmetadata
	 * @return FormatMetadata
	 */
	private function getMockFormat( $extendedMetadata = [] ) {
		$mock = $this->createMock( FormatMetadata::class );
		$mock->expects( $this->any() )
			->method( 'fetchExtendedMetadata' )
			->will( $this->returnValue( $extendedMetadata ) );
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
			->will( $this->returnValue( 'https://commons.wikimedia.org/image/0/0f/Schema_test.jpg' ) );
		$mock->expects( $this->any() )
			->method( 'exists' )
			->will( $this->returnValue( $exists ) );
		$mock->expects( $this->any() )
			->method( 'getMediaType' )
			->will( $this->returnValue( $mediaType ) );
		return $mock;
	}

	/**
	 * @return Title
	 */
	private function getMockTitle() {
		$mock = $this->createMock( Title::class );
		$mock->expects( $this->any() )
			->method( 'getFullURL' )
			->will( $this->returnValue( 'https://commons.wikimedia.org/wiki/File:Schema_test.jpg' ) );
		return $mock;
	}

}
