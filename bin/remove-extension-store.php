<?php
/**
 * Removes the Extension Store module from the database (its code is gone from the tree): the module
 * registration, its Settings entry and its credentials table. Safe to run more than once; take a
 * database backup first.
 *
 * Usage (from the project root):   php bin/remove-extension-store.php
 * Afterwards: clear test/templates_c/v7/*, and run bin/dump-db.sh if fresh clones should match.
 */
if (PHP_SAPI !== 'cli') {
	exit("Run this from the command line.\n");
}
chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/utils/utils.php';
vimport('includes.runtime.EntryPoint');
include_once 'vtlib/Vtiger/Module.php';

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

function say($message) {
	echo $message . "\n";
}

$adb->pquery('DELETE FROM vtiger_settings_field WHERE name = ?', array('LBL_EXTENSION_STORE'));
say('Removed the Settings entry.');

$module = Vtiger_Module::getInstance('ExtensionStore');
if ($module) {
	$module->delete();
	say('Unregistered the module (blocks, filters, sharing, tools, web service, profiles, links).');
} else {
	say('The ExtensionStore module is not registered.');
}

$adb->query('DROP TABLE IF EXISTS vtiger_extnstore_users');
say('Dropped vtiger_extnstore_users.');

create_tab_data_file();
create_parenttab_data_file();
say('Done. Clear test/templates_c/v7/* and reload.');
