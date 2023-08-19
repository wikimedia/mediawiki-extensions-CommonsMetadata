<?php

namespace CommonsMetadata\Hooks;

use File;
use FormatMetadata;
use Html;
use MediaWiki\Title\Title;

/**
 * @license GPL-2.0-or-later
 */
class SkinAfterBottomScriptsHandler {
	/** @var FormatMetadata */
	private $format;

	/** @var string */
	private $publicDomainPageUrl;

	/**
	 * @param FormatMetadata $format
	 * @param string $publicDomainPageUrl
	 */
	public function __construct( $format, $publicDomainPageUrl ) {
		$this->format = $format;
		$this->publicDomainPageUrl = $publicDomainPageUrl;
	}

	/**
	 * @param Title $title Title of the file page
	 * @param File|null $file The file itself
	 *
	 * @return string
	 */
	public function getSchemaElement( Title $title, $file ) {
		$allowedMediaTypes = [ MEDIATYPE_BITMAP, MEDIATYPE_DRAWING ];
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

		$schema = [
			'@context' => 'https://schema.org',
			'@type' => 'ImageObject',
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

		if ( isset( $extendedMetadata[ 'DateTime' ] ) ) {
			$schema[ 'uploadDate' ] = $extendedMetadata[ 'DateTime' ][ 'value' ];
		}

		return $schema;
	}
}
