<?php

namespace CommonsMetadata;

use Language;

/**
 * Hook handler
 */
class HookHandler {
	/**
	 * Metadata version. When getting metadata of a remote file via the API, sometimes
	 * we get the data generated by a CommonsMetadata extension installed at the remote,
	 * as well. We use this version number to keep track of whether that data is different
	 * from what would be generated here.
	 * @var float
	 */
	const VERSION = 1.1;

	/**
	 * Hook handler for extended metadata
	 *
	 * @param $combinedMeta Array Metadata so far
	 * @param $file \File The file object in question
	 * @param $context \IContextSource context. Used to select language
	 * @param $singleLang Boolean Get only target language, or all translations
	 * @param &$maxCache Integer How many seconds to cache the result
	 * @return bool this hook handler always returns true.
	 */
	public static function onGetExtendedMetadata( &$combinedMeta, \File $file, \IContextSource $context, $singleLang, &$maxCache ) {
		if (
			isset( $combinedMeta['CommonsMetadataExtension'] )
			&& $combinedMeta['CommonsMetadataExtension'] == self::VERSION
		) {
			// This is a file from a remote API repo, and CommonsMetadata is installed on
			// the remote as well, and generates the same metadata format. We have nothing to do.
			return true;
		} else {
			$combinedMeta['CommonsMetadataExtension'] = array(
				'value' => self::VERSION,
				'source' => 'extension',
			);
		}

		$lang = $context->getLanguage();

		global $wgUseOldTemplateParser; // FIXME feature switch for convenient testing, will be removed once this is out of beta
		if ( !isset( $wgUseOldTemplateParser ) ) {
			$templateParser = new TemplateParser();
			$templateParser->setMultiLanguage( !$singleLang );
			$fallbacks = Language::getFallbacksFor( $lang->getCode() );
			array_unshift( $fallbacks, $lang->getCode() );
			$templateParser->setPriorityLanguages( $fallbacks );
		} else {
			$templateParser = new \CommonsMetadata_TemplateParser();
			$templateParser->setLanguage( $singleLang ? $lang->getCode() : false );
		}

		$dataCollector = new DataCollector();
		$dataCollector->setLanguage( $lang );
		$dataCollector->setMultiLang( !$singleLang );
		$dataCollector->setTemplateParser( $templateParser );
		$dataCollector->setLicenseParser( new LicenseParser() );

		$dataCollector->collect( $combinedMeta, $file );

		if ( !$file->isLocal() ) {
			// Foreign files don't have explicit cache purging
			// In theory, if this became an issue, we could do
			// a db query to the foreign wiki to look at page_touched.
			$maxCache = 60 * 60 * 12;
		}

		return true;
	}

	/**
	 * Hook to check if cache is stale
	 *
	 * @param $timestamp String Timestamp of when cache taken
	 * @param $file \File The file metadata is for
	 * @return boolean Is metadata still valid
	 */
	public static function onValidateExtendedMetadataCache( $timestamp, $file ) {
		return // use cached value if...
			!$file->isLocal() // file is remote (we don't know when remote updates happen, so we always cache, with a short TTL)
			|| $file->getTitle()->getTouched() === false // or we don't know when the file was last updated
			|| wfTimestamp( TS_UNIX, $file->getTitle()->getTouched() ) // or last update was before we cached it
				<= wfTimestamp( TS_UNIX, $timestamp );
	}

	/**
	 * Hook to add unit tests
	 * @param array $files
	 * @return bool
	 */
	public static function onUnitTestsList( &$files ) {
		$testDir = __DIR__ . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'phpunit';
		$files = array_merge( $files, glob( $testDir . DIRECTORY_SEPARATOR . '*Test.php' ) );
		return true;
	}
}
