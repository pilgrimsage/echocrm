<?php
/**
 * Gives the Status field of Bank Accounts and Cost Centres a picklist of its own. Both fields were
 * called `status`, the name of an old table (`vtiger_status`, no sort column) that vtiger cannot
 * read as a picklist, so the Active / Inactive dropdown was empty and, the field being mandatory,
 * a bank account or cost centre could not be saved from the edit form. The fields are now
 * `bankaccount_status` and `costcentre_status` (same database column, `status`, so existing records
 * and the SQL that reads them are untouched). Safe to run more than once.
 *
 * Usage (from the project root):   php bin/fix-status-picklists.php
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

// module => array(new field name, the table that holds the column)
$targets = array(
	'BankAccounts' => array('bankaccount_status', 'vtiger_bankaccounts'),
	'CostCentres' => array('costcentre_status', 'vtiger_costcentres'),
);

foreach ($targets as $moduleName => $target) {
	list($newName, $table) = $target;
	$module = Vtiger_Module::getInstance($moduleName);
	if (!$module) {
		say("$moduleName is not installed, skipped.");
		continue;
	}

	$renamed = $adb->pquery('SELECT 1 FROM vtiger_field WHERE tabid = ? AND fieldname = ?', array($module->id, $newName));
	if (!$adb->num_rows($renamed)) {
		$old = $adb->pquery('SELECT 1 FROM vtiger_field WHERE tabid = ? AND fieldname = ?', array($module->id, 'status'));
		if (!$adb->num_rows($old)) {
			fwrite(STDERR, "$moduleName has neither a status nor a $newName field.\n");
			exit(1);
		}
		$adb->pquery('UPDATE vtiger_field SET fieldname = ? WHERE tabid = ? AND fieldname = ?', array($newName, $module->id, 'status'));
		// saved list views and filters that use the column
		$oldColumn = $table . ':status:status:' . $moduleName . '_Status:V';
		$newColumn = $table . ':status:' . $newName . ':' . $moduleName . '_Status:V';
		foreach (array('vtiger_cvcolumnlist', 'vtiger_cvadvfilter') as $cvTable) {
			$adb->pquery("UPDATE $cvTable SET columnname = ? WHERE columnname = ?", array($newColumn, $oldColumn));
		}
		say("$moduleName: renamed the field to $newName.");
	}

	$picklist = $adb->pquery('SELECT picklistid FROM vtiger_picklist WHERE name = ?', array($newName));
	if (!$adb->num_rows($picklist)) {
		// a plain field object: the lookup by name is cached and, right after the rename, still empty
		$field = new Vtiger_Field();
		$field->name = $newName;
		$field->uitype = 15;
		$field->setPicklistValues(array('Active', 'Inactive'));
		if (!Vtiger_Utils::CheckTable('vtiger_' . $newName)) {
			fwrite(STDERR, "The picklist table for $newName was not created.\n");
			exit(1);
		}
		say("$moduleName: created the Active / Inactive picklist.");
	} else {
		say("$moduleName: the picklist exists.");
	}
}

create_tab_data_file();
create_parenttab_data_file();
say('Done. Clear test/templates_c/v7/* and reload.');
