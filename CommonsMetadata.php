<?php
# Extends the extmetadata propery of image info API module to include
# details from file description pages that use commons style templates.
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
# http://www.gnu.org/copyleft/gpl.html

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'CommonsMetadata',
	'author' => 'Brian Wolff',
	'url' => '//www.mediawiki.org/wiki/Extension:CommonsMetadata',
	'descriptionmsg' => 'commonsmetadata-desc',
);

$wgAutoloadClasses['CommonsMetadata\HookHandler'] = __DIR__ . '/HookHandler.php';
$wgAutoloadClasses['CommonsMetadata\DataCollector'] = __DIR__ . '/DataCollector.php';
$wgAutoloadClasses['CommonsMetadata\DomNavigator'] = __DIR__ . '/DomNavigator.php';
$wgAutoloadClasses['CommonsMetadata\TemplateParser'] = __DIR__ . '/TemplateParser.php';
$wgAutoloadClasses['CommonsMetadata\LicenseParser'] = __DIR__ . '/LicenseParser.php';

$wgMessagesDirs['CommonsMetadata'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['CommonsMetadata'] =  __DIR__ . '/CommonsMetadata.i18n.php';

$wgHooks['GetExtendedMetadata'][] = 'CommonsMetadata\HookHandler::onGetExtendedMetadata';
$wgHooks['ValidateExtendedMetadataCache'][] = 'CommonsMetadata\HookHandler::onValidateExtendedMetadataCache';
$wgHooks['UnitTestsList'][] = 'CommonsMetadata\HookHandler::onUnitTestsList';
