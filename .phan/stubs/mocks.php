<?php

/**
 * Declare mocks as stubs, because the class names are handled special in normal code
 * @phpcs:disable MediaWiki.Files.ClassMatchesFilename,Generic.Files.OneObjectStructurePerFile
 */

class ForeignDBFileMock extends ForeignDBFile {
	public $mockedCategories = [];
}

class LocalFileMock extends LocalFile {
	public $mockedCategories = [];
}
