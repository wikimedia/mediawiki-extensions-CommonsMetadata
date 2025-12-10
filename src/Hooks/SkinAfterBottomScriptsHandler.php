<?php

namespace CommonsMetadata\Hooks;

use FormatMetadata;
use MediaWiki\FileRepo\File\File;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;

/**
 * @license GPL-2.0-or-later
 */
class SkinAfterBottomScriptsHandler {
	public function __construct(
		private readonly FormatMetadata $format,
		private readonly string $publicDomainPageUrl,
	) {
	}

	/**
	 * @param Title $title Title of the file page
	 * @param File|null $file The file itself
	 *
	 * @return string
	 */
	public function getSchemaElement( Title $title, $file ) {
		$allowedMediaTypes = [ MEDIATYPE_BITMAP, MEDIATYPE_DRAWING, MEDIATYPE_VIDEO, MEDIATYPE_AUDIO ];
		if ( $file === null || !$file->exists() || !in_array( $file->getMediaType(), $allowedMediaTypes ) ) {
			return '';
		}

		$extendedMetadata = $this->format->fetchExtendedMetadata( $file );
		$schema = $this->getSchema( $title, $file, $extendedMetadata );
		if ( $schema === null ) {
			return '';
		}

		$html = Html::openElement( 'script', [ 'type' => 'application/ld+json' ] );
		$html .= json_encode( $schema );
		$html .= Html::closeElement( 'script' );
		return $html;
	}

	/**
	 * @param Title $title
	 * @param File $file
	 * @param array $extendedMetadata
	 *
	 * @return array|null
	 */
	public function getSchema( Title $title, File $file, $extendedMetadata ) {
		if ( isset( $extendedMetadata[ 'LicenseUrl' ] ) ) {
			$licenseUrl = $extendedMetadata[ 'LicenseUrl' ][ 'value' ];
		} elseif ( isset( $extendedMetadata[ 'License' ] ) &&
			$extendedMetadata[ 'License' ][ 'value' ] === 'pd' ) {
			// If an image is in the public domain, there is no license to link
			// to, so we'll link to a page describing the use of such images.
			$licenseUrl = $this->publicDomainPageUrl;
		}

		if ( !isset( $licenseUrl ) ) {
			return null;
		}

		$schemaType = $this->getSchemaType( $file->getMediaType() );
		$schema = [
			'@context' => 'https://schema.org',
			'@type' => $schemaType,
			// The original file, which is what's indexed by Google and present
			// in Google image searches.
			'contentUrl' => $file->getFullUrl(),
			// A link to the actual license (or to the public domain page).
			'license' => $licenseUrl,
			// This is meant to be a human-readable summary of the license, so
			// we're linking to the file page which contains such a summary in
			// the Licensing section.
			'acquireLicensePage' => $title->getFullURL(),
		];

		// MediaObject properties
		// ObjectName and ImageDescription would override our ogp annotations. disabled for now.
//		if ( isset( $extendedMetadata[ 'ObjectName' ] ) ) {
//			$schema['name'] = $extendedMetadata[ 'ObjectName' ][ 'value' ];
//		}
//		if ( isset( $extendedMetadata[ 'ImageDescription' ] ) ) {
//			$schema['description'] = $extendedMetadata[ 'ImageDescription' ][ 'value' ];
//		}
		if ( isset( $extendedMetadata[ 'DateTime' ] ) ) {
			$schema[ 'uploadDate' ] = $extendedMetadata[ 'DateTime' ][ 'value' ];
		}

		// More specific media properties
		if ( $schemaType === 'ImageObject' || $schemaType === 'VideoObject' ) {
			$schema[ 'width' ] = "{$file->getWidth()} px";
			$schema[ 'height' ] = "{$file->getHeight()} px";
		}
		if ( $schemaType === 'AudioObject' || $schemaType === 'VideoObject' ) {
			$schema[ 'duration' ] = $this->secondsToIso8601Duration( (int)$file->getLength() );
			if ( ExtensionRegistry::getInstance()->isLoaded( 'TimedMediaHandler' ) ) {
				$schema[ 'embedUrl' ] = wfAppendQuery( $title->getCanonicalURL(), [ 'embedplayer' => 'true' ] );
			}
		}
		if ( $schemaType === 'VideoObject' ) {
			$thumb = $file->transform( [ 'width' => 1200, 'height' => 1200 ] );
			$schema[ 'thumbnailUrl' ] = (string)MediaWikiServices::getInstance()->getUrlUtils()
				->expand( $thumb->getUrl(), PROTO_CANONICAL );
		}

		// Additional MediaObject properties
		$schema['contentSize'] = $file->getSize();
		$schema['encodingFormat'] = $file->getMimeType();

		return $schema;
	}

	protected function getSchemaType( string $mediaType ): string {
		switch ( $mediaType ) {
			case MEDIATYPE_BITMAP:
			case MEDIATYPE_DRAWING:
				return 'ImageObject';
			case MEDIATYPE_AUDIO:
				return 'AudioObject';
			case MEDIATYPE_VIDEO:
				return 'VideoObject';
			default:
				throw new \InvalidArgumentException( 'Unsupported media type for schema.org' );
		}
	}

	private function secondsToIso8601Duration( int $seconds ): string {
		$seconds = max( 0, $seconds );
		$h = intdiv( $seconds, 3600 );
		$m = intdiv( $seconds % 3600, 60 );
		$s = $seconds % 60;
		$duration = 'PT';
		if ( $h > 0 ) {
			$duration .= $h . 'H';
		}
		if ( $m > 0 ) {
			$duration .= $m . 'M';
		}
		// Always include seconds to avoid empty PT
		$duration .= $s . 'S';
		return $duration;
	}
}
