<?php

/**
 * Declare mocks as stubs, because the class names are handled special in normal code
 * @phpcs:disable MediaWiki.Files.ClassMatchesFilename,Generic.Files.OneObjectStructurePerFile
 */

class ForeignDBFileMock extends ForeignDBFile {
	/** @var string[] */
	public $mockedCategories = [];
}

class LocalFileMock extends LocalFile {
	/** @var string[] */
	public $mockedCategories = [];
}
