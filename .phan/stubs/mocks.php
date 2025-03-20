<?php

/**
 * Declare mocks as stubs, because the class names are handled special in normal code
 * @phpcs:disable MediaWiki.Files.ClassMatchesFilename,Generic.Files.OneObjectStructurePerFile
 */

use MediaWiki\FileRepo\File\ForeignDBFile;
use MediaWiki\FileRepo\File\LocalFile;

class ForeignDBFileMock extends ForeignDBFile {
}

class LocalFileMock extends LocalFile {
}
