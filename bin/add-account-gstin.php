<?php
/**
 * Adds the "GSTIN" field to Accounts (the customer's GST identification number, printed on tax
 * invoices). Safe to run more than once: it does nothing if the field already exists.
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
if (Vtiger_Field::getInstance('gstin', $module)) {
	echo "Accounts.gstin already exists - nothing to do.\n";
	exit(0);
}

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

echo "Added Accounts.gstin (block LBL_ACCOUNT_INFORMATION). Clear test/templates_c/v7/* and reload.\n";
