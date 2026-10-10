<?php
/**
 * Adds fixed assets: the Fixed Assets module (register with depreciation terms, disposal details and
 * the book value), asset classes (what each kind of asset posts to and how it depreciates), the
 * depreciation table, the starting classes and ledgers, and the handler that enforces the rules and posts
 * acquisition and disposal. Monthly depreciation is posted from Ledgers > Run Depreciation.
 *
 * Run after bin/create-journal.php (and create-cost-centres.php). Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-fixed-assets.php
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
include_once 'include/utils/AssetUtils.php';

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

function say($message) {
	echo $message . "\n";
}

Vtiger_Utils::CreateTable('vtiger_asset_classes', "(
	class_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	class_name VARCHAR(100) NOT NULL,
	asset_ledger INT(19) NOT NULL,
	accum_ledger INT(19) NOT NULL,
	expense_ledger INT(19) NOT NULL,
	dep_method VARCHAR(5) NOT NULL DEFAULT 'SLM',
	life_years DECIMAL(7,2) DEFAULT NULL,
	dep_rate DECIMAL(7,3) DEFAULT NULL,
	UNIQUE KEY uq_name (class_name)
)", true);
Vtiger_Utils::CreateTable('vtiger_asset_depreciation', "(
	dep_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	asset_id INT(19) NOT NULL,
	period CHAR(7) NOT NULL,
	amount DECIMAL(25,2) NOT NULL,
	accumulated_after DECIMAL(25,2) NOT NULL,
	book_after DECIMAL(25,2) NOT NULL,
	UNIQUE KEY uq_asset_period (asset_id, period),
	KEY idx_period (period)
)", true);

Vtiger_Ledger_Utils::ledgerId('Depreciation Expense', 'Expenses');
Vtiger_Ledger_Utils::ledgerId('Gain or Loss on Disposal of Assets', 'Income');
Vtiger_Asset_Utils::seedClasses();
$classNames = array_keys(Vtiger_Asset_Utils::classes());
say('Asset classes ready: ' . implode(', ', $classNames));

$B = 'LBL_FIXEDASSETS_INFORMATION';
$B2 = 'LBL_FIXEDASSETS_DEPRECIATION';
$B3 = 'LBL_FIXEDASSETS_DISPOSAL';
$result = registerEntityModule(array(
	'name' => 'FixedAssets', 'label' => 'Fixed Assets', 'parent' => 'Inventory', 'menu_sequence' => 12, 'prefix' => 'FA',
	'blocks' => array($B, $B2, $B3, 'LBL_DESCRIPTION_INFORMATION'),
	'entity' => 'asset_name', 'list_columns' => array('asset_no', 'asset_name', 'asset_class', 'purchase_date', 'cost', 'accumulated_dep', 'book_value', 'asset_status'),
	'fields' => array(
		array('name' => 'asset_name', 'label' => 'Asset', 'block' => $B, 'uitype' => 2, 'type' => 'V~M', 'col' => 'asset_name', 'ctype' => 'VARCHAR(200)', 'qc' => 0, 'sm' => 1),
		array('name' => 'asset_no', 'label' => 'Asset No', 'block' => $B, 'uitype' => 4, 'type' => 'V~O', 'col' => 'asset_no', 'sm' => 1),
		array('name' => 'asset_class', 'label' => 'Asset Class', 'block' => $B, 'uitype' => 15, 'type' => 'V~M', 'col' => 'asset_class', 'ctype' => 'VARCHAR(100)', 'qc' => 0, 'sm' => 1, 'picklist' => $classNames),
		array('name' => 'asset_status', 'label' => 'Status', 'block' => $B, 'uitype' => 15, 'type' => 'V~M', 'col' => 'asset_status', 'ctype' => 'VARCHAR(20)', 'qc' => 0, 'dv' => 'In Service', 'picklist' => array('In Service', 'Disposed')),
		array('name' => 'purchase_date', 'label' => 'Purchase Date', 'block' => $B, 'uitype' => 5, 'type' => 'D~M', 'col' => 'purchase_date', 'ctype' => 'DATE', 'qc' => 0, 'sm' => 1),
		array('name' => 'cost', 'label' => 'Cost', 'block' => $B, 'uitype' => 72, 'type' => 'N~M', 'col' => 'cost', 'ctype' => 'DECIMAL(25,8)', 'qc' => 0, 'sm' => 1),
		array('name' => 'vendor', 'label' => 'Vendor', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'vendor', 'ctype' => 'INT(19)', 'related' => array('Vendors')),
		array('name' => 'purchase_order', 'label' => 'Purchase Order', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'purchase_order', 'ctype' => 'INT(19)', 'related' => array('PurchaseOrder')),
		array('name' => 'cost_centre', 'label' => 'Cost Centre', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'cost_centre', 'ctype' => 'INT(19)', 'related' => array('CostCentres')),
		array('name' => 'location', 'label' => 'Location', 'block' => $B, 'uitype' => 1, 'type' => 'V~O', 'col' => 'location'),
		array('name' => 'serial_no', 'label' => 'Serial No', 'block' => $B, 'uitype' => 1, 'type' => 'V~O', 'col' => 'serial_no'),
		array('name' => 'acquisition_posting', 'label' => 'Acquisition Entry', 'block' => $B, 'uitype' => 15, 'type' => 'V~M', 'col' => 'acquisition_posting', 'ctype' => 'VARCHAR(20)', 'dv' => 'Post entry', 'picklist' => array('Post entry', 'Already booked')),
		array('name' => 'funding_ledger', 'label' => 'Funded From Ledger', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'funding_ledger', 'ctype' => 'INT(19)', 'related' => array('Ledgers')),
		array('name' => 'dep_method', 'label' => 'Method', 'block' => $B2, 'uitype' => 15, 'type' => 'V~O', 'col' => 'dep_method', 'ctype' => 'VARCHAR(5)', 'picklist' => array('SLM', 'WDV')),
		array('name' => 'life_years', 'label' => 'Life Years', 'block' => $B2, 'uitype' => 1, 'type' => 'N~O', 'col' => 'life_years', 'ctype' => 'DECIMAL(7,2)'),
		array('name' => 'dep_rate', 'label' => 'Annual Rate', 'block' => $B2, 'uitype' => 1, 'type' => 'N~O', 'col' => 'dep_rate', 'ctype' => 'DECIMAL(7,3)'),
		array('name' => 'salvage_value', 'label' => 'Salvage Value', 'block' => $B2, 'uitype' => 72, 'type' => 'N~O', 'col' => 'salvage_value', 'ctype' => 'DECIMAL(25,8)'),
		array('name' => 'depreciation_from', 'label' => 'Depreciate From', 'block' => $B2, 'uitype' => 5, 'type' => 'D~O', 'col' => 'depreciation_from', 'ctype' => 'DATE'),
		array('name' => 'opening_accumulated', 'label' => 'Accumulated Brought Forward', 'block' => $B2, 'uitype' => 72, 'type' => 'N~O', 'col' => 'opening_accumulated', 'ctype' => 'DECIMAL(25,8)'),
		array('name' => 'accumulated_dep', 'label' => 'Accumulated Depreciation', 'block' => $B2, 'uitype' => 72, 'type' => 'N~O', 'col' => 'accumulated_dep', 'ctype' => 'DECIMAL(25,8)', 'dt' => 2, 'sm' => 1),
		array('name' => 'book_value', 'label' => 'Book Value', 'block' => $B2, 'uitype' => 72, 'type' => 'N~O', 'col' => 'book_value', 'ctype' => 'DECIMAL(25,8)', 'dt' => 2, 'sm' => 1),
		array('name' => 'disposal_date', 'label' => 'Disposal Date', 'block' => $B3, 'uitype' => 5, 'type' => 'D~O', 'col' => 'disposal_date', 'ctype' => 'DATE'),
		array('name' => 'sale_amount', 'label' => 'Sale Proceeds', 'block' => $B3, 'uitype' => 72, 'type' => 'N~O', 'col' => 'sale_amount', 'ctype' => 'DECIMAL(25,8)'),
		array('name' => 'sale_ledger', 'label' => 'Proceeds Received In', 'block' => $B3, 'uitype' => 10, 'type' => 'I~O', 'col' => 'sale_ledger', 'ctype' => 'INT(19)', 'related' => array('Ledgers')),
		array('name' => 'assigned_user_id', 'label' => 'Assigned To', 'block' => $B, 'uitype' => 53, 'type' => 'V~M', 'col' => 'smownerid', 'table' => 'vtiger_crmentity', 'qc' => 0),
		array('name' => 'createdtime', 'label' => 'Created Time', 'block' => $B, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'createdtime', 'table' => 'vtiger_crmentity', 'dt' => 2),
		array('name' => 'modifiedtime', 'label' => 'Modified Time', 'block' => $B, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'modifiedtime', 'table' => 'vtiger_crmentity', 'dt' => 2),
		array('name' => 'modifiedby', 'label' => 'Last Modified By', 'block' => $B, 'uitype' => 52, 'type' => 'V~O', 'col' => 'modifiedby', 'table' => 'vtiger_crmentity', 'dt' => 3),
		array('name' => 'description', 'label' => 'Description', 'block' => 'LBL_DESCRIPTION_INFORMATION', 'uitype' => 19, 'type' => 'V~O', 'col' => 'description', 'table' => 'vtiger_crmentity'),
	),
));
addCurrencyColumns('vtiger_fixedassets');
addIndex('vtiger_fixedassets', 'idx_status', 'asset_status');
ModComments::addWidgetTo('FixedAssets');
// cost centres list their assets
$costCentres = Vtiger_Module::getInstance('CostCentres');
if ($costCentres) {
	$exists = $adb->pquery('SELECT 1 FROM vtiger_relatedlists WHERE tabid = ? AND related_tabid = ?', array($costCentres->id, $result['module']->id));
	if (!$adb->num_rows($exists)) {
		$costCentres->setRelatedList($result['module'], 'Fixed Assets', array(), 'get_dependents_list');
	}
}
say('Fixed Assets module ready.');

$existing = $adb->pquery('SELECT event_name FROM vtiger_eventhandlers WHERE handler_class = ?', array('FixedAssetsHandler'));
$have = array();
while ($row = $adb->fetch_array($existing)) {
	$have[$row['event_name']] = true;
}
foreach (array('vtiger.entity.beforesave', 'vtiger.entity.aftersave', 'vtiger.entity.beforedelete', 'vtiger.entity.afterdelete', 'vtiger.entity.afterrestore') as $event) {
	if (empty($have[$event])) {
		$em = new VTEventsManager($adb);
		$em->registerHandler($event, 'modules/FixedAssets/FixedAssetsHandler.php', 'FixedAssetsHandler');
	}
}
create_tab_data_file();
create_parenttab_data_file();
say('Done. Clear test/templates_c/v7/* and reload.');
