<?php
/**
 * Adds the "GSTIN" field to Accounts (the customer's GST identification number, printed on tax
 * invoices) and registers the handler that validates it on save. Safe to run more than once.
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

$module = Vtiger_Module::getInstance('Accounts');
if (!$module) {
	fwrite(STDERR, "Accounts module not found.\n");
	exit(1);
}

// 1. The field
if (Vtiger_Field::getInstance('gstin', $module)) {
	echo "Accounts.gstin already exists.\n";
} else {
	$block = Vtiger_Block::getInstance('LBL_ACCOUNT_INFORMATION', $module);
	if (!$block) {
		fwrite(STDERR, "Block LBL_ACCOUNT_INFORMATION not found on Accounts.\n");
		exit(1);
	}

	$field = new Vtiger_Field();
	$field->name = 'gstin';
	$field->label = 'GSTIN';
	$field->table = 'vtiger_account';
	$field->column = 'gstin';
	$field->columntype = 'VARCHAR(15)';
	$field->uitype = 1;
	$field->typeofdata = 'V~O~LE~15';
	$block->addField($field);
	echo "Added Accounts.gstin (block LBL_ACCOUNT_INFORMATION).\n";
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
