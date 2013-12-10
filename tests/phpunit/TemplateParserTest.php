<?php

use CommonsMetadata\TemplateParser;

/**
 * @covers TemplateParser
 * @group Extensions/CommonsMetadata
 */
class TemplateParserTest extends MediaWikiTestCase {
	/**
	 * Convenience switch for speed tests. When enabled, uses the old implementation of the template parser.
	 * @var bool
	 */
	protected static $useOldParser = 0;

	/**
	 * Maps test names to filenames in the test subdirectory.
	 * This array only exists to have a place where the intentions of test files can be conveniently commented.
	 * Files have been saved from the Commons images of the same name via action=render.
	 * @var array name => filename
	 */
	protected static $testHTMLFiles = array(
		// an image with no information template
		'noinfo' => 'File:Pentacle_3.svg',
		// a fairly simple page with a basic information template (with no language markup) and a single CC license
		'simple' => 'File:Sunrise_over_fishing_boats_in_Kerala.jpg',
		// language markup, but some of the description (a WLM reference number) is outside it
		'outside_lang' => 'File:Colonial_Williamsburg_(December,_2011)_-_Christmas_decorations_20.JPG',
		// English description only
		'singlelang' => 'File:Dala_Kyrka.JPG',
		// non-English description only
		'no_english' => 'File:Balkana,_januar_2012_(2).JPG',
		// en/fr/de description
		'multilang' => 'File:Sydney_Tower_Panorama.jpg',
		// complex non-ASCII characters
		'japanese' => 'File:SFC_.gif',
		// an image with multiple licenses (GFDL + 2xCC)
		'multilicense' => 'File:Pentacle_3.svg',
		// license template inside {{information}}
		'embedded_license' => 'File:Thury_Grave_Wiener_Zentralfriedhof.jpg',
		// coordinates
		'coord' => 'File:Sydney_Tower_Panorama.jpg',
		// complex HTML in the author field
		'creator_template' => 'File:Elizabeth_I_George_Gower.jpg',
		// an image with many languages
		'manylang' => 'File:Sikh_pilgrim_at_the_Golden_Temple_(Harmandir_Sahib)_in_Amritsar,_India.jpg',
		// an image with a relatively long description
		'big' => 'File:Askaris_im_Warschauer_Getto_-_1943.jpg',
	);

	/**
	 * Make sure there are no errors when common HTML structures are missing.
	 */
	public function testEmptyString() {
		$data = $this->getParser()->parsePage( '' );
		$this->assertEmpty( $data );
	}


	// -------------------- description tests --------------------

	/**
	 * When there is no {{en}} or similar language template in the description, all of it should
	 * be returned, regardless of user language
	 */
	public function testDescriptionWithoutLanguage() {
		$data = $this->parseTestHTML( 'simple' );
		$this->assertFieldContainsString( 'ImageDescription', 'Sunrise', $data );

		$parser = $this->getParser( 'de' );
		$data = $this->parseTestHTML( 'simple', $parser );
		$this->assertFieldContainsString( 'ImageDescription', 'Sunrise', $data );
	}

	/**
	 * When there is a single language template in the description,
	 * the contents of language template should be returned, regardless of language
	 */
	public function testDescriptionInSingleLanguage() {
		$data = $this->parseTestHTML( 'singlelang' );
		$this->assertFieldContainsString( 'ImageDescription', 'Dala kyrka', $data );

		$parser = $this->getParser( 'de' );
		$data = $this->parseTestHTML( 'singlelang', $parser );
		$this->assertFieldContainsString( 'ImageDescription', 'Dala kyrka', $data );
	}

	/**
	 * When the description is in language A, and we request language B, for which A is not a fallback,
	 * B should be returned.
	 * fixme this is a mixing of concerns - language resolution should be refactored into a separate class
	 */
	public function testDescriptionInSingleNonFallbackLanguage() {
		$data = $this->parseTestHTML( 'no_english' );
		$this->assertFieldContainsString( 'ImageDescription', 'Balkana', $data );

		$parser = $this->getParser( 'de' );
		$data = $this->parseTestHTML( 'no_english', $parser );
		$this->assertFieldContainsString( 'ImageDescription', 'Balkana', $data );
	}

	/**
	 * When there is a single language template in the description, anything outside it should be ignored.
	 */
	public function testDescriptionOutsideLanguageTemplate() {
		$data = $this->parseTestHTML( 'outside_lang' );
		$this->assertFieldContainsString( 'ImageDescription', 'Williamsburg Historic District', $data );
		$this->assertFieldNotContainsString( 'ImageDescription', 'This is an image of a place or building', $data );
	}

	/**
	 * When there are multiple language template in the description, all of it should
	 * be returned, regardless of user language.
	 */
	public function testDescriptionInMultipleLanguages() {
		$data = $this->parseTestHTML( 'multilang' ); // en/fr/de description
		$this->assertFieldContainsString( 'ImageDescription', 'Assembly', $data );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Rassemblement', $data );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Versammlung', $data );

		$parser = $this->getParser( 'fr' );
		$data = $this->parseTestHTML( 'multilang', $parser );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Assembly', $data );
		$this->assertFieldContainsString( 'ImageDescription', 'Rassemblement', $data );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Versammlung', $data );

		$parser = $this->getParser( 'de' );
		$data = $this->parseTestHTML( 'multilang', $parser );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Assembly', $data );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Rassemblement', $data );
		$this->assertFieldContainsString( 'ImageDescription', 'Versammlung', $data );

		$parser = $this->getParser( 'nl' );
		$data = $this->parseTestHTML( 'multilang', $parser );
		$this->assertFieldContainsString( 'ImageDescription', 'Assembly', $data );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Rassemblement', $data );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Versammlung', $data );
	}

	/**
	 * In multilang mode, all languages should be returned in an array.
	 */
	public function testMultilangModeInMultipleLanguages() {
		$parser = $this->getParser( false );
		$data = $this->parseTestHTML( 'multilang', $parser );

		$this->assertArrayHasKey( 'ImageDescription', $data );
		$description = $data['ImageDescription'];
		$this->assertLanguageArray( $description );

		unset( $description['_type'] );
		$this->assertArrayEquals( array( 'en', 'fr', 'de' ), array_keys( $description ) );
		$this->assertStringContains( 'Rassemblement', $description['fr'] );
	}

	/**
	 * When there is only a single language in multilang mode, it should still be returned in an array.
	 */
	public function testMultilangModeInSingleLanguage() {
		$parser = $this->getParser( false );
		$data = $this->parseTestHTML( 'singlelang', $parser );

		$this->assertArrayHasKey( 'ImageDescription', $data );
		$description = $data['ImageDescription'];
		$this->assertLanguageArray( $description );

		unset( $description['_type'] );
		$this->assertCount( 1, $description );
		$this->assertArrayHasKey( 'en', $description );
		$this->assertStringContains( 'Dala kyrka', $description['en'] );
	}

	/**
	 * In multilang mode, even when there is no language template used, we should still get the text, wrapped in an array.
	 * We do not assert anything about what the language key is - several choices might make sense.
	 */
	public function testMultilangModeWithoutLanguage() {
		$this->markTestSkipped( 'bug 57846');

		$parser = $this->getParser( false );
		$data = $this->parseTestHTML( 'simple', $parser );

		$this->assertArrayHasKey( 'ImageDescription', $data );
		$description = $data['ImageDescription'];
		$this->assertLanguageArray( $description );

		unset( $description['_type'] );
		$this->assertCount( 1, $description );
		$this->assertStringContains( 'Sunrise', reset( $description ) );
	}

	/**
	 * The markup generated by the language template ("English:" etc) should be skipped.
	 */
	public function testLanguageNameNotPresent() {
		$this->markTestSkipped( 'bug 57262' );

		$data = $this->parseTestHTML( 'singlelang' );
		$this->assertFieldNotContainsString( 'ImageDescription', 'English', $data );
	}

	/**
	 * There is no guarantee to return HTML, but surrounding whitespace and simple markup like a wrapping <p> should be removed.
	 */
	public function testSimpleWrappersAreRemoved() {
		$this->markTestSkipped( 'bug 57458, bug 57848' );

		$data = $this->parseTestHTML( 'simple' );
		$this->assertFieldStartsWith( 'ImageDescription', 'Sunrise', $data );
	}

	/**
	 * Some image descriptions contain complex creator templates with hCard data.
	 * The template markup should not be present in the metadata.
	 */
	public function testHCard() {
		$this->markTestSkipped( 'bug 57383' );

		$data = $this->parseTestHTML( 'creator_template' );
		$this->assertFieldEquals( 'Artist', 'George Gower', $data );
	}


	// -------------------- license tests --------------------

	public function testSingleLicense() {
		$data = $this->parseTestHTML( 'simple' );
		$this->assertFieldEquals( 'LicenseShortName', array( 'CC-BY-SA-3.0' ), $data );
		// long name is called UsageTerms - bug 57847
		$this->assertFieldEquals( 'UsageTerms', 'Creative Commons Attribution-Share Alike 3.0', $data );
		$this->assertFieldEquals( 'LicenseUrl', 'http://creativecommons.org/licenses/by-sa/3.0', $data );
		$this->assertFieldEquals( 'Copyrighted', 'True', $data );
	}

	public function testMultiLicense() {
		$data = $this->parseTestHTML( 'multilicense' );
		$this->assertFieldEquals( 'LicenseShortName', array( 'GFDL', 'CC-BY-SA-2.5', 'CC-BY-SA-3.0' ), $data );
		$this->markTestSkipped( 'bug 57259' );
		$this->assertFieldEquals( 'UsageTerms', array (
			'GNU Free Documentation License',
			'Creative Commons Attribution-Share Alike 2.5',
			'Creative Commons Attribution-Share Alike 3.0',
		), $data );
		$this->assertFieldEquals( 'LicenseUrl', array(
			'http://www.gnu.org/copyleft/fdl.html',
			'http://creativecommons.org/licenses/by-sa/2.5',
			'http://creativecommons.org/licenses/by-sa/3.0',
		), $data );
		$this->assertFieldEquals( 'Copyrighted', array(
			'True',
			'True',
			'True',
		), $data );
	}

	public function testLicenseTemplateInsideInformationTemplate() {
		$data = $this->parseTestHTML( 'embedded_license' );
		$this->assertFieldEquals( 'LicenseShortName', array( 'Public domain' ), $data );
	}


	// -------------------- misc tests --------------------

	/**
	 * Make sure non-ASCII characters are not garbled
	 */
	public function testUnicode() {
		$parser = $this->getParser( 'ja' );
		$data = $this->parseTestHTML( 'japanese', $parser );

		$this->assertFieldContainsString( 'ImageDescription', 'スーパーファミコン', $data );
	}

	/**
	 * Test coordinate extraction from {{location}} templates
	 */
	public function testCoordinates() {
		$data = $this->parseTestHTML( 'coord' );
		$this->assertFieldEquals( 'GPSLatitude', '-33.870455555556', $data );
		$this->assertFieldEquals( 'GPSLongitude', '151.20888888889', $data );
	}

	/**
	 * Manually executed speed test to compare performance of the two parsers.
	 */
	public function _testParsingSpeed() {
		for ( $i = 0; $i < 100; $i++ ) {
			foreach ( self::$testHTMLFiles as $test => $_ ) {
				$this->parseTestHTML( $test );
			}
		}
	}


	// -------------------- helpers --------------------

	/**
	 * Loads a test file (usually the saved output of action=render for some image description page).
	 * @param string $name
	 * @throws \InvalidArgumentException
	 * @return string
	 */
	protected function getTestHTML( $name ) {
		if ( !isset( self::$testHTMLFiles[$name] ) ) {
			throw new \InvalidArgumentException( 'no HTML test named ' . $name );
		}
		$filename = dirname( __DIR__ ) . '/html/' . self::$testHTMLFiles[$name] . '.html';

		if ( !file_exists( $filename ) ) {
			throw new \InvalidArgumentException( 'no HTML test file named ' . $filename );
		}
		$html = file_get_contents( $filename );
		return $html;
	}

	/**
	 * Convenience method to parses a test file.
	 * @param string $name
	 * @param TemplateParser $parser
	 * @return array metadata field => value
	 */
	protected function parseTestHTML( $name, $parser = null ) {
		if ( !$parser ) {
			$parser = $this->getParser();
		}
		$html = $this->getTestHTML( $name );
		return $parser->parsePage( $html );
	}

	/**
	 * Convenience method to create a new parser.
	 * @param string|bool $language language code for parser's language; false for multi-language mode
	 * @return TemplateParser
	 */
	protected function getParser( $language = 'en' ) {
		if ( self::$useOldParser ) {
			return $this->getOldParser( $language );
		}

		$parser = new TemplateParser();
		if ( $language === false ) {
			$language = 'en';
			$parser->setMultiLanguage( true );
		}
		$parser->setPriorityLanguages( array( $language ) );
		return $parser;
	}

	/**
	 * Use old parser, for speed tests.
	 * @param string|bool $language language code for parser's language; false for multi-language mode
	 * @return CommonsMetadata_TemplateParser
	 */
	protected function getOldParser( $language = 'en' ) {
		$parser = new CommonsMetadata_TemplateParser();
		$parser->setLanguage( $language );
		return $parser;
	}

	/**
	 * Equality assertion for a metadata field returned by the parser.
	 * @param string $key field name
	 * @param string $expected expected value
	 * @param array $data data returned by the parser
	 * @param string $message
	 */
	protected function assertFieldEquals( $key, $expected, $data, $message = '') {
		$this->assertArrayHasKey( $key, $data, $message );
		$actual = $data[$key];
		if ( is_array( $actual ) && is_array( $expected) ) {
			$this->assertArrayEquals( $expected, $actual, $message );
		} else {
			$this->assertEquals( $expected, $actual, $message );
		}
	}

	/**
	 * Asserts that a string has a given substring
	 * @param string $expectedSubstring literal (not regex)
	 * @param string $actualString
	 * @param string $message
	 */
	protected function assertStringContains( $expectedSubstring, $actualString, $message = '' ) {
		$newMessage = "String '" . $expectedSubstring . "' not found in \n" . $actualString;
		if ( $message ) {
			$newMessage .= "\n" . $message;
		}
		$this->assertNotSame( false, strpos( $actualString, $expectedSubstring ), $newMessage );
	}

	/**
	 * Asserts that a string does not have a given substring
	 * @param string $expectedSubstring literal (not regex)
	 * @param string $actualString
	 * @param string $message
	 */
	protected function assertStringNotContains( $expectedSubstring, $actualString, $message = '' ) {
		$newMessage = "String '" . $expectedSubstring . "' found in \n" . $actualString;
		if ( $message ) {
			$newMessage .= "\n" . $message;
		}
		$this->assertSame( false, strpos( $actualString, $expectedSubstring ), $newMessage );
	}

	/**
	 * Substring assertion for a metadata field returned by the parser.
	 * @param string $key field name
	 * @param string $expectedSubstring expected value
	 * @param array $data data returned by the parser
	 * @param string $message
	 */
	protected function assertFieldContainsString( $key, $expectedSubstring, $data, $message = '' ) {
		$this->assertArrayHasKey( $key, $data, $message );
		$actualString = $data[$key];
		$this->assertInternalType( 'string', $actualString, $message );
		$this->assertStringContains( $expectedSubstring, $actualString, $message );
	}

	/**
	 * Substring assertion for a metadata field returned by the parser.
	 * @param string $key field name
	 * @param string $expectedSubstring expected value
	 * @param array $data data returned by the parser
	 * @param string $message
	 */
	protected function assertFieldNotContainsString( $key, $expectedSubstring, $data, $message = '' ) {
		$this->assertArrayHasKey( $key, $data, $message );
		$actualString = $data[$key];
		$this->assertInternalType( 'string', $actualString, $message );
		$this->assertStringNotContains( $expectedSubstring, $actualString, $message );
	}

	/**
	 * Substring assertion for a metadata field returned by the parser.
	 * @param string $key field name
	 * @param string $expectedPrefix expected value
	 * @param array $data data returned by the parser
	 * @param string $message
	 */
	protected function assertFieldStartsWith( $key, $expectedPrefix, $data, $message = '' ) {
		$this->assertArrayHasKey( $key, $data, $message );
		$actual = $data[$key];
		$this->assertStringStartsWith( $expectedPrefix, $actual, $message );
	}

	/**
	 * Asserts that the parameter is a language array.
	 * See https://www.mediawiki.org/wiki/Manual:File_metadata_handling#Multi-language_array_format
	 * @param array $array
	 * @param string $message
	 */
	protected function assertLanguageArray( $array, $message = '' ) {
		$this->assertInternalType( 'array', $array, $message );
		$this->assertArrayHasKey( '_type', $array, $message );
		$this->assertEquals( 'lang', $array['_type'], $message );
	}
}
