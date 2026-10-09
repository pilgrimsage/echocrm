<?php
/**
 * Adds financial years and year-end closing: the table of closed years and the "closing entry" marker
 * on the monthly ledger totals (so a period profit and loss can leave closing entries out). Run after
 * bin/create-journal.php. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-financial-years.php
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

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

Vtiger_Utils::CreateTable('vtiger_financial_years', "(
	year_start DATE NOT NULL PRIMARY KEY,
	year_end DATE NOT NULL,
	label VARCHAR(20) NOT NULL,
	profit DECIMAL(25,2) NOT NULL DEFAULT 0,
	closed_by INT(19) DEFAULT NULL,
	closed_time DATETIME DEFAULT NULL,
	closing_entry_id INT(19) DEFAULT NULL
)", true);

if (!in_array('kind', $adb->getColumnNames('vtiger_ledger_balances'))) {
	$adb->query('ALTER TABLE vtiger_ledger_balances ADD COLUMN kind TINYINT NOT NULL DEFAULT 0, DROP PRIMARY KEY, ADD PRIMARY KEY (ledger_id, ym, kind)');
	echo "Added the closing-entry marker to the monthly ledger totals.\n";
}
addIndex('vtiger_journal_entries', 'idx_type_date', 'entry_type, entry_date');
Vtiger_Ledger_Utils::ledgerId('Retained Earnings', 'Equity');
Vtiger_Ledger_Utils::rebuildMonthlyTotals();
echo "Done. Clear test/templates_c/v7/* and reload.\n";
