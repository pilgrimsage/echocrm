<?php
/**
 * Switches on stock valuation (moving weighted average cost) and cost of goods sold:
 * the stock ledger tables, the "Track stock" field on Products (ticked for the products that exist),
 * the ledgers the stock postings use, and a repost of everything so existing documents get their moves.
 * vtiger's own workflows that adjusted the "quantity in stock" on invoice / purchase order saves are
 * switched off, because the stock ledger now keeps that figure.
 *
 * Run after bin/create-journal.php. Safe to run more than once. Enter opening stock afterwards
 * (Ledgers > Opening Stock / Adjustments) for products that already have stock.
 *
 * Usage (from the project root):   php bin/create-stock.php
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
include_once 'include/utils/LedgerUtils.php';

global $adb, $current_user, $currentModule;
$current_user = Users::getActiveAdminUser();

function say($message) {
	echo $message . "\n";
}

Vtiger_Utils::CreateTable('vtiger_stock_adjustments', "(
	adjustment_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	adjustment_date DATE NOT NULL,
	adjustment_type VARCHAR(30) NOT NULL,
	narration VARCHAR(200) DEFAULT NULL,
	counter_ledger INT(19) DEFAULT NULL,
	created_by INT(19) DEFAULT NULL,
	created_time DATETIME DEFAULT NULL
)", true);
Vtiger_Utils::CreateTable('vtiger_stock_moves', "(
	move_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	product_id INT(19) NOT NULL,
	move_date DATE NOT NULL,
	move_type VARCHAR(20) NOT NULL,
	source_module VARCHAR(30) NOT NULL,
	source_id INT(19) NOT NULL,
	source_line INT(11) NOT NULL DEFAULT 0,
	qty DECIMAL(25,3) NOT NULL,
	unit_cost DECIMAL(25,6) NOT NULL DEFAULT 0,
	value DECIMAL(25,2) NOT NULL DEFAULT 0,
	cost_mode CHAR(5) NOT NULL DEFAULT 'avg',
	balance_qty DECIMAL(25,3) NOT NULL DEFAULT 0,
	balance_value DECIMAL(25,2) NOT NULL DEFAULT 0,
	avg_cost DECIMAL(25,6) NOT NULL DEFAULT 0,
	created_time DATETIME DEFAULT NULL,
	KEY idx_product_date (product_id, move_date, move_id),
	KEY idx_source (source_module, source_id)
)", true);
say('Stock tables ready.');

// "Track stock" on Products
$products = Vtiger_Module::getInstance('Products');
if (!Vtiger_Field::getInstance('track_stock', $products)) {
	$block = Vtiger_Block::getInstance('LBL_STOCK_INFORMATION', $products);
	$field = new Vtiger_Field();
	$field->name = 'track_stock';
	$field->label = 'Track Stock';
	$field->table = 'vtiger_products';
	$field->column = 'track_stock';
	$field->columntype = 'TINYINT(1) DEFAULT 1';
	$field->uitype = 56;
	$field->typeofdata = 'C~O';
	$field->defaultvalue = '1';
	$block->addField($field);
	$adb->pquery('UPDATE vtiger_products SET track_stock = 1 WHERE track_stock IS NULL OR track_stock = 0');
	say('Added Products.track_stock (ticked for all existing products).');
}

// ledgers
foreach (array('Inventory' => 'Assets', 'Goods in Transit' => 'Assets', 'Cost of Goods Sold' => 'Expenses', 'Stock Adjustments and Write-offs' => 'Expenses', 'Opening Balance Equity' => 'Equity') as $name => $group) {
	Vtiger_Ledger_Utils::ledgerId($name, $group);
}

// vtiger's own quantity-in-stock workflows are replaced by the stock ledger
$adb->pquery("UPDATE com_vtiger_workflows SET status = 0 WHERE defaultworkflow = 1 AND module_name IN ('Invoice', 'PurchaseOrder') AND summary LIKE '%Update%Inventory%Products%'");
say('Switched off the default "Update Inventory Products" workflows (the stock ledger keeps the quantity now).');

addIndex('vtiger_products', 'idx_track_stock', 'track_stock');

$currentModule = 'Ledgers';
$counts = Vtiger_Ledger_Utils::rebuildAll();
say('Reposted: ' . implode(', ', array_map(function ($k, $v) { return "$k $v"; }, array_keys($counts), $counts)));
say('Done. Clear test/templates_c/v7/* and reload.');
