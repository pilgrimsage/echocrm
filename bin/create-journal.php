<?php
/**
 * Switches on double-entry bookkeeping: the journal tables, the standard ledgers the automatic
 * postings use, the handler that keeps the journal in step with invoices, purchase orders,
 * credit/debit notes, payments, bank transactions and opening balances, and a first full posting
 * of everything that already exists. Safe to run more than once (the posting is rebuilt each time).
 *
 * Run bin/create-banking-modules.php first.
 *
 * Usage (from the project root):   php bin/create-journal.php
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
include_once 'include/utils/LedgerUtils.php';

global $adb, $current_user, $currentModule;
$current_user = Users::getActiveAdminUser();

function say($message) {
	echo $message . "\n";
}

// 1. Tables
Vtiger_Utils::CreateTable('vtiger_journal_entries', "(
	entry_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	entry_date DATE NOT NULL,
	narration VARCHAR(255) DEFAULT NULL,
	source_module VARCHAR(50) NOT NULL,
	source_id INT(19) NOT NULL DEFAULT 0,
	source_key VARCHAR(20) NOT NULL,
	entry_type VARCHAR(10) NOT NULL,
	status VARCHAR(10) NOT NULL DEFAULT 'Posted',
	reversal_of INT(19) DEFAULT NULL,
	total DECIMAL(25,2) NOT NULL DEFAULT 0,
	created_by INT(19) DEFAULT NULL,
	created_time DATETIME DEFAULT NULL,
	KEY idx_source (source_module, source_id, source_key),
	KEY idx_date (entry_date)
)", true);
Vtiger_Utils::CreateTable('vtiger_journal_lines', "(
	line_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	entry_id INT(19) NOT NULL,
	ledger_id INT(19) NOT NULL,
	debit DECIMAL(25,2) NOT NULL DEFAULT 0,
	credit DECIMAL(25,2) NOT NULL DEFAULT 0,
	party_account INT(19) DEFAULT NULL,
	party_vendor INT(19) DEFAULT NULL,
	memo VARCHAR(255) DEFAULT NULL,
	KEY idx_entry (entry_id),
	KEY idx_ledger (ledger_id)
)", true);
Vtiger_Utils::CreateTable('vtiger_ledger_balances', "(
	ledger_id INT(19) NOT NULL,
	ym CHAR(7) NOT NULL,
	debit DECIMAL(25,2) NOT NULL DEFAULT 0,
	credit DECIMAL(25,2) NOT NULL DEFAULT 0,
	PRIMARY KEY (ledger_id, ym)
)", true);
Vtiger_Utils::CreateTable('vtiger_posting_accounts', "(
	account_key VARCHAR(40) NOT NULL PRIMARY KEY,
	ledger_id INT(19) NOT NULL
)", true);
require_once __DIR__ . '/lib/module-setup.php';
addIndex('vtiger_invoice', 'idx_status_balance', 'invoicestatus, balance');
addIndex('vtiger_purchaseorder', 'idx_status_balance', 'postatus, balance');
addIndex('vtiger_salesorder', 'idx_invoiceid', 'invoiceid');
addIndex('vtiger_salesorder', 'idx_purchaseorderid', 'purchaseorderid');
addIndex('vtiger_journal_lines', 'idx_ledger_entry', 'idx_ledger_entry', 'ledger_id, entry_id, debit, credit');
addIndex('vtiger_journal_lines', 'idx_party_account', 'party_account');
addIndex('vtiger_journal_lines', 'idx_party_vendor', 'party_vendor');
addIndex('vtiger_journal_entries', 'idx_date_id', 'entry_date, entry_id');
Vtiger_Utils::CreateTable('vtiger_accounting_settings', "(
	name VARCHAR(50) NOT NULL PRIMARY KEY,
	value VARCHAR(255) DEFAULT NULL
)", true);
say('Journal tables ready.');

// 2. Ledgers the automatic postings use (existing ones are kept)
$ledgers = array(
	'Assets' => array('Accounts Receivable', 'Cash in Hand', 'Bank Accounts', 'Input CGST', 'Input SGST', 'Input IGST', 'Input Tax (Other)'),
	'Liabilities' => array('Accounts Payable', 'Output CGST', 'Output SGST', 'Output IGST', 'Output Tax (Other)', 'Customer Advances', 'Suspense Account'),
	'Equity' => array('Opening Balance Equity'),
	'Income' => array('Sales', 'Sales Returns'),
	'Expenses' => array('Purchases', 'Purchase Returns', 'Round Off'),
);
foreach ($ledgers as $group => $names) {
	foreach ($names as $name) {
		Vtiger_Ledger_Utils::ledgerId($name, $group);
	}
}
say('Standard ledgers ready.');

// 3. Handler
foreach (array('vtiger.entity.beforesave', 'vtiger.entity.aftersave', 'vtiger.entity.afterdelete', 'vtiger.entity.afterrestore') as $event) {
	$found = $adb->pquery('SELECT 1 FROM vtiger_eventhandlers WHERE handler_class = ? AND event_name = ?', array('JournalHandler', $event));
	if (!$adb->num_rows($found)) {
		$em = new VTEventsManager($adb);
		$em->registerHandler($event, 'modules/Ledgers/JournalHandler.php', 'JournalHandler');
		say("Registered JournalHandler for $event.");
	}
}

// 4. Post everything that exists
$currentModule = 'Ledgers';
$counts = Vtiger_Ledger_Utils::rebuildAll();
say('Posted: ' . implode(', ', array_map(function ($k, $v) { return "$k $v"; }, array_keys($counts), $counts)));

$check = $adb->pquery('SELECT SUM(debit) AS d, SUM(credit) AS c FROM vtiger_journal_lines');
say(sprintf('Journal totals: debit %s, credit %s.', $adb->query_result($check, 0, 'd') ?: 0, $adb->query_result($check, 0, 'c') ?: 0));
say('Done. Clear test/templates_c/v7/* and reload.');
