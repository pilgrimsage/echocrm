<?php
/**
 * Registers the Payments module: payments received for quotes, invoices and credit/debit notes
 * (money in) or paid for purchase orders and notes (money out), with the handler that validates
 * them and keeps the document's received/paid and balance up to date. Module, tables, fields,
 * picklists, default list view, numbering (PAY1, PAY2, ...), menu entry (Inventory), the Payments
 * related list on each document module and the event handler registration.
 *
 * The PHP files live in modules/Payments and languages/en_us/Payments.php. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-payments-module.php
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

$moduleName = 'Payments';

// 1. Module and tables
$module = Vtiger_Module::getInstance($moduleName);
if (!$module) {
	$module = new Vtiger_Module();
	$module->name = $moduleName;
	$module->label = 'Payments';
	$module->parent = 'Inventory';
	$module->isentitytype = true;
	$module->save();
	say("Created module $moduleName (tabid {$module->id}).");
}
$module->initTables();
require_once __DIR__ . '/lib/module-setup.php';
addCurrencyColumns('vtiger_payments');
addIndex('vtiger_payments', 'idx_related', 'related_to, status');

// 2. Blocks
$blocks = array();
foreach (array('LBL_PAYMENTS_INFORMATION', 'LBL_DESCRIPTION_INFORMATION') as $index => $label) {
	$block = Vtiger_Block::getInstance($label, $module);
	if (!$block) {
		$block = new Vtiger_Block();
		$block->label = $label;
		$block->sequence = $index + 1;
		$module->addBlock($block);
	}
	$blocks[$label] = $block;
}

// 3. Fields (qc = quick create, sm = summary field, dt = display type)
$I = 'LBL_PAYMENTS_INFORMATION';
$fields = array(
	array('name' => 'payment_no', 'label' => 'Payment No', 'block' => $I, 'uitype' => 4, 'type' => 'V~O', 'col' => 'payment_no', 'ctype' => 'VARCHAR(100)', 'dt' => 1, 'qc' => 3, 'sm' => 1),
	array('name' => 'related_to', 'label' => 'Document', 'block' => $I, 'uitype' => 10, 'type' => 'V~M', 'col' => 'related_to', 'ctype' => 'INT(19)', 'dt' => 1, 'qc' => 0, 'sm' => 1, 'related' => array('Invoice', 'Quotes', 'PurchaseOrder')),
	array('name' => 'direction', 'label' => 'Direction', 'block' => $I, 'uitype' => 15, 'type' => 'V~O', 'col' => 'direction', 'ctype' => 'VARCHAR(20)', 'dt' => 2, 'qc' => 3, 'sm' => 1, 'picklist' => array('Received', 'Paid')),
	array('name' => 'amount', 'label' => 'Amount', 'block' => $I, 'uitype' => 72, 'type' => 'N~M', 'col' => 'amount', 'ctype' => 'DECIMAL(25,8)', 'dt' => 1, 'qc' => 0, 'sm' => 1),
	array('name' => 'payment_date', 'label' => 'Payment Date', 'block' => $I, 'uitype' => 5, 'type' => 'D~M', 'col' => 'payment_date', 'ctype' => 'DATE', 'dt' => 1, 'qc' => 0, 'sm' => 1),
	array('name' => 'payment_method', 'label' => 'Payment Method', 'block' => $I, 'uitype' => 15, 'type' => 'V~M', 'col' => 'payment_method', 'ctype' => 'VARCHAR(50)', 'dt' => 1, 'qc' => 0, 'sm' => 0,
		'picklist' => array('Cash', 'Bank Transfer', 'UPI', 'Cheque', 'Card', 'Online Gateway', 'Other')),
	array('name' => 'reference_no', 'label' => 'Reference No', 'block' => $I, 'uitype' => 1, 'type' => 'V~O', 'col' => 'reference_no', 'ctype' => 'VARCHAR(100)', 'dt' => 1, 'qc' => 1, 'sm' => 0),
	array('name' => 'status', 'label' => 'Status', 'block' => $I, 'uitype' => 15, 'type' => 'V~M', 'col' => 'status', 'ctype' => 'VARCHAR(20)', 'dt' => 1, 'qc' => 0, 'sm' => 1, 'picklist' => array('Completed', 'Pending', 'Failed'), 'dv' => 'Completed'),
	array('name' => 'account_id', 'label' => 'Customer', 'block' => $I, 'uitype' => 10, 'type' => 'I~O', 'col' => 'account_id', 'ctype' => 'INT(19)', 'dt' => 2, 'qc' => 3, 'sm' => 0, 'related' => array('Accounts')),
	array('name' => 'vendor_id', 'label' => 'Vendor', 'block' => $I, 'uitype' => 10, 'type' => 'I~O', 'col' => 'vendor_id', 'ctype' => 'INT(19)', 'dt' => 2, 'qc' => 3, 'sm' => 0, 'related' => array('Vendors')),
	array('name' => 'assigned_user_id', 'label' => 'Assigned To', 'block' => $I, 'uitype' => 53, 'type' => 'V~M', 'col' => 'smownerid', 'table' => 'vtiger_crmentity', 'dt' => 1, 'qc' => 0, 'sm' => 0),
	array('name' => 'createdtime', 'label' => 'Created Time', 'block' => $I, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'createdtime', 'table' => 'vtiger_crmentity', 'dt' => 2, 'qc' => 3, 'sm' => 0),
	array('name' => 'modifiedtime', 'label' => 'Modified Time', 'block' => $I, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'modifiedtime', 'table' => 'vtiger_crmentity', 'dt' => 2, 'qc' => 3, 'sm' => 0),
	array('name' => 'modifiedby', 'label' => 'Last Modified By', 'block' => $I, 'uitype' => 52, 'type' => 'V~O', 'col' => 'modifiedby', 'table' => 'vtiger_crmentity', 'dt' => 3, 'qc' => 3, 'sm' => 0),
	array('name' => 'description', 'label' => 'Description', 'block' => 'LBL_DESCRIPTION_INFORMATION', 'uitype' => 19, 'type' => 'V~O', 'col' => 'description', 'table' => 'vtiger_crmentity', 'dt' => 1, 'qc' => 3, 'sm' => 0),
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
		$field->table = isset($spec['table']) ? $spec['table'] : 'vtiger_payments';
		$field->columntype = isset($spec['ctype']) ? $spec['ctype'] : 'VARCHAR(100)';
		$field->displaytype = $spec['dt'];
		$field->quickcreate = $spec['qc'];
		$field->summaryfield = $spec['sm'];
		if (isset($spec['dv'])) {
			$field->defaultvalue = $spec['dv'];
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
$module->setEntityIdentifier($fieldInstances['payment_no']);

// 4. Default list view, sharing, tools, numbering, web service
if (!Vtiger_Filter::getInstance('All', $module)) {
	$filter = new Vtiger_Filter();
	$filter->name = 'All';
	$filter->isdefault = true;
	$module->addFilter($filter);
	foreach (array('payment_no', 'related_to', 'direction', 'amount', 'payment_date', 'payment_method', 'status', 'assigned_user_id') as $position => $name) {
		$filter->addField($fieldInstances[$name], $position);
	}
}
$module->setDefaultSharing('Public_ReadWriteDelete');
$module->enableTools(array('Import', 'Export'));
$numbering = $adb->pquery('SELECT 1 FROM vtiger_modentity_num WHERE semodule = ?', array($moduleName));
if (!$adb->num_rows($numbering)) {
	$adb->pquery('INSERT INTO vtiger_modentity_num VALUES (?,?,?,?,?,?)', array($adb->getUniqueId('vtiger_modentity_num'), $moduleName, 'PAY', 1, 1, 1));
}
$ws = $adb->pquery('SELECT 1 FROM vtiger_ws_entity WHERE name = ?', array($moduleName));
if (!$adb->num_rows($ws)) {
	$module->initWebservice();
}

// 5. Menu (Inventory app, after Invoice)
$adb->pquery("DELETE FROM vtiger_app2tab WHERE tabid = ? AND appname != 'INVENTORY'", array($module->id));
$menu = $adb->pquery("SELECT 1 FROM vtiger_app2tab WHERE tabid = ? AND appname = 'INVENTORY'", array($module->id));
if (!$adb->num_rows($menu)) {
	$adb->pquery("INSERT INTO vtiger_app2tab (tabid, appname, sequence, visible) VALUES (?, 'INVENTORY', 7, 1)", array($module->id));
}

// 6. Related list on every document module
foreach (array('Invoice', 'Quotes', 'PurchaseOrder') as $documentName) {
	$document = Vtiger_Module::getInstance($documentName);
	$exists = $adb->pquery('SELECT 1 FROM vtiger_relatedlists WHERE tabid = ? AND related_tabid = ?', array($document->id, $module->id));
	if (!$adb->num_rows($exists)) {
		// no Add button: payments are recorded from the document's "Record Payment" button, which prefills them
		$document->setRelatedList($module, 'Payments', array(), 'get_dependents_list');
		say("  related list Payments added to $documentName");
	}
}
ModComments::addWidgetTo($moduleName);

// 7. Handler: validation before save, document refresh after save / delete / restore
$existing = $adb->pquery('SELECT 1 FROM vtiger_eventhandlers WHERE handler_class = ?', array('PaymentsHandler'));
if (!$adb->num_rows($existing)) {
	$em = new VTEventsManager($adb);
	foreach (array('vtiger.entity.beforesave', 'vtiger.entity.aftersave', 'vtiger.entity.afterdelete', 'vtiger.entity.afterrestore') as $event) {
		$em->registerHandler($event, 'modules/Payments/PaymentsHandler.php', 'PaymentsHandler');
	}
	say('Registered PaymentsHandler.');
}

create_tab_data_file();
create_parenttab_data_file();
say('Done. Clear test/templates_c/v7/* and reload.');
