<?php
/**
 * Foreign-currency support for the books: adds the "Exchange Rate" field to Payments (the rate on the day
 * the money moved, blank = the document's own rate, so no exchange difference). Documents are posted in the
 * base currency at the rate they carry, and the difference between the rate a receivable / payable was booked
 * at and the rate of the payment goes to the "Exchange Gain / Loss" ledger (created on first use).
 *
 * Run after bin/create-payments-module.php. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-multicurrency.php
 * Afterwards: clear test/templates_c/v7/*, and run bin/dump-db.sh if fresh clones should get it.
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
require_once __DIR__ . '/lib/module-setup.php';

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();
$say = function ($message) { echo $message . "\n"; };

$module = Vtiger_Module::getInstance('Payments');
if (!$module) {
	exit("The Payments module is missing: run bin/create-payments-module.php first.\n");
}
if (!Vtiger_Field::getInstance('settlement_rate', $module)) {
	$block = Vtiger_Block::getInstance('LBL_PAYMENTS_INFORMATION', $module);
	$field = new Vtiger_Field();
	$field->name = 'settlement_rate';
	$field->label = 'Exchange Rate';
	$field->table = 'vtiger_payments';
	$field->column = 'settlement_rate';
	$field->columntype = 'DECIMAL(16,6)';
	$field->uitype = 7;
	$field->typeofdata = 'N~O';
	$field->quickcreate = 1;
	$block->addField($field);
	$say('Field Exchange Rate added to Payments.');
}
$columns = $adb->getColumnNames('vtiger_payments');
if (!in_array('settlement_rate', $columns)) {
	exit("The column vtiger_payments.settlement_rate was not created.\n");
}
$say('Foreign currency support ready. Clear test/templates_c/v7/* and reload.');
