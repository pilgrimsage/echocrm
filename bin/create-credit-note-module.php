<?php
/**
 * Registers the Credit Note module (an inventory module modelled on Invoice) in the database:
 * module, tables, blocks, fields, picklists, default list view, numbering (CN1, CN2, ...),
 * web service entity, menu entry (Inventory) and the related lists on Invoice.
 *
 * The PHP and template files live in modules/CreditNote and layouts/v7/modules/CreditNote and are
 * already in the repository; this script only does what cannot be committed as files.
 *
 * Safe to run more than once: every step checks what already exists.
 *
 * Usage (from the project root):   php bin/create-credit-note-module.php
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
include_once 'modules/ModComments/ModComments.php';

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

function say($message) {
	echo $message . "\n";
}

$moduleName = 'CreditNote';

// ---------------------------------------------------------------------------------------------
// 1. Module and tables
// ---------------------------------------------------------------------------------------------
$module = Vtiger_Module::getInstance($moduleName);
if (!$module) {
	$module = new Vtiger_Module();
	$module->name = $moduleName;
	$module->label = 'CreditNote';
	$module->parent = 'Sales';
	$module->isentitytype = true;
	$module->save();
	say("Created module $moduleName (tabid {$module->id}).");
} else {
	say("Module $moduleName already exists (tabid {$module->id}).");
}
$module->initTables();           // vtiger_creditnote, vtiger_creditnotecf (CREATE ... IF NOT EXISTS)
// Column the shared inventory save (saveInventoryProductDetails) writes on every save. It is not a
// registered field, so only a table copied from Invoice's has it; without it the whole totals UPDATE
// fails ("unknown column") and subtotal, total, tax type and region are silently lost.
Vtiger_Utils::AddColumn('vtiger_creditnote', 'compound_taxes_info', 'TEXT');
Vtiger_Utils::CreateTable('vtiger_creditnotebillads', '(creditnotebilladdressid INT(19) PRIMARY KEY)', true);
Vtiger_Utils::CreateTable('vtiger_creditnoteshipads', '(creditnoteshipaddressid INT(19) PRIMARY KEY)', true);

// ---------------------------------------------------------------------------------------------
// 2. Blocks (the order is the order they appear on the form)
// ---------------------------------------------------------------------------------------------
$blockLabels = array(
	'LBL_CREDITNOTE_INFORMATION', 'LBL_CUSTOM_INFORMATION', 'LBL_ADDRESS_INFORMATION',
	'LBL_ITEM_DETAILS', 'LBL_TERMS_INFORMATION', 'LBL_DESCRIPTION_INFORMATION',
);
$blocks = array();
foreach ($blockLabels as $index => $label) {
	$block = Vtiger_Block::getInstance($label, $module);
	if (!$block) {
		$block = new Vtiger_Block();
		$block->label = $label;
		$block->sequence = $index + 1;
		$module->addBlock($block);
		say("  block $label created");
	}
	$blocks[$label] = $block;
}

// ---------------------------------------------------------------------------------------------
// 3. Fields. Attributes follow Invoice's own (vtiger_field), so the shared inventory screens treat
//    them the same way. Columns are only created for the new tables; fields on vtiger_crmentity or
//    vtiger_inventoryproductrel just register against the existing columns.
//    keys: name, label, block, uitype, type (typeofdata), col, ctype, table, dt (displaytype),
//          pr (presence), qc (quickcreate), me (masseditable), sm (summaryfield), dv (default)
// ---------------------------------------------------------------------------------------------
$I = 'LBL_CREDITNOTE_INFORMATION';
$A = 'LBL_ADDRESS_INFORMATION';
$D = 'LBL_ITEM_DETAILS';
$billads = 'vtiger_creditnotebillads';
$shipads = 'vtiger_creditnoteshipads';
$rel = 'vtiger_inventoryproductrel';

$fields = array(
	// --- Credit note details
	array('name' => 'subject', 'label' => 'Subject', 'block' => $I, 'uitype' => 2, 'type' => 'V~M', 'col' => 'subject', 'ctype' => 'VARCHAR(100)', 'dt' => 1, 'pr' => 0, 'qc' => 3, 'me' => 1, 'sm' => 1),
	array('name' => 'creditnote_no', 'label' => 'Credit Note No', 'block' => $I, 'uitype' => 4, 'type' => 'V~O', 'col' => 'creditnote_no', 'ctype' => 'VARCHAR(100)', 'dt' => 1, 'pr' => 0, 'qc' => 3, 'me' => 0, 'sm' => 1),
	array('name' => 'invoice_id', 'label' => 'Invoice', 'block' => $I, 'uitype' => 10, 'type' => 'I~M', 'col' => 'invoiceid', 'ctype' => 'INT(19)', 'dt' => 1, 'pr' => 0, 'qc' => 3, 'me' => 0, 'sm' => 1, 'related' => array('Invoice')),
	array('name' => 'contact_id', 'label' => 'Contact Name', 'block' => $I, 'uitype' => 57, 'type' => 'I~O', 'col' => 'contactid', 'ctype' => 'INT(19)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'creditnotedate', 'label' => 'Credit Note Date', 'block' => $I, 'uitype' => 5, 'type' => 'D~O', 'col' => 'creditnotedate', 'ctype' => 'DATE', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'reason', 'label' => 'Reason', 'block' => $I, 'uitype' => 15, 'type' => 'V~O', 'col' => 'reason', 'ctype' => 'VARCHAR(200)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0,
		'picklist' => array('Sales Return', 'Post Sale Discount', 'Deficiency in Services', 'Correction in Invoice', 'Change in POS', 'Finalization of Provisional Assessment', 'Others')),
	array('name' => 'account_id', 'label' => 'Account Name', 'block' => $I, 'uitype' => 73, 'type' => 'I~M', 'col' => 'accountid', 'ctype' => 'INT(19)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'creditnotestatus', 'label' => 'Status', 'block' => $I, 'uitype' => 15, 'type' => 'V~O', 'col' => 'creditnotestatus', 'ctype' => 'VARCHAR(200)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0,
		'picklist' => array('Created', 'Approved', 'Sent', 'Cancelled')),
	array('name' => 'assigned_user_id', 'label' => 'Assigned To', 'block' => $I, 'uitype' => 53, 'type' => 'V~M', 'col' => 'smownerid', 'table' => 'vtiger_crmentity', 'dt' => 1, 'pr' => 0, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'createdtime', 'label' => 'Created Time', 'block' => $I, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'createdtime', 'table' => 'vtiger_crmentity', 'dt' => 2, 'pr' => 0, 'qc' => 3, 'me' => 0, 'sm' => 0),
	array('name' => 'modifiedtime', 'label' => 'Modified Time', 'block' => $I, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'modifiedtime', 'table' => 'vtiger_crmentity', 'dt' => 2, 'pr' => 0, 'qc' => 3, 'me' => 0, 'sm' => 0),
	array('name' => 'modifiedby', 'label' => 'Last Modified By', 'block' => $I, 'uitype' => 52, 'type' => 'V~O', 'col' => 'modifiedby', 'table' => 'vtiger_crmentity', 'dt' => 3, 'pr' => 0, 'qc' => 3, 'me' => 0, 'sm' => 0),
	array('name' => 'currency_id', 'label' => 'Currency', 'block' => $I, 'uitype' => 117, 'type' => 'I~O', 'col' => 'currency_id', 'ctype' => 'INT(19)', 'dt' => 3, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0, 'dv' => '1'),
	array('name' => 'conversion_rate', 'label' => 'Conversion Rate', 'block' => $I, 'uitype' => 1, 'type' => 'N~O', 'col' => 'conversion_rate', 'ctype' => 'DECIMAL(10,3)', 'dt' => 3, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0, 'dv' => '1'),
	array('name' => 'pre_tax_total', 'label' => 'Pre Tax Total', 'block' => $I, 'uitype' => 72, 'type' => 'N~O', 'col' => 'pre_tax_total', 'ctype' => 'DECIMAL(25,8)', 'dt' => 3, 'pr' => 2, 'qc' => 1, 'me' => 1, 'sm' => 0),
	array('name' => 'hdnSubTotal', 'label' => 'Sub Total', 'block' => $I, 'uitype' => 72, 'type' => 'N~O', 'col' => 'subtotal', 'ctype' => 'DECIMAL(25,8)', 'dt' => 3, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'hdnGrandTotal', 'label' => 'Total', 'block' => $I, 'uitype' => 72, 'type' => 'N~O', 'col' => 'total', 'ctype' => 'DECIMAL(25,8)', 'dt' => 3, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 1),
	array('name' => 'txtAdjustment', 'label' => 'Adjustment', 'block' => $I, 'uitype' => 72, 'type' => 'NN~O', 'col' => 'adjustment', 'ctype' => 'DECIMAL(25,8)', 'dt' => 3, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'hdnTaxType', 'label' => 'Tax Type', 'block' => $I, 'uitype' => 16, 'type' => 'V~O', 'col' => 'taxtype', 'ctype' => 'VARCHAR(25)', 'dt' => 3, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'source', 'label' => 'Source', 'block' => $I, 'uitype' => 1, 'type' => 'V~O', 'col' => 'source', 'table' => 'vtiger_crmentity', 'dt' => 2, 'pr' => 2, 'qc' => 3, 'me' => 0, 'sm' => 0),
	array('name' => 'starred', 'label' => 'Starred', 'block' => $I, 'uitype' => 56, 'type' => 'C~O', 'col' => 'starred', 'table' => 'vtiger_crmentity_user_field', 'dt' => 6, 'pr' => 2, 'qc' => 3, 'me' => 0, 'sm' => 0),
	array('name' => 'tags', 'label' => 'tags', 'block' => $I, 'uitype' => 1, 'type' => 'V~O', 'col' => 'tags', 'ctype' => 'VARCHAR(1)', 'dt' => 6, 'pr' => 2, 'qc' => 3, 'me' => 0, 'sm' => 0),

	// --- Addresses (bill-to / ship-to, as on an Invoice)
	array('name' => 'bill_street', 'label' => 'Billing Address', 'block' => $A, 'uitype' => 24, 'type' => 'V~M', 'col' => 'bill_street', 'table' => $billads, 'ctype' => 'VARCHAR(250)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'ship_street', 'label' => 'Shipping Address', 'block' => $A, 'uitype' => 24, 'type' => 'V~M', 'col' => 'ship_street', 'table' => $shipads, 'ctype' => 'VARCHAR(250)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'bill_pobox', 'label' => 'Billing PO Box', 'block' => $A, 'uitype' => 1, 'type' => 'V~O', 'col' => 'bill_pobox', 'table' => $billads, 'ctype' => 'VARCHAR(30)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'ship_pobox', 'label' => 'Shipping PO Box', 'block' => $A, 'uitype' => 1, 'type' => 'V~O', 'col' => 'ship_pobox', 'table' => $shipads, 'ctype' => 'VARCHAR(30)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'bill_city', 'label' => 'Billing City', 'block' => $A, 'uitype' => 1, 'type' => 'V~O', 'col' => 'bill_city', 'table' => $billads, 'ctype' => 'VARCHAR(30)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'ship_city', 'label' => 'Shipping City', 'block' => $A, 'uitype' => 1, 'type' => 'V~O', 'col' => 'ship_city', 'table' => $shipads, 'ctype' => 'VARCHAR(30)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'bill_state', 'label' => 'Billing State', 'block' => $A, 'uitype' => 1, 'type' => 'V~O', 'col' => 'bill_state', 'table' => $billads, 'ctype' => 'VARCHAR(30)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'ship_state', 'label' => 'Shipping State', 'block' => $A, 'uitype' => 1, 'type' => 'V~O', 'col' => 'ship_state', 'table' => $shipads, 'ctype' => 'VARCHAR(30)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'bill_code', 'label' => 'Billing Code', 'block' => $A, 'uitype' => 1, 'type' => 'V~O', 'col' => 'bill_code', 'table' => $billads, 'ctype' => 'VARCHAR(30)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'ship_code', 'label' => 'Shipping Code', 'block' => $A, 'uitype' => 1, 'type' => 'V~O', 'col' => 'ship_code', 'table' => $shipads, 'ctype' => 'VARCHAR(30)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'bill_country', 'label' => 'Billing Country', 'block' => $A, 'uitype' => 1, 'type' => 'V~O', 'col' => 'bill_country', 'table' => $billads, 'ctype' => 'VARCHAR(100)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'ship_country', 'label' => 'Shipping Country', 'block' => $A, 'uitype' => 1, 'type' => 'V~O', 'col' => 'ship_country', 'table' => $shipads, 'ctype' => 'VARCHAR(100)', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'hdnS_H_Amount', 'label' => 'S&H Amount', 'block' => $A, 'uitype' => 72, 'type' => 'N~O', 'col' => 's_h_amount', 'ctype' => 'DECIMAL(25,8)', 'dt' => 3, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),

	// --- Line items: columns live in vtiger_inventoryproductrel (shared by every inventory module)
	array('name' => 'productid', 'label' => 'Item Name', 'block' => $D, 'uitype' => 10, 'type' => 'V~M', 'col' => 'productid', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'quantity', 'label' => 'Quantity', 'block' => $D, 'uitype' => 7, 'type' => 'N~O', 'col' => 'quantity', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'listprice', 'label' => 'List Price', 'block' => $D, 'uitype' => 71, 'type' => 'N~O', 'col' => 'listprice', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'comment', 'label' => 'Item Comment', 'block' => $D, 'uitype' => 19, 'type' => 'V~O', 'col' => 'comment', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'discount_amount', 'label' => 'Item Discount Amount', 'block' => $D, 'uitype' => 71, 'type' => 'N~O', 'col' => 'discount_amount', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'discount_percent', 'label' => 'Item Discount Percent', 'block' => $D, 'uitype' => 7, 'type' => 'V~O', 'col' => 'discount_percent', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'tax1', 'label' => 'VAT', 'block' => $D, 'uitype' => 83, 'type' => 'V~O', 'col' => 'tax1', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'tax2', 'label' => 'Sales', 'block' => $D, 'uitype' => 83, 'type' => 'V~O', 'col' => 'tax2', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'tax3', 'label' => 'Service', 'block' => $D, 'uitype' => 83, 'type' => 'V~O', 'col' => 'tax3', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'hdnS_H_Percent', 'label' => 'S&H Percent', 'block' => $D, 'uitype' => 1, 'type' => 'N~O', 'col' => 's_h_percent', 'ctype' => 'DECIMAL(25,8)', 'dt' => 5, 'pr' => 2, 'qc' => 0, 'me' => 0, 'sm' => 0),
	array('name' => 'hdnDiscountAmount', 'label' => 'Discount Amount', 'block' => $D, 'uitype' => 72, 'type' => 'N~O', 'col' => 'discount_amount', 'ctype' => 'DECIMAL(25,8)', 'dt' => 5, 'pr' => 2, 'qc' => 3, 'me' => 0, 'sm' => 0),
	array('name' => 'hdnDiscountPercent', 'label' => 'Discount Percent', 'block' => $D, 'uitype' => 1, 'type' => 'N~O', 'col' => 'discount_percent', 'ctype' => 'DECIMAL(25,3)', 'dt' => 5, 'pr' => 2, 'qc' => 3, 'me' => 0, 'sm' => 0),
	array('name' => 'image', 'label' => 'Image', 'block' => $D, 'uitype' => 56, 'type' => 'V~O', 'col' => 'image', 'table' => $rel, 'dt' => 5, 'pr' => 1, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'purchase_cost', 'label' => 'Purchase Cost', 'block' => $D, 'uitype' => 71, 'type' => 'N~O', 'col' => 'purchase_cost', 'table' => $rel, 'dt' => 5, 'pr' => 1, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'margin', 'label' => 'Margin', 'block' => $D, 'uitype' => 71, 'type' => 'N~O', 'col' => 'margin', 'table' => $rel, 'dt' => 5, 'pr' => 1, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'region_id', 'label' => 'Tax Region', 'block' => $D, 'uitype' => 16, 'type' => 'N~O', 'col' => 'region_id', 'ctype' => 'INT(19)', 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'tax4', 'label' => 'CGST', 'block' => $D, 'uitype' => 83, 'type' => 'V~O', 'col' => 'tax4', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'tax5', 'label' => 'SGST', 'block' => $D, 'uitype' => 83, 'type' => 'V~O', 'col' => 'tax5', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'tax6', 'label' => 'IGST', 'block' => $D, 'uitype' => 83, 'type' => 'V~O', 'col' => 'tax6', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),
	array('name' => 'tax7', 'label' => 'US Sales Tax', 'block' => $D, 'uitype' => 83, 'type' => 'V~O', 'col' => 'tax7', 'table' => $rel, 'dt' => 5, 'pr' => 2, 'qc' => 1, 'me' => 0, 'sm' => 0),

	// --- Terms and description
	array('name' => 'terms_conditions', 'label' => 'Terms & Conditions', 'block' => 'LBL_TERMS_INFORMATION', 'uitype' => 19, 'type' => 'V~O', 'col' => 'terms_conditions', 'ctype' => 'TEXT', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
	array('name' => 'description', 'label' => 'Description', 'block' => 'LBL_DESCRIPTION_INFORMATION', 'uitype' => 19, 'type' => 'V~O', 'col' => 'description', 'table' => 'vtiger_crmentity', 'dt' => 1, 'pr' => 2, 'qc' => 3, 'me' => 1, 'sm' => 0),
);

$fieldInstances = array();
foreach ($fields as $spec) {
	$field = Vtiger_Field::getInstance($spec['name'], $module);
	if (!$field) {
		$field = new Vtiger_Field();
		$field->name = $spec['name'];
		$field->label = $spec['label'];
		$field->uitype = $spec['uitype'];
		$field->typeofdata = $spec['type'];
		$field->column = $spec['col'];
		$field->table = isset($spec['table']) ? $spec['table'] : 'vtiger_creditnote';
		$field->columntype = isset($spec['ctype']) ? $spec['ctype'] : false;
		$field->displaytype = $spec['dt'];
		$field->presence = $spec['pr'];
		$field->quickcreate = $spec['qc'];
		$field->masseditable = $spec['me'];
		$field->summaryfield = $spec['sm'];
		$field->readonly = 1;
		if (isset($spec['dv'])) {
			$field->defaultvalue = $spec['dv'];
		}
		// no column to create for fields that live in a table that already has the column
		if (!isset($spec['ctype'])) {
			$field->columntype = 'VARCHAR(100)'; // harmless: AddColumn skips a column that exists
		}
		$blocks[$spec['block']]->addField($field);
		if (!empty($spec['related'])) {
			$field->setRelatedModules($spec['related']);
		}
		if (!empty($spec['picklist'])) {
			$field->setPicklistValues($spec['picklist']);
		}
		say("  field {$spec['name']} created");
	}
	$fieldInstances[$spec['name']] = $field;
}

// ---------------------------------------------------------------------------------------------
// 4. Entity identifier, default list view, sharing, tools, numbering, web service
// ---------------------------------------------------------------------------------------------
$module->setEntityIdentifier($fieldInstances['subject']);

$filter = Vtiger_Filter::getInstance('All', $module);
if (!$filter) {
	$filter = new Vtiger_Filter();
	$filter->name = 'All';
	$filter->isdefault = true;
	$module->addFilter($filter);
	foreach (array('creditnote_no', 'subject', 'invoice_id', 'account_id', 'creditnotestatus', 'hdnGrandTotal', 'assigned_user_id') as $position => $fieldName) {
		$filter->addField($fieldInstances[$fieldName], $position);
	}
	say('  default list view "All" created');
}

// same default as Invoice (vtiger_def_org_share permission 2): visible to whoever can see invoices
$module->setDefaultSharing('Public_ReadWriteDelete');
$module->enableTools(array('Import', 'Export'));

$numbering = $adb->pquery('SELECT 1 FROM vtiger_modentity_num WHERE semodule = ?', array($moduleName));
if (!$adb->num_rows($numbering)) {
	// same row CRMEntity::setModuleSeqNumber('configure', ...) would write (prefix CN, starting at 1)
	$numberId = $adb->getUniqueId('vtiger_modentity_num');
	$adb->pquery('INSERT INTO vtiger_modentity_num VALUES (?,?,?,?,?,?)', array($numberId, $moduleName, 'CN', 1, 1, 1));
	say('  numbering configured: CN1, CN2, ...');
}

$ws = $adb->pquery('SELECT 1 FROM vtiger_ws_entity WHERE name = ?', array($moduleName));
if (!$adb->num_rows($ws)) {
	$module->initWebservice();
}
// inventory modules use the line-item aware web service handler
$adb->pquery('UPDATE vtiger_ws_entity SET handler_path = ?, handler_class = ? WHERE name = ?',
	array('include/Webservices/LineItem/VtigerInventoryOperation.php', 'VtigerInventoryOperation', $moduleName));

// ---------------------------------------------------------------------------------------------
// 5. Menu (Inventory app), related lists, comments
// ---------------------------------------------------------------------------------------------
$menu = $adb->pquery("SELECT 1 FROM vtiger_app2tab WHERE tabid = ? AND appname = 'INVENTORY'", array($module->id));
if (!$adb->num_rows($menu)) {
	$adb->pquery("INSERT INTO vtiger_app2tab (tabid, appname, sequence, visible) VALUES (?, 'INVENTORY', 6, 1)", array($module->id));
	say('  added to the Inventory menu');
}

// Setting the parent to 'Sales' makes vtlib add the module to the Sales app as well; Invoice itself
// is listed under Inventory only, so keep the Credit Note next to it.
$extra = $adb->pquery("SELECT 1 FROM vtiger_app2tab WHERE tabid = ? AND appname = 'SALES'", array($module->id));
if ($adb->num_rows($extra)) {
	$adb->pquery("DELETE FROM vtiger_app2tab WHERE tabid = ? AND appname = 'SALES'", array($module->id));
	say('  removed the automatic Sales menu entry');
}

$invoice = Vtiger_Module::getInstance('Invoice');
$exists = $adb->pquery('SELECT 1 FROM vtiger_relatedlists WHERE tabid = ? AND related_tabid = ?', array($invoice->id, $module->id));
if (!$adb->num_rows($exists)) {
	$invoice->setRelatedList($module, 'Credit Notes', array('ADD'), 'get_dependents_list');
	say('  related list "Credit Notes" added to Invoice');
}
$documents = Vtiger_Module::getInstance('Documents');
$exists = $adb->pquery('SELECT 1 FROM vtiger_relatedlists WHERE tabid = ? AND related_tabid = ?', array($module->id, $documents->id));
if (!$adb->num_rows($exists)) {
	$module->setRelatedList($documents, 'Documents', array('ADD', 'SELECT'), 'get_attachments');
	say('  related list Documents added');
}
ModComments::addWidgetTo($moduleName);

say("Done. Clear test/templates_c/v7/* and reload.");
