<?php

namespace CommonsMetadata;

use Content;
use File;
use IContextSource;
use Language;
use ParserOutput;
use RepoGroup;
use Title;

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
	const VERSION = 1.2;

	/**
	 * Hook handler for extended metadata
	 *
	 * @param array $combinedMeta Metadata so far
	 * @param File $file The file object in question
	 * @param IContextSource $context Context. Used to select language
	 * @param bool $singleLang Get only target language, or all translations
	 * @param int &$maxCache How many seconds to cache the result
	 * @return bool This hook handler always returns true
	 */
	public static function onGetExtendedMetadata(
		&$combinedMeta, File $file, IContextSource $context, $singleLang, &$maxCache
	) {
		global $wgCommonsMetadataForceRecalculate;

		if (
			isset( $combinedMeta['CommonsMetadataExtension']['value'] )
			&& $combinedMeta['CommonsMetadataExtension']['value'] == self::VERSION
			&& !$wgCommonsMetadataForceRecalculate
		) {
			// This is a file from a remote API repo, and CommonsMetadata is installed on
			// the remote as well, and generates the same metadata format. We have nothing to do.
			return true;
		} else {
			$combinedMeta['CommonsMetadataExtension'] = [
				'value' => self::VERSION,
				'source' => 'extension',
			];
		}

		$lang = $context->getLanguage();

		$templateParser = new TemplateParser();
		$templateParser->setMultiLanguage( !$singleLang );
		$fallbacks = Language::getFallbacksFor( $lang->getCode() );
		array_unshift( $fallbacks, $lang->getCode() );
		$templateParser->setPriorityLanguages( $fallbacks );

		$dataCollector = new DataCollector();
		$dataCollector->setLanguage( $lang );
		$dataCollector->setMultiLang( !$singleLang );
		$dataCollector->setTemplateParser( $templateParser );
		$dataCollector->setLicenseParser( new LicenseParser() );

		$dataCollector->collect( $combinedMeta, $file );

		if ( !$file->getDescriptionTouched() ) {
			// Not all files provide the last update time of the description.
			// If that's the case, just cache blindly for a shorter period.
			$maxCache = 60 * 60 * 12;
		}

		return true;
	}

	/**
	 * Hook to check if cache is stale
	 *
	 * @param string $timestamp Timestamp of when cache taken
	 * @param File $file The file metadata is for
	 * @return bool Is metadata still valid
	 */
	public static function onValidateExtendedMetadataCache( $timestamp, File $file ) {
		return // use cached value if...
			// we don't know when the file was last updated
			!$file->getDescriptionTouched()
			// or last update was before we cached it
			|| wfTimestamp( TS_UNIX, $file->getDescriptionTouched() )
				<= wfTimestamp( TS_UNIX, $timestamp );
	}

	/**
	 * Check HTML output of a file page to see if it has all the basic metadata, and
	 * add tracking categories if it does not.
	 * @param Content $content
	 * @param Title $title
	 * @param ParserOutput $parserOutput
	 * @return bool this hook handler always returns true.
	 */
	public static function onContentAlterParserOutput(
		Content $content, Title $title, ParserOutput $parserOutput
	) {
		global $wgCommonsMetadataSetTrackingCategories;

		if (
			!$wgCommonsMetadataSetTrackingCategories
			|| !$title->inNamespace( NS_FILE )
			|| $content->getModel() !== CONTENT_MODEL_WIKITEXT
			|| !RepoGroup::singleton()->getLocalRepo()->findFile(
				$title, [ 'ignoreRedirect' => true ] )
		) {
			return true;
		}

		$language = $content->getContentHandler()->getPageViewLanguage( $title, $content );
		$dataCollector = self::getDataCollector( $language, true );

		$categoryKeys = $dataCollector->verifyAttributionMetadata( $parserOutput->getText() );
		foreach ( $categoryKeys as $key ) {
			$parserOutput->addTrackingCategory(
				'commonsmetadata-trackingcategory-' . $key, $title );
		}

		return true;
	}

	/**
	 * @param Language $lang
	 * @param bool $singleLang
	 * @return DataCollector
	 */
	private static function getDataCollector( Language $lang, $singleLang ) {
		$templateParser = new TemplateParser();
		$templateParser->setMultiLanguage( !$singleLang );
		$fallbacks = Language::getFallbacksFor( $lang->getCode() );
		array_unshift( $fallbacks, $lang->getCode() );
		$templateParser->setPriorityLanguages( $fallbacks );

		$dataCollector = new DataCollector();
		$dataCollector->setLanguage( $lang );
		$dataCollector->setMultiLang( !$singleLang );
		$dataCollector->setTemplateParser( $templateParser );
		$dataCollector->setLicenseParser( new LicenseParser() );

		return $dataCollector;
	}
}
