<?php
/**
 * Tax withheld at source: the sections table (code, description, rate), a "Withholding Section" and a
 * "Tax Withheld" field on Payments, and a starter list of sections (edit them in Ledgers > Withholding Sections).
 *
 * Run after bin/create-payments-module.php. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-withholding.php
 * Afterwards: clear test/templates_c/v7/*.
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

Vtiger_Utils::CreateTable('vtiger_tds_sections', "(
	section_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	code VARCHAR(20) NOT NULL,
	description VARCHAR(200) DEFAULT NULL,
	rate DECIMAL(7,3) NOT NULL DEFAULT 0,
	active TINYINT(1) NOT NULL DEFAULT 1,
	UNIQUE KEY uq_code (code)
)", true);
$exists = $adb->pquery("SHOW TABLES LIKE 'vtiger_tds_sections'");
if (!$adb->num_rows($exists)) {
	exit("The table vtiger_tds_sections was not created.\n");
}
$count = $adb->pquery('SELECT COUNT(*) FROM vtiger_tds_sections');
if (!(int)$adb->query_result($count, 0, 0)) {
	foreach (array(array('194C', 'Payments to contractors', 2), array('194H', 'Commission or brokerage', 5), array('194I', 'Rent', 10), array('194J', 'Professional and technical fees', 10), array('194Q', 'Purchase of goods', 0.1)) as $s) {
		$adb->pquery('INSERT INTO vtiger_tds_sections (code, description, rate, active) VALUES (?, ?, ?, 1)', $s);
	}
	$say('Starter sections added (check the rates for your case).');
}

$module = Vtiger_Module::getInstance('Payments');
if (!$module) {
	exit("The Payments module is missing: run bin/create-payments-module.php first.\n");
}
$block = Vtiger_Block::getInstance('LBL_PAYMENTS_INFORMATION', $module);
$codes = array();
$result = $adb->pquery('SELECT code FROM vtiger_tds_sections ORDER BY code');
while ($row = $adb->fetch_array($result)) {
	$codes[] = $row['code'];
}
if (!Vtiger_Field::getInstance('tds_section', $module)) {
	$field = new Vtiger_Field();
	$field->name = 'tds_section';
	$field->label = 'Withholding Section';
	$field->table = 'vtiger_payments';
	$field->column = 'tds_section';
	$field->columntype = 'VARCHAR(20)';
	$field->uitype = 15;
	$field->typeofdata = 'V~O';
	$field->quickcreate = 1;
	$block->addField($field);
	$field->setPicklistValues($codes);
	$say('Field Withholding Section added to Payments.');
}
if (!Vtiger_Field::getInstance('tds_amount', $module)) {
	$field = new Vtiger_Field();
	$field->name = 'tds_amount';
	$field->label = 'Tax Withheld';
	$field->table = 'vtiger_payments';
	$field->column = 'tds_amount';
	$field->columntype = 'DECIMAL(25,8)';
	$field->uitype = 7;
	$field->typeofdata = 'N~O';
	$field->quickcreate = 1;
	$block->addField($field);
	$say('Field Tax Withheld added to Payments.');
}
$columns = $adb->getColumnNames('vtiger_payments');
if (!in_array('tds_section', $columns) || !in_array('tds_amount', $columns)) {
	exit("The Payments columns were not created.\n");
}
addIndex('vtiger_payments', 'idx_tds', 'tds_section');
$say('Withholding tax ready. Clear test/templates_c/v7/* and reload.');
