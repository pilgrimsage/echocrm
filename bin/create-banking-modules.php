<?php
/**
 * Registers the banking modules: Ledgers (chart of accounts), Bank Accounts and Bank Transactions,
 * adds the "Bank Account" field to Payments (so a completed payment posts its bank transaction),
 * registers the handler that enforces the banking rules and seeds a standard set of ledgers.
 *
 * The PHP files live in modules/Ledgers, modules/BankAccounts, modules/BankTransactions and
 * include/utils/BankUtils.php. Safe to run more than once. Run bin/create-payments-module.php first.
 *
 * Usage (from the project root):   php bin/create-banking-modules.php
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

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

function say($message) {
	echo $message . "\n";
}

$common = function ($block, $table = null) {
	$t = $table ? array('table' => $table) : array();
	return array(
		array('name' => 'assigned_user_id', 'label' => 'Assigned To', 'block' => $block, 'uitype' => 53, 'type' => 'V~M', 'col' => 'smownerid', 'table' => 'vtiger_crmentity', 'qc' => 0),
		array('name' => 'createdtime', 'label' => 'Created Time', 'block' => $block, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'createdtime', 'table' => 'vtiger_crmentity', 'dt' => 2),
		array('name' => 'modifiedtime', 'label' => 'Modified Time', 'block' => $block, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'modifiedtime', 'table' => 'vtiger_crmentity', 'dt' => 2),
		array('name' => 'modifiedby', 'label' => 'Last Modified By', 'block' => $block, 'uitype' => 52, 'type' => 'V~O', 'col' => 'modifiedby', 'table' => 'vtiger_crmentity', 'dt' => 3),
	);
};
$description = array('name' => 'description', 'label' => 'Description', 'block' => 'LBL_DESCRIPTION_INFORMATION', 'uitype' => 19, 'type' => 'V~O', 'col' => 'description', 'table' => 'vtiger_crmentity');

// ---- Ledgers ------------------------------------------------------------------------------
$B = 'LBL_LEDGERS_INFORMATION';
$ledgers = registerEntityModule(array(
	'name' => 'Ledgers', 'label' => 'Ledgers', 'parent' => 'Inventory', 'menu_sequence' => 8, 'prefix' => 'LED',
	'blocks' => array($B, 'LBL_DESCRIPTION_INFORMATION'),
	'entity' => 'ledger_name', 'list_columns' => array('ledger_no', 'ledger_name', 'ledger_group', 'parent_ledger', 'assigned_user_id'),
	'fields' => array_merge(array(
		array('name' => 'ledger_name', 'label' => 'Ledger Name', 'block' => $B, 'uitype' => 2, 'type' => 'V~M', 'col' => 'ledger_name', 'ctype' => 'VARCHAR(200)', 'qc' => 0, 'sm' => 1),
		array('name' => 'ledger_no', 'label' => 'Ledger No', 'block' => $B, 'uitype' => 4, 'type' => 'V~O', 'col' => 'ledger_no', 'sm' => 1),
		array('name' => 'ledger_group', 'label' => 'Ledger Group', 'block' => $B, 'uitype' => 15, 'type' => 'V~M', 'col' => 'ledger_group', 'ctype' => 'VARCHAR(30)', 'qc' => 0, 'sm' => 1,
			'picklist' => array('Assets', 'Liabilities', 'Income', 'Expenses', 'Equity')),
		array('name' => 'parent_ledger', 'label' => 'Parent Ledger', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'parent_ledger', 'ctype' => 'INT(19)', 'qc' => 1, 'related' => array('Ledgers')),
		array('name' => 'opening_balance', 'label' => 'Opening Balance', 'block' => $B, 'uitype' => 72, 'type' => 'N~O', 'col' => 'opening_balance', 'ctype' => 'DECIMAL(25,8)'),
	), $common($B), array($description)),
));
addCurrencyColumns('vtiger_ledgers');
say('Ledgers ready.');

// ---- Bank accounts --------------------------------------------------------------------------
$B = 'LBL_BANKACCOUNTS_INFORMATION';
$B2 = 'LBL_BANKACCOUNTS_BALANCE';
$accounts = registerEntityModule(array(
	'name' => 'BankAccounts', 'label' => 'Bank Accounts', 'parent' => 'Inventory', 'menu_sequence' => 9, 'prefix' => 'BNK',
	'blocks' => array($B, $B2, 'LBL_DESCRIPTION_INFORMATION'),
	'entity' => 'account_name', 'list_columns' => array('bank_no', 'account_name', 'account_type', 'bank_name', 'current_balance', 'status', 'assigned_user_id'),
	'fields' => array_merge(array(
		array('name' => 'account_name', 'label' => 'Account Name', 'block' => $B, 'uitype' => 2, 'type' => 'V~M', 'col' => 'account_name', 'ctype' => 'VARCHAR(200)', 'qc' => 0, 'sm' => 1),
		array('name' => 'bank_no', 'label' => 'Account Code', 'block' => $B, 'uitype' => 4, 'type' => 'V~O', 'col' => 'bank_no', 'sm' => 1),
		array('name' => 'account_type', 'label' => 'Account Type', 'block' => $B, 'uitype' => 15, 'type' => 'V~M', 'col' => 'account_type', 'ctype' => 'VARCHAR(30)', 'qc' => 0, 'sm' => 1,
			'picklist' => array('Bank - Current', 'Bank - Savings', 'Cash', 'Credit Card', 'Wallet / UPI')),
		array('name' => 'bank_name', 'label' => 'Bank Name', 'block' => $B, 'uitype' => 1, 'type' => 'V~O', 'col' => 'bank_name', 'sm' => 1),
		array('name' => 'account_number', 'label' => 'Account Number', 'block' => $B, 'uitype' => 1, 'type' => 'V~O', 'col' => 'account_number'),
		array('name' => 'ifsc_code', 'label' => 'IFSC Code', 'block' => $B, 'uitype' => 1, 'type' => 'V~O~LE~11', 'col' => 'ifsc_code', 'ctype' => 'VARCHAR(11)'),
		array('name' => 'branch_name', 'label' => 'Branch Name', 'block' => $B, 'uitype' => 1, 'type' => 'V~O', 'col' => 'branch_name'),
		array('name' => 'status', 'label' => 'Status', 'block' => $B, 'uitype' => 15, 'type' => 'V~M', 'col' => 'status', 'ctype' => 'VARCHAR(20)', 'qc' => 0, 'dv' => 'Active', 'picklist' => array('Active', 'Inactive')),
		array('name' => 'ledger', 'label' => 'Ledger', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'ledger', 'ctype' => 'INT(19)', 'related' => array('Ledgers')),
		array('name' => 'opening_balance', 'label' => 'Opening Balance', 'block' => $B2, 'uitype' => 72, 'type' => 'N~O', 'col' => 'opening_balance', 'ctype' => 'DECIMAL(25,8)', 'qc' => 0),
		array('name' => 'opening_date', 'label' => 'Opening Date', 'block' => $B2, 'uitype' => 5, 'type' => 'D~O', 'col' => 'opening_date', 'ctype' => 'DATE'),
		array('name' => 'current_balance', 'label' => 'Current Balance', 'block' => $B2, 'uitype' => 72, 'type' => 'N~O', 'col' => 'current_balance', 'ctype' => 'DECIMAL(25,8)', 'dt' => 2, 'sm' => 1),
	), $common($B), array($description)),
));
addCurrencyColumns('vtiger_bankaccounts');
say('Bank Accounts ready.');

// ---- Bank transactions ----------------------------------------------------------------------
$B = 'LBL_BANKTRANSACTIONS_INFORMATION';
$transactions = registerEntityModule(array(
	'name' => 'BankTransactions', 'label' => 'Bank Transactions', 'parent' => 'Inventory', 'menu_sequence' => 10, 'prefix' => 'BT',
	'blocks' => array($B, 'LBL_DESCRIPTION_INFORMATION'),
	'entity' => 'transaction_no', 'list_columns' => array('transaction_no', 'bank_account', 'transaction_date', 'direction', 'transaction_type', 'amount', 'balance_after', 'reconciled'),
	'fields' => array_merge(array(
		array('name' => 'transaction_no', 'label' => 'Transaction No', 'block' => $B, 'uitype' => 4, 'type' => 'V~O', 'col' => 'transaction_no', 'sm' => 1),
		array('name' => 'bank_account', 'label' => 'Bank Account', 'block' => $B, 'uitype' => 10, 'type' => 'I~M', 'col' => 'bank_account', 'ctype' => 'INT(19)', 'qc' => 0, 'sm' => 1, 'related' => array('BankAccounts')),
		array('name' => 'transaction_date', 'label' => 'Transaction Date', 'block' => $B, 'uitype' => 5, 'type' => 'D~M', 'col' => 'transaction_date', 'ctype' => 'DATE', 'qc' => 0, 'sm' => 1),
		array('name' => 'direction', 'label' => 'Direction', 'block' => $B, 'uitype' => 15, 'type' => 'V~M', 'col' => 'direction', 'ctype' => 'VARCHAR(10)', 'qc' => 0, 'sm' => 1, 'picklist' => array('In', 'Out')),
		array('name' => 'amount', 'label' => 'Amount', 'block' => $B, 'uitype' => 72, 'type' => 'N~M', 'col' => 'amount', 'ctype' => 'DECIMAL(25,8)', 'qc' => 0, 'sm' => 1),
		array('name' => 'transaction_type', 'label' => 'Transaction Type', 'block' => $B, 'uitype' => 15, 'type' => 'V~O', 'col' => 'transaction_type', 'ctype' => 'VARCHAR(30)', 'qc' => 1,
			'picklist' => array('Deposit', 'Withdrawal', 'Payment Received', 'Payment Made', 'Transfer In', 'Transfer Out', 'Bank Charges', 'Interest', 'Other')),
		array('name' => 'ledger', 'label' => 'Ledger', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'ledger', 'ctype' => 'INT(19)', 'qc' => 1, 'related' => array('Ledgers')),
		array('name' => 'party_account', 'label' => 'Customer', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'party_account', 'ctype' => 'INT(19)', 'related' => array('Accounts')),
		array('name' => 'party_vendor', 'label' => 'Vendor', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'party_vendor', 'ctype' => 'INT(19)', 'related' => array('Vendors')),
		array('name' => 'reference_no', 'label' => 'Reference No', 'block' => $B, 'uitype' => 1, 'type' => 'V~O', 'col' => 'reference_no'),
		array('name' => 'narration', 'label' => 'Narration', 'block' => $B, 'uitype' => 21, 'type' => 'V~O', 'col' => 'narration', 'ctype' => 'TEXT'),
		array('name' => 'payment', 'label' => 'Payment', 'block' => $B, 'uitype' => 10, 'type' => 'I~O', 'col' => 'payment', 'ctype' => 'INT(19)', 'dt' => 2, 'related' => array('Payments')),
		array('name' => 'reconciled', 'label' => 'Reconciled', 'block' => $B, 'uitype' => 56, 'type' => 'C~O', 'col' => 'reconciled', 'ctype' => 'TINYINT(1)', 'sm' => 1),
		array('name' => 'reconciled_date', 'label' => 'Reconciled Date', 'block' => $B, 'uitype' => 5, 'type' => 'D~O', 'col' => 'reconciled_date', 'ctype' => 'DATE', 'dt' => 2),
		array('name' => 'balance_after', 'label' => 'Balance After', 'block' => $B, 'uitype' => 72, 'type' => 'N~O', 'col' => 'balance_after', 'ctype' => 'DECIMAL(25,8)', 'dt' => 2),
	), $common($B), array($description)),
));
// not a field: the other side of a transfer
Vtiger_Utils::AddColumn('vtiger_banktransactions', 'transfer_pair', 'INT(19)');
addCurrencyColumns('vtiger_banktransactions');
addCurrencyColumns('vtiger_payments');
addIndex('vtiger_banktransactions', 'idx_account_date', 'bank_account, transaction_date, banktransactionsid');
addIndex('vtiger_banktransactions', 'idx_payment', 'payment');
addIndex('vtiger_banktransactions', 'idx_ledger', 'ledger');
addIndex('vtiger_payments', 'idx_related', 'related_to, status');
say('Bank Transactions ready.');

// ---- Payments know their bank account --------------------------------------------------------
$payments = Vtiger_Module::getInstance('Payments');
if ($payments && !Vtiger_Field::getInstance('bank_account', $payments)) {
	$block = Vtiger_Block::getInstance('LBL_PAYMENTS_INFORMATION', $payments);
	$field = new Vtiger_Field();
	$field->name = 'bank_account';
	$field->label = 'Bank Account';
	$field->table = 'vtiger_payments';
	$field->column = 'bank_account';
	$field->columntype = 'INT(19)';
	$field->uitype = 10;
	$field->typeofdata = 'I~O';
	$field->quickcreate = 1;
	$block->addField($field);
	$field->setRelatedModules(array('BankAccounts'));
	say('Added Payments.bank_account.');
}

// ---- Product categories can post to their own income / expense ledgers --------------------------
$categories = Vtiger_Module::getInstance('ProductCategories');
if ($categories) {
	$block = Vtiger_Block::getInstance('LBL_PRODUCTCATEGORIES_INFORMATION', $categories);
	foreach (array('income_ledger' => 'Income Ledger', 'expense_ledger' => 'Expense Ledger') as $name => $label) {
		if (!Vtiger_Field::getInstance($name, $categories)) {
			$field = new Vtiger_Field();
			$field->name = $name;
			$field->label = $label;
			$field->table = 'vtiger_productcategories';
			$field->column = $name;
			$field->columntype = 'INT(19)';
			$field->uitype = 10;
			$field->typeofdata = 'I~O';
			$field->quickcreate = 1;
			$block->addField($field);
			$field->setRelatedModules(array('Ledgers'));
			say("Added ProductCategories.$name.");
		}
	}
}

// ---- Related lists ---------------------------------------------------------------------------
$links = array(
	array($accounts['module'], $transactions['module'], 'Transactions', 'bank_account'),
	array($ledgers['module'], $transactions['module'], 'Transactions', 'ledger'),
);
foreach ($links as $link) {
	list($parent, $child, $label) = $link;
	$exists = $adb->pquery('SELECT 1 FROM vtiger_relatedlists WHERE tabid = ? AND related_tabid = ?', array($parent->id, $child->id));
	if (!$adb->num_rows($exists)) {
		$parent->setRelatedList($child, $label, array(), 'get_dependents_list');
	}
}
if ($payments) {
	$exists = $adb->pquery('SELECT 1 FROM vtiger_relatedlists WHERE tabid = ? AND related_tabid = ?', array($accounts['module']->id, $payments->id));
	if (!$adb->num_rows($exists)) {
		$accounts['module']->setRelatedList($payments, 'Payments', array(), 'get_dependents_list');
	}
}
foreach (array('Ledgers', 'BankAccounts', 'BankTransactions') as $name) {
	ModComments::addWidgetTo($name);
}

// ---- Handler ---------------------------------------------------------------------------------
$existing = $adb->pquery('SELECT 1 FROM vtiger_eventhandlers WHERE handler_class = ?', array('BankTransactionsHandler'));
if (!$adb->num_rows($existing)) {
	$em = new VTEventsManager($adb);
	foreach (array('vtiger.entity.beforesave', 'vtiger.entity.aftersave', 'vtiger.entity.beforedelete', 'vtiger.entity.afterdelete', 'vtiger.entity.afterrestore') as $event) {
		$em->registerHandler($event, 'modules/BankTransactions/BankTransactionsHandler.php', 'BankTransactionsHandler');
	}
	say('Registered BankTransactionsHandler.');
}

// payments must also be protected when their bank transaction is reconciled
$registered = $adb->pquery("SELECT 1 FROM vtiger_eventhandlers WHERE handler_class = 'PaymentsHandler' AND event_name = 'vtiger.entity.beforedelete'");
if (!$adb->num_rows($registered)) {
	$em = new VTEventsManager($adb);
	$em->registerHandler('vtiger.entity.beforedelete', 'modules/Payments/PaymentsHandler.php', 'PaymentsHandler');
	say('PaymentsHandler now also runs before delete.');
}

// ---- Standard ledgers and a cash account --------------------------------------------------------
$seed = array(
	'Assets' => array('Cash in Hand', 'Bank Accounts', 'Accounts Receivable', 'Inventory', 'Advances and Deposits'),
	'Liabilities' => array('Accounts Payable', 'Loans', 'GST Payable', 'Salaries Payable'),
	'Equity' => array("Owner's Capital", 'Drawings', 'Retained Earnings'),
	'Income' => array('Sales', 'Service Income', 'Interest Income', 'Other Income'),
	'Expenses' => array('Purchases', 'Salaries and Wages', 'Rent', 'Utilities', 'Bank Charges', 'Interest Expense', 'Freight and Transport', 'Office Expenses', 'Miscellaneous Expenses'),
);
$ledgerIds = array();
foreach ($seed as $group => $names) {
	foreach ($names as $name) {
		$found = $adb->pquery('SELECT ledgersid FROM vtiger_ledgers WHERE ledger_name = ?', array($name));
		if ($adb->num_rows($found)) {
			$ledgerIds[$name] = $adb->query_result($found, 0, 0);
			continue;
		}
		$focus = CRMEntity::getInstance('Ledgers');
		$focus->column_fields['ledger_name'] = $name;
		$focus->column_fields['ledger_group'] = $group;
		$focus->column_fields['assigned_user_id'] = $current_user->id;
		$focus->save('Ledgers');
		$ledgerIds[$name] = $focus->id;
	}
}
$cash = $adb->pquery('SELECT 1 FROM vtiger_bankaccounts b INNER JOIN vtiger_crmentity c ON c.crmid = b.bankaccountsid AND c.deleted = 0 WHERE b.account_type = ?', array('Cash'));
if (!$adb->num_rows($cash)) {
	$focus = CRMEntity::getInstance('BankAccounts');
	$focus->column_fields['account_name'] = 'Cash in Hand';
	$focus->column_fields['account_type'] = 'Cash';
	$focus->column_fields['status'] = 'Active';
	$focus->column_fields['opening_balance'] = 0;
	$focus->column_fields['ledger'] = $ledgerIds['Cash in Hand'];
	$focus->column_fields['assigned_user_id'] = $current_user->id;
	$focus->save('BankAccounts');
	say('Seeded the ledgers and a Cash in Hand account.');
}

create_tab_data_file();
create_parenttab_data_file();
say('Done. Clear test/templates_c/v7/* and reload.');
