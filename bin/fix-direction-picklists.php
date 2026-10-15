<?php
/**
 * Separates the "Direction" picklists of Payments and Bank Transactions. Both fields were called
 * `direction`, so they shared one table holding Received / Paid / In / Out: a Bank Transaction form
 * offered Received and Paid (and refused them, only In and Out are valid), and Payments offered
 * In and Out. The Payments field is now `payment_direction` (same database column, `direction`)
 * with Received / Paid; the shared table keeps In / Out for Bank Transactions. Existing records keep
 * their values. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/fix-direction-picklists.php
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

$module = Vtiger_Module::getInstance('Payments');
if (!$module) {
	say('The Payments module is not installed; nothing to do.');
	exit(0);
}

$renamed = $adb->pquery('SELECT 1 FROM vtiger_field WHERE tabid = ? AND fieldname = ?', array($module->id, 'payment_direction'));
if (!$adb->num_rows($renamed)) {
	$adb->pquery('UPDATE vtiger_field SET fieldname = ? WHERE tabid = ? AND fieldname = ?', array('payment_direction', $module->id, 'direction'));
	$oldColumn = 'vtiger_payments:direction:direction:Payments_Direction:V';
	$newColumn = 'vtiger_payments:direction:payment_direction:Payments_Direction:V';
	foreach (array('vtiger_cvcolumnlist', 'vtiger_cvadvfilter') as $cvTable) {
		$adb->pquery("UPDATE $cvTable SET columnname = ? WHERE columnname = ?", array($newColumn, $oldColumn));
	}
	say('Renamed the Payments field to payment_direction.');
}

$picklist = $adb->pquery('SELECT picklistid FROM vtiger_picklist WHERE name = ?', array('payment_direction'));
if (!$adb->num_rows($picklist)) {
	// a plain field object: the lookup by name is cached and, right after the rename, still empty
	$field = new Vtiger_Field();
	$field->name = 'payment_direction';
	$field->uitype = 15;
	$field->setPicklistValues(array('Received', 'Paid'));
	if (!Vtiger_Utils::CheckTable('vtiger_payment_direction')) {
		fwrite(STDERR, "The picklist table was not created.\n");
		exit(1);
	}
	say('Created the Received / Paid picklist for Payments.');
}

// the shared table keeps In / Out, which is all Bank Transactions accepts
$extra = $adb->pquery("SELECT picklist_valueid FROM vtiger_direction WHERE direction IN ('Received', 'Paid')", array());
while ($row = $adb->fetch_array($extra)) {
	$adb->pquery('DELETE FROM vtiger_role2picklist WHERE picklistvalueid = ?', array($row['picklist_valueid']));
}
$adb->pquery("DELETE FROM vtiger_direction WHERE direction IN ('Received', 'Paid')", array());
say('Bank Transactions now offers In / Out only.');

create_tab_data_file();
create_parenttab_data_file();
say('Done. Clear test/templates_c/v7/* and reload.');
