<?php
/**
 * Adds the "GSTIN" field to Accounts and Vendors (the GST identification number, printed on tax
 * invoices and on credit / debit notes) and registers the handler that validates it on save. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/add-account-gstin.php
 *
 * Afterwards run bin/dump-db.sh if you want fresh clones (bin/setup.sh) to get the field too.
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

global $current_user;
$current_user = Users::getActiveAdminUser();

// 1. The field, per module: module => array(block, table)
$targets = array(
	'Accounts' => array('LBL_ACCOUNT_INFORMATION', 'vtiger_account'),
	'Vendors' => array('LBL_VENDOR_INFORMATION', 'vtiger_vendor'),
);
foreach ($targets as $moduleName => $target) {
	$module = Vtiger_Module::getInstance($moduleName);
	if (!$module) {
		fwrite(STDERR, "$moduleName module not found.\n");
		exit(1);
	}
	if (Vtiger_Field::getInstance('gstin', $module)) {
		echo "$moduleName.gstin already exists.\n";
		continue;
	}
	$block = Vtiger_Block::getInstance($target[0], $module);
	if (!$block) {
		fwrite(STDERR, "Block {$target[0]} not found on $moduleName.\n");
		exit(1);
	}

	$field = new Vtiger_Field();
	$field->name = 'gstin';
	$field->label = 'GSTIN';
	$field->table = $target[1];
	$field->column = 'gstin';
	$field->columntype = 'VARCHAR(15)';
	$field->uitype = 1;
	$field->typeofdata = 'V~O~LE~15';
	$block->addField($field);
	echo "Added $moduleName.gstin (block {$target[0]}).\n";
}

// 2. The save-time validation (rejects a malformed GSTIN or a wrong check character, stores it upper-case)
global $adb;
$handlerClass = 'AccountsGSTINHandler';
$existing = $adb->pquery('SELECT 1 FROM vtiger_eventhandlers WHERE handler_class = ?', array($handlerClass));
if ($adb->num_rows($existing)) {
	echo "$handlerClass is already registered.\n";
} else {
	$em = new VTEventsManager($adb);
	$em->registerHandler('vtiger.entity.beforesave', 'modules/Accounts/AccountsGSTINHandler.php', $handlerClass);
	echo "Registered $handlerClass (vtiger.entity.beforesave).\n";
}

echo "Done. Clear test/templates_c/v7/* and reload.\n";
