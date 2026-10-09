<?php
/**
 * Adds the cost centre / project dimension to the books: the Cost Centres module (type Cost Centre,
 * Project, Branch or Department, optional parent, dates, budget and a link to a CRM Project), a
 * "Cost Centre" field on quotes, invoices, purchase orders, credit/debit notes and bank transactions
 * (payments take their document's), the dimension column on journal lines, the monthly totals per
 * cost centre, and a repost of everything so existing records are tagged.
 *
 * Run after bin/create-journal.php. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-cost-centres.php
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
require_once __DIR__ . '/lib/module-setup.php';
include_once 'include/utils/LedgerUtils.php';

global $adb, $current_user, $currentModule;
$current_user = Users::getActiveAdminUser();

function say($message) {
	echo $message . "\n";
}

// 1. The module
$B = 'LBL_COSTCENTRES_INFORMATION';
$result = registerEntityModule(array(
	'name' => 'CostCentres', 'label' => 'Cost Centres', 'parent' => 'Inventory', 'menu_sequence' => 11, 'prefix' => 'CC',
	'blocks' => array($B, 'LBL_DESCRIPTION_INFORMATION'),
	'entity' => 'costcentre_name', 'list_columns' => array('costcentre_no', 'costcentre_name', 'dimension_type', 'parent_costcentre', 'status', 'budget', 'assigned_user_id'),
	'fields' => array(
		array('name' => 'costcentre_name', 'label' => 'Name', 'block' => $B, 'uitype' => 2, 'type' => 'V~M', 'col' => 'costcentre_name', 'ctype' => 'VARCHAR(200)', 'qc' => 0, 'sm' => 1),
		array('name' => 'costcentre_no', 'label' => 'Code', 'block' => $B, 'uitype' => 4, 'type' => 'V~O', 'col' => 'costcentre_no', 'sm' => 1),
		array('name' => 'dimension_type', 'label' => 'Type', 'block' => $B, 'uitype' => 15, 'type' => 'V~M', 'col' => 'dimension_type', 'ctype' => 'VARCHAR(30)', 'qc' => 0, 'sm' => 1,
			'picklist' => array('Cost Centre', 'Project', 'Branch', 'Department')),
		array('name' => 'status', 'label' => 'Status', 'block' => $B, 'uitype' => 15, 'type' => 'V~M', 'col' => 'status', 'ctype' => 'VARCHAR(20)', 'qc' => 0, 'dv' => 'Active', 'picklist' => array('Active', 'Closed')),
		array('name' => 'parent_costcentre', 'label' => 'Parent', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'parent_costcentre', 'ctype' => 'INT(19)', 'qc' => 1, 'related' => array('CostCentres')),
		array('name' => 'start_date', 'label' => 'Start Date', 'block' => $B, 'uitype' => 5, 'type' => 'D~O', 'col' => 'start_date', 'ctype' => 'DATE'),
		array('name' => 'end_date', 'label' => 'End Date', 'block' => $B, 'uitype' => 5, 'type' => 'D~O', 'col' => 'end_date', 'ctype' => 'DATE'),
		array('name' => 'budget', 'label' => 'Budget', 'block' => $B, 'uitype' => 72, 'type' => 'N~O', 'col' => 'budget', 'ctype' => 'DECIMAL(25,8)', 'sm' => 1),
		array('name' => 'linked_project', 'label' => 'Linked Project', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'linked_project', 'ctype' => 'INT(19)', 'related' => array('Project')),
		array('name' => 'assigned_user_id', 'label' => 'Assigned To', 'block' => $B, 'uitype' => 53, 'type' => 'V~M', 'col' => 'smownerid', 'table' => 'vtiger_crmentity', 'qc' => 0),
		array('name' => 'createdtime', 'label' => 'Created Time', 'block' => $B, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'createdtime', 'table' => 'vtiger_crmentity', 'dt' => 2),
		array('name' => 'modifiedtime', 'label' => 'Modified Time', 'block' => $B, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'modifiedtime', 'table' => 'vtiger_crmentity', 'dt' => 2),
		array('name' => 'modifiedby', 'label' => 'Last Modified By', 'block' => $B, 'uitype' => 52, 'type' => 'V~O', 'col' => 'modifiedby', 'table' => 'vtiger_crmentity', 'dt' => 3),
		array('name' => 'description', 'label' => 'Description', 'block' => 'LBL_DESCRIPTION_INFORMATION', 'uitype' => 19, 'type' => 'V~O', 'col' => 'description', 'table' => 'vtiger_crmentity'),
	),
));
$costCentres = $result['module'];
addCurrencyColumns('vtiger_costcentres');
addIndex('vtiger_costcentres', 'idx_parent', 'parent_costcentre');
ModComments::addWidgetTo('CostCentres');
say('Cost Centres module ready.');

// 2. The field on the records that carry the dimension
$carriers = array(
	'Invoice' => 'LBL_INVOICE_INFORMATION', 'PurchaseOrder' => 'LBL_PO_INFORMATION', 'Quotes' => 'LBL_QUOTE_INFORMATION',
	'SalesOrder' => 'LBL_SO_INFORMATION', 'BankTransactions' => 'LBL_BANKTRANSACTIONS_INFORMATION', 'Payments' => 'LBL_PAYMENTS_INFORMATION',
);
foreach ($carriers as $moduleName => $blockLabel) {
	$module = Vtiger_Module::getInstance($moduleName);
	if (!$module) {
		continue;
	}
	$field = Vtiger_Field::getInstance('cost_centre', $module);
	if (!$field) {
		$block = Vtiger_Block::getInstance($blockLabel, $module);
		$table = array('Invoice' => 'vtiger_invoice', 'PurchaseOrder' => 'vtiger_purchaseorder', 'Quotes' => 'vtiger_quotes', 'SalesOrder' => 'vtiger_salesorder',
			'BankTransactions' => 'vtiger_banktransactions', 'Payments' => 'vtiger_payments')[$moduleName];
		$field = new Vtiger_Field();
		$field->name = 'cost_centre';
		$field->label = 'Cost Centre';
		$field->table = $table;
		$field->column = 'cost_centre';
		$field->columntype = 'INT(19)';
		$field->uitype = 10;
		$field->typeofdata = 'I~O';
		$field->quickcreate = 1;
		// a payment takes its document's cost centre (written after the save), so it is shown but not edited
		$field->displaytype = $moduleName == 'Payments' ? 2 : 1;
		$block->addField($field);
		$field->setRelatedModules(array('CostCentres'));
		say("  field Cost Centre added to $moduleName");
	}
	$rel = $adb->pquery('SELECT 1 FROM vtiger_relatedlists WHERE tabid = ? AND related_tabid = ?', array($costCentres->id, $module->id));
	if (!$adb->num_rows($rel)) {
		$costCentres->setRelatedList($module, $moduleName == 'SalesOrder' ? 'Credit/Debit Notes' : ($moduleName == 'PurchaseOrder' ? 'Purchase Orders' : $moduleName), array(), 'get_dependents_list');
	}
}

// 3. Journal storage
$columns = $adb->getColumnNames('vtiger_journal_lines');
if (!in_array('cost_centre', $columns)) {
	$adb->query('ALTER TABLE vtiger_journal_lines ADD COLUMN cost_centre INT(19) DEFAULT NULL');
	say('Added the dimension column to the journal lines.');
}
addIndex('vtiger_journal_lines', 'idx_cost_centre', 'cost_centre');
Vtiger_Utils::CreateTable('vtiger_dimension_balances', "(
	cost_centre_id INT(19) NOT NULL,
	ledger_id INT(19) NOT NULL,
	ym CHAR(7) NOT NULL,
	debit DECIMAL(25,2) NOT NULL DEFAULT 0,
	credit DECIMAL(25,2) NOT NULL DEFAULT 0,
	PRIMARY KEY (cost_centre_id, ledger_id, ym)
)", true);

// 4. Repost so existing records carry their cost centre
$currentModule = 'Ledgers';
$counts = Vtiger_Ledger_Utils::rebuildAll();
say('Reposted: ' . implode(', ', array_map(function ($k, $v) { return "$k $v"; }, array_keys($counts), $counts)));

create_tab_data_file();
create_parenttab_data_file();
say('Done. Clear test/templates_c/v7/* and reload.');
