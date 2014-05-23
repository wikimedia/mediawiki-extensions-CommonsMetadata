<?php

use CommonsMetadata\TemplateParser;

/**
 * @covers CommonsMetadata\TemplateParser
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
		'noinfo' => 'File_Pentacle_3.svg',
		// a fairly simple page with a basic information template (with no language markup) and a single CC license
		'simple' => 'File_Sunrise_over_fishing_boats_in_Kerala.jpg',
		// language markup, but some of the description (a WLM reference number) is outside it
		'outside_lang' => 'File_Colonial_Williamsburg_(December,_2011)_-_Christmas_decorations_20.JPG',
		// English description only
		'singlelang' => 'File_Dala_Kyrka.JPG',
		// non-English description only
		'no_english' => 'File_Balkana,_januar_2012_(2).JPG',
		// en/fr/de description
		'multilang' => 'File_Sydney_Tower_Panorama.jpg',
		// complex non-ASCII characters
		'japanese' => 'File_SFC_.gif',
		// an image with multiple licenses (GFDL + 2xCC)
		'multilicense' => 'File_Pentacle_3.svg',
		// license template inside {{information}}
		'embedded_license' => 'File_Thury_Grave_Wiener_Zentralfriedhof.jpg',
		// coordinates
		'coord' => 'File_Sydney_Tower_Panorama.jpg',
		// complex HTML in the author field
		'creator_template' => 'File_Elizabeth_I_George_Gower.jpg',
		// an image with many languages
		'manylang' => 'File_Sikh_pilgrim_at_the_Golden_Temple_(Harmandir_Sahib)_in_Amritsar,_India.jpg',
		// an image with a relatively long description
		'big' => 'File_Askaris_im_Warschauer_Getto_-_1943.jpg',
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
		$this->assertFieldContainsString( 'ImageDescription', 'Sunrise', $data, TemplateParser::INFORMATION_FIELDS_KEY );

		$parser = $this->getParser( 'de' );
		$data = $this->parseTestHTML( 'simple', $parser );
		$this->assertFieldContainsString( 'ImageDescription', 'Sunrise', $data, TemplateParser::INFORMATION_FIELDS_KEY );
	}

	/**
	 * When there is a single language template in the description,
	 * the contents of language template should be returned, regardless of language
	 */
	public function testDescriptionInSingleLanguage() {
		$data = $this->parseTestHTML( 'singlelang' );
		$this->assertFieldContainsString( 'ImageDescription', 'Dala kyrka', $data, TemplateParser::INFORMATION_FIELDS_KEY );

		$parser = $this->getParser( 'de' );
		$data = $this->parseTestHTML( 'singlelang', $parser );
		$this->assertFieldContainsString( 'ImageDescription', 'Dala kyrka', $data, TemplateParser::INFORMATION_FIELDS_KEY );
	}

	/**
	 * When the description is in language A, and we request language B, for which A is not a fallback,
	 * B should be returned.
	 * fixme this is a mixing of concerns - language resolution should be refactored into a separate class
	 */
	public function testDescriptionInSingleNonFallbackLanguage() {
		$data = $this->parseTestHTML( 'no_english' );
		$this->assertFieldContainsString( 'ImageDescription', 'Balkana', $data, TemplateParser::INFORMATION_FIELDS_KEY );

		$parser = $this->getParser( 'de' );
		$data = $this->parseTestHTML( 'no_english', $parser );
		$this->assertFieldContainsString( 'ImageDescription', 'Balkana', $data, TemplateParser::INFORMATION_FIELDS_KEY );
	}

	/**
	 * When there is a single language template in the description, anything outside it should be ignored.
	 */
	public function testDescriptionOutsideLanguageTemplate() {
		$data = $this->parseTestHTML( 'outside_lang' );
		$this->assertFieldContainsString( 'ImageDescription', 'Williamsburg Historic District', $data, TemplateParser::INFORMATION_FIELDS_KEY );
		$this->assertFieldNotContainsString( 'ImageDescription', 'This is an image of a place or building', $data, TemplateParser::INFORMATION_FIELDS_KEY );
	}

	/**
	 * When there are multiple language template in the description, all of it should
	 * be returned, regardless of user language.
	 */
	public function testDescriptionInMultipleLanguages() {
		$data = $this->parseTestHTML( 'multilang' ); // en/fr/de description
		$this->assertFieldContainsString( 'ImageDescription', 'Assembly', $data, TemplateParser::INFORMATION_FIELDS_KEY );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Rassemblement', $data, TemplateParser::INFORMATION_FIELDS_KEY );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Versammlung', $data, TemplateParser::INFORMATION_FIELDS_KEY );

		$parser = $this->getParser( 'fr' );
		$data = $this->parseTestHTML( 'multilang', $parser );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Assembly', $data, TemplateParser::INFORMATION_FIELDS_KEY );
		$this->assertFieldContainsString( 'ImageDescription', 'Rassemblement', $data, TemplateParser::INFORMATION_FIELDS_KEY );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Versammlung', $data, TemplateParser::INFORMATION_FIELDS_KEY );

		$parser = $this->getParser( 'de' );
		$data = $this->parseTestHTML( 'multilang', $parser );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Assembly', $data, TemplateParser::INFORMATION_FIELDS_KEY );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Rassemblement', $data, TemplateParser::INFORMATION_FIELDS_KEY );
		$this->assertFieldContainsString( 'ImageDescription', 'Versammlung', $data, TemplateParser::INFORMATION_FIELDS_KEY );

		$parser = $this->getParser( 'nl' );
		$data = $this->parseTestHTML( 'multilang', $parser );
		$this->assertFieldContainsString( 'ImageDescription', 'Assembly', $data, TemplateParser::INFORMATION_FIELDS_KEY );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Rassemblement', $data, TemplateParser::INFORMATION_FIELDS_KEY );
		$this->assertFieldNotContainsString( 'ImageDescription', 'Versammlung', $data, TemplateParser::INFORMATION_FIELDS_KEY );
	}

	/**
	 * In multilang mode, all languages should be returned in an array.
	 */
	public function testMultilangModeInMultipleLanguages() {
		$parser = $this->getParser( false );
		$data = $this->parseTestHTML( 'multilang', $parser );

		$data = $this->getAndAssertTemplateData( $data, TemplateParser::INFORMATION_FIELDS_KEY );
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

		$data = $this->getAndAssertTemplateData( $data, TemplateParser::INFORMATION_FIELDS_KEY );
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

		$data = $this->getAndAssertTemplateData( $data, TemplateParser::INFORMATION_FIELDS_KEY );
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
		$data = $this->parseTestHTML( 'singlelang' );
		$this->assertFieldNotContainsString( 'ImageDescription', 'English', $data, TemplateParser::INFORMATION_FIELDS_KEY );
	}

	/**
	 * There is no guarantee to return HTML, but surrounding whitespace and simple markup like a wrapping <p> should be removed.
	 */
	public function testSimpleWrappersAreRemoved() {
		$data = $this->parseTestHTML( 'simple' );
		$this->assertFieldStartsWith( 'ImageDescription', 'Sunrise', $data, TemplateParser::INFORMATION_FIELDS_KEY );
	}

	/**
	 * Some image descriptions contain complex creator templates with hCard data.
	 * The template markup should not be present in the metadata.
	 */
	public function testHCard() {
		$data = $this->parseTestHTML( 'creator_template' );
		$this->assertFieldEquals( 'Artist',
			'<bdi>After <a href="//en.wikipedia.org/wiki/George_Gower" class="extiw" title="en:George Gower">George Gower</a></bdi>',
			$data, TemplateParser::INFORMATION_FIELDS_KEY );
	}


	// -------------------- license tests --------------------

	public function testSingleLicense() {
		$data = $this->parseTestHTML( 'simple' );

		$this->assertFieldEquals( 'LicenseShortName', 'CC-BY-SA-3.0', $data, TemplateParser::LICENSES_KEY );
		// long name is called UsageTerms - bug 57847
		$this->assertFieldEquals( 'UsageTerms', 'Creative Commons Attribution-Share Alike 3.0', $data, TemplateParser::LICENSES_KEY );
		$this->assertFieldEquals( 'LicenseUrl', 'http://creativecommons.org/licenses/by-sa/3.0', $data, TemplateParser::LICENSES_KEY );
		$this->assertFieldEquals( 'Copyrighted', 'True', $data, TemplateParser::LICENSES_KEY );
	}

	public function testMultiLicense() {
		$data = $this->parseTestHTML( 'multilicense' );

		$this->assertFieldEquals( 'LicenseShortName', 'GFDL', $data, TemplateParser::LICENSES_KEY, 0 );
		$this->assertFieldEquals( 'LicenseShortName', 'CC-BY-SA-3.0', $data, TemplateParser::LICENSES_KEY, 1 );
		$this->assertFieldEquals( 'LicenseShortName', 'CC-BY-SA-2.5', $data, TemplateParser::LICENSES_KEY, 2 );

		$this->assertFieldEquals( 'UsageTerms', 'GNU Free Documentation License', $data, TemplateParser::LICENSES_KEY, 0 );
		$this->assertFieldEquals( 'UsageTerms', 'Creative Commons Attribution-Share Alike 3.0', $data, TemplateParser::LICENSES_KEY, 1 );
		$this->assertFieldEquals( 'UsageTerms', 'Creative Commons Attribution-Share Alike 2.5', $data, TemplateParser::LICENSES_KEY, 2 );

		$this->assertFieldEquals( 'LicenseUrl', 'http://www.gnu.org/copyleft/fdl.html', $data, TemplateParser::LICENSES_KEY, 0 );
		$this->assertFieldEquals( 'LicenseUrl', 'http://creativecommons.org/licenses/by-sa/3.0/', $data, TemplateParser::LICENSES_KEY, 1 );
		$this->assertFieldEquals( 'LicenseUrl', 'http://creativecommons.org/licenses/by-sa/2.5', $data, TemplateParser::LICENSES_KEY, 2 );

		$this->assertFieldEquals( 'Copyrighted', 'True', $data, TemplateParser::LICENSES_KEY, 0 );
		$this->assertFieldEquals( 'Copyrighted', 'True', $data, TemplateParser::LICENSES_KEY, 1 );
		$this->assertFieldEquals( 'Copyrighted', 'True', $data, TemplateParser::LICENSES_KEY, 2 );
	}

	public function testLicenseTemplateInsideInformationTemplate() {
		$data = $this->parseTestHTML( 'embedded_license' );
		$this->assertFieldEquals( 'LicenseShortName', 'Public domain', $data, TemplateParser::LICENSES_KEY );
	}


	// -------------------- misc tests --------------------

	/**
	 * Make sure non-ASCII characters are not garbled
	 */
	public function testUnicode() {
		$parser = $this->getParser( 'ja' );
		$data = $this->parseTestHTML( 'japanese', $parser );

		$this->assertFieldContainsString( 'ImageDescription', 'スーパーファミコン', $data, TemplateParser::INFORMATION_FIELDS_KEY );
	}

	/**
	 * Test coordinate extraction from {{location}} templates
	 */
	public function testCoordinates() {
		$data = $this->parseTestHTML( 'coord' );
		$this->assertFieldEquals( 'GPSLatitude', '-33.870455555556', $data, TemplateParser::COORDINATES_KEY );
		$this->assertFieldEquals( 'GPSLongitude', '151.20888888889', $data, TemplateParser::COORDINATES_KEY );
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
	 * @param string $type one of the TemplateParser::*_KEY constants
	 * @param int $position template position wrt templates of the same type
	 * @param string $message
	 */
	protected function assertFieldEquals( $key, $expected, $data, $type, $position = 0, $message = '') {
		$data = $this->getAndAssertTemplateData( $data, $type, $position );
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
	 * Asserts that $data contains at least $position templates of type $type, and returns the one
	 * at $position.
	 * @param array $data
	 * @param string $type one of the TemplateParser::*_KEY constants
	 * @param int $position template position wrt templates of the same type
	 */
	protected function getAndAssertTemplateData( $data, $type, $position = 0 ) {
		$this->assertArrayHasKey( $type, $data, "No $type type in template data" );
		$this->assertArrayHasKey( $position, $data[$type], "No position $position for template type $type in data" );
		return $data[$type][$position];
	}

	/**
	 * Substring assertion for a metadata field returned by the parser.
	 * @param string $key field name
	 * @param string $expectedSubstring expected value
	 * @param array $data data returned by the parser
	 * @param string $type one of the TemplateParser::*_KEY constants
	 * @param int $position template position wrt templates of the same type
	 * @param string $message
	 */
	protected function assertFieldContainsString( $key, $expectedSubstring, $data, $type, $position = 0, $message = '' ) {
		$data = $this->getAndAssertTemplateData( $data, $type, $position );
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
	 * @param string $type one of the TemplateParser::*_KEY constants
	 * @param int $position template position wrt templates of the same type
	 * @param string $message
	 */
	protected function assertFieldNotContainsString( $key, $expectedSubstring, $data, $type, $position = 0, $message = '' ) {
		$data = $this->getAndAssertTemplateData( $data, $type, $position );
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
	 * @param string $type one of the TemplateParser::*_KEY constants
	 * @param int $position template position wrt templates of the same type
	 * @param string $message
	 */
	protected function assertFieldStartsWith( $key, $expectedPrefix, $data, $type, $position = 0, $message = '' ) {
		$data = $this->getAndAssertTemplateData( $data, $type, $position );
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
