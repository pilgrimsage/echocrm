<?php
/**
 * Gives the Payments "Status" field a picklist of its own. The field was called `status`, which is
 * also the name of the shared Active/Inactive picklist table (`vtiger_status`, used by Bank Accounts
 * and Cost Centres), so Payments ended up with no values at all and the mandatory field could not
 * be filled on the edit form. The field is now `payment_status` (same database column, `status`),
 * with Completed / Pending / Failed. Existing payments keep their values. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/fix-payments-status.php
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

$field = Vtiger_Field::getInstance('payment_status', $module);
if (!$field) {
	$old = Vtiger_Field::getInstance('status', $module);
	if (!$old) {
		fwrite(STDERR, "Payments has neither a status nor a payment_status field.\n");
		exit(1);
	}
	$adb->pquery('UPDATE vtiger_field SET fieldname = ? WHERE tabid = ? AND fieldname = ?', array('payment_status', $module->id, 'status'));
	// saved list views that show the column
	$adb->pquery('UPDATE vtiger_cvcolumnlist SET columnname = ? WHERE columnname = ?',
		array('vtiger_payments:status:payment_status:Payments_Status:V', 'vtiger_payments:status:status:Payments_Status:V'));
	$adb->pquery('UPDATE vtiger_cvadvfilter SET columnname = ? WHERE columnname = ?',
		array('vtiger_payments:status:payment_status:Payments_Status:V', 'vtiger_payments:status:status:Payments_Status:V'));
	say('Renamed the field to payment_status.');
}

$picklist = $adb->pquery('SELECT picklistid FROM vtiger_picklist WHERE name = ?', array('payment_status'));
if (!$adb->num_rows($picklist)) {
	// a plain field object: the lookup by name is cached and, right after the rename, still empty
	$field = new Vtiger_Field();
	$field->name = 'payment_status';
	$field->uitype = 15;
	$field->setPicklistValues(array('Completed', 'Pending', 'Failed'));
	if (!Vtiger_Utils::CheckTable('vtiger_payment_status')) {
		fwrite(STDERR, "The picklist table was not created.\n");
		exit(1);
	}
	say('Created the Completed / Pending / Failed picklist.');
} else {
	say('The picklist exists.');
}

create_tab_data_file();
create_parenttab_data_file();
say('Done. Clear test/templates_c/v7/* and reload.');
