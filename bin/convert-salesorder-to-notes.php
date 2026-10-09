<?php
/**
 * Turns the Sales Order module into the Credit / Debit Note module. The module keeps its internal
 * name (SalesOrder) so URLs, permissions, the line-item engine and the PDF code stay as they are;
 * what changes is what it is used for:
 *   - Credit Note: issued to a customer against an Invoice (customer, invoice, reason).
 *   - Debit Note:  issued to a vendor against a Purchase Order (vendor, purchase order, reason).
 *
 * This script hides the order-only fields, adds the note fields, changes the status values, adds
 * the debit-note number series (DN, the credit-note series is CN) and registers the save-time
 * validation. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/convert-salesorder-to-notes.php
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

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

function say($message) {
	echo $message . "\n";
}

$module = Vtiger_Module::getInstance('SalesOrder');
if (!$module) {
	fwrite(STDERR, "SalesOrder module not found.\n");
	exit(1);
}
$tabId = $module->id;
$block = Vtiger_Block::getInstance('LBL_SO_INFORMATION', $module);

// ---------------------------------------------------------------------------------------------
// 1. Order-only fields are hidden (presence 1 keeps the column and any data, the screens skip it)
// ---------------------------------------------------------------------------------------------
$hide = array('potential_id', 'customerno', 'quote_id', 'vtiger_purchaseorder', 'carrier', 'pending',
	'salescommission', 'exciseduty', 'enable_recurring', 'recurring_frequency', 'start_period',
	'end_period', 'payment_duration', 'invoicestatus', 'last_recurring_date');
foreach ($hide as $name) {
	$adb->pquery('UPDATE vtiger_field SET presence = 1, typeofdata = REPLACE(typeofdata, "~M", "~O") WHERE tabid = ? AND fieldname = ?', array($tabId, $name));
}
say('Hid the order-only fields (' . count($hide) . ').');

// ---------------------------------------------------------------------------------------------
// 2. Relabel what stays. The account is only required for credit notes (checked on save).
// ---------------------------------------------------------------------------------------------
$adb->pquery('UPDATE vtiger_field SET fieldlabel = ? WHERE tabid = ? AND fieldname = ?', array('Note No', $tabId, 'salesorder_no'));
$adb->pquery('UPDATE vtiger_field SET fieldlabel = ? WHERE tabid = ? AND fieldname = ?', array('Note Date', $tabId, 'duedate'));
$adb->pquery('UPDATE vtiger_field SET typeofdata = ? WHERE tabid = ? AND fieldname = ?', array('I~O', $tabId, 'account_id'));
$adb->pquery('UPDATE vtiger_field SET typeofdata = ? WHERE tabid = ? AND fieldname = ?', array('V~M', $tabId, 'sostatus'));
$adb->pquery('UPDATE vtiger_field SET typeofdata = ? WHERE tabid = ? AND fieldname = ?', array('D~M', $tabId, 'duedate'));
$adb->pquery('UPDATE vtiger_sostatus SET sostatus = ? WHERE sostatus = ?', array('Sent', 'Delivered'));
$adb->pquery('UPDATE vtiger_salesorder SET sostatus = ? WHERE sostatus = ?', array('Sent', 'Delivered'));
say('Relabelled Note No / Note Date; statuses are Created, Approved, Sent, Cancelled.');

// ---------------------------------------------------------------------------------------------
// 3. New fields (in the details block, after the subject)
// ---------------------------------------------------------------------------------------------
$newFields = array(
	array('name' => 'note_type', 'label' => 'Note Type', 'uitype' => 16, 'type' => 'V~M', 'col' => 'note_type', 'ctype' => 'VARCHAR(20)',
		'picklist' => array('Credit Note', 'Debit Note'), 'dv' => 'Credit Note'),
	array('name' => 'invoice_id', 'label' => 'Invoice', 'uitype' => 10, 'type' => 'I~O', 'col' => 'invoiceid', 'ctype' => 'INT(19)', 'related' => array('Invoice')),
	array('name' => 'vendor_id', 'label' => 'Vendor Name', 'uitype' => 10, 'type' => 'I~O', 'col' => 'vendorid', 'ctype' => 'INT(19)', 'related' => array('Vendors')),
	array('name' => 'purchaseorder_id', 'label' => 'Purchase Order', 'uitype' => 10, 'type' => 'I~O', 'col' => 'purchaseorderid', 'ctype' => 'INT(19)', 'related' => array('PurchaseOrder')),
	array('name' => 'reason', 'label' => 'Reason', 'uitype' => 15, 'type' => 'V~O', 'col' => 'reason', 'ctype' => 'VARCHAR(200)',
		'picklist' => array('Sales Return', 'Purchase Return', 'Post Sale Discount', 'Deficiency in Services', 'Correction in Invoice', 'Change in POS', 'Finalization of Provisional Assessment', 'Others')),
);
foreach ($newFields as $spec) {
	if (Vtiger_Field::getInstance($spec['name'], $module)) {
		continue;
	}
	$field = new Vtiger_Field();
	$field->name = $spec['name'];
	$field->label = $spec['label'];
	$field->table = 'vtiger_salesorder';
	$field->column = $spec['col'];
	$field->columntype = $spec['ctype'];
	$field->uitype = $spec['uitype'];
	$field->typeofdata = $spec['type'];
	$field->displaytype = 1;
	$field->presence = 2;
	$field->quickcreate = 3;
	$field->masseditable = 0;
	$field->summaryfield = ($spec['name'] == 'note_type') ? 1 : 0;
	if (isset($spec['dv'])) {
		$field->defaultvalue = $spec['dv'];
	}
	$block->addField($field);
	if (!empty($spec['related'])) {
		$field->setRelatedModules($spec['related']);
	}
	if (!empty($spec['picklist'])) {
		$field->setPicklistValues($spec['picklist']);
	}
	say("  field {$spec['name']} created");
}

// vendorid is a leftover of the order's vendor terms and still has a foreign key to vtiger_vendor,
// which rejects the 0 vtiger stores for "no vendor" (every credit note). Reference fields in vtiger
// are plain integer columns; drop the constraint.
$fk = $adb->pquery("SELECT constraint_name FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = 'vtiger_salesorder' AND column_name = 'vendorid' AND referenced_table_name IS NOT NULL");
while ($fk && $row = $adb->fetch_array($fk)) {
	$adb->query('ALTER TABLE vtiger_salesorder DROP FOREIGN KEY `' . $row['constraint_name'] . '`');
	say('Dropped foreign key ' . $row['constraint_name'] . ' on vtiger_salesorder.vendorid.');
}

// ---------------------------------------------------------------------------------------------
// 4. Debit note number series (credit notes keep the SalesOrder row; its prefix becomes CN)
// ---------------------------------------------------------------------------------------------
$adb->pquery('UPDATE vtiger_modentity_num SET prefix = ? WHERE semodule = ? AND prefix = ?', array('CN', 'SalesOrder', 'SO'));
$debit = $adb->pquery('SELECT 1 FROM vtiger_modentity_num WHERE semodule = ?', array('SalesOrderDebit'));
if (!$adb->num_rows($debit)) {
	$numberId = $adb->getUniqueId('vtiger_modentity_num');
	$adb->pquery('INSERT INTO vtiger_modentity_num VALUES (?,?,?,?,?,?)', array($numberId, 'SalesOrderDebit', 'DN', 1, 1, 1));
	say('Numbering: credit notes CN1, CN2, ...; debit notes DN1, DN2, ...');
}

// ---------------------------------------------------------------------------------------------
// 5. List view columns, related lists
// ---------------------------------------------------------------------------------------------
$filter = Vtiger_Filter::getInstance('All', $module);
if ($filter) {
	$adb->pquery('DELETE FROM vtiger_cvcolumnlist WHERE cvid = ?', array($filter->id));
	$position = 0;
	foreach (array('salesorder_no', 'note_type', 'subject', 'account_id', 'vendor_id', 'sostatus', 'hdnGrandTotal', 'assigned_user_id') as $name) {
		$field = Vtiger_Field::getInstance($name, $module);
		if ($field) {
			$filter->addField($field, $position++);
		}
	}
	say('Default list view columns reset.');
}

// the demo "Pending Sales Orders" list is about orders
$pending = $adb->pquery("SELECT cvid FROM vtiger_customview WHERE entitytype = 'SalesOrder' AND viewname = 'Pending Sales Orders'");
while ($pending && $row = $adb->fetch_array($pending)) {
	foreach (array('vtiger_cvcolumnlist', 'vtiger_cvadvfilter', 'vtiger_cvadvfilter_grouping', 'vtiger_cvstdfilter') as $table) {
		$adb->pquery("DELETE FROM $table WHERE cvid = ?", array($row['cvid']));
	}
	$adb->pquery('DELETE FROM vtiger_customview WHERE cvid = ?', array($row['cvid']));
	say('Removed the "Pending Sales Orders" list.');
}

// Order related lists that no longer apply to a note
$adb->pquery('DELETE FROM vtiger_relatedlists WHERE related_tabid = ? AND tabid IN (SELECT tabid FROM vtiger_tab WHERE name IN ("Potentials","Quotes","Products","Services"))', array($tabId));
$adb->pquery('DELETE FROM vtiger_relatedlists WHERE tabid = ? AND related_tabid IN (SELECT tabid FROM vtiger_tab WHERE name IN ("Invoice"))', array($tabId));
$invoice = Vtiger_Module::getInstance('Invoice');
$exists = $adb->pquery('SELECT 1 FROM vtiger_relatedlists WHERE tabid = ? AND related_tabid = ?', array($invoice->id, $tabId));
if (!$adb->num_rows($exists)) {
	$invoice->setRelatedList($module, 'Credit Notes', array('ADD'), 'get_dependents_list');
}
$po = Vtiger_Module::getInstance('PurchaseOrder');
$exists = $adb->pquery('SELECT 1 FROM vtiger_relatedlists WHERE tabid = ? AND related_tabid = ?', array($po->id, $tabId));
if (!$adb->num_rows($exists)) {
	$po->setRelatedList($module, 'Debit Notes', array('ADD'), 'get_dependents_list');
}
say('Related lists: Credit Notes on Invoice, Debit Notes on Purchase Order.');

// ---------------------------------------------------------------------------------------------
// 6. Save-time validation (credit note needs customer + invoice, debit note needs vendor)
// ---------------------------------------------------------------------------------------------
$handlerClass = 'SalesOrderNoteHandler';
$existing = $adb->pquery('SELECT 1 FROM vtiger_eventhandlers WHERE handler_class = ?', array($handlerClass));
if (!$adb->num_rows($existing)) {
	$em = new VTEventsManager($adb);
	$em->registerHandler('vtiger.entity.beforesave', 'modules/SalesOrder/SalesOrderNoteHandler.php', $handlerClass);
	say("Registered $handlerClass.");
}

say('Done. Clear test/templates_c/v7/* and reload.');
