<?php
/**
 * Adds budgets: the budget and budget-line tables. The code is in include/utils/BudgetUtils.php and
 * modules/Ledgers (Budgets, Budget Against Actual). The spending control is set in Accounting Settings.
 * Run after bin/create-financial-years.php. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-budgets.php
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

global $adb;
Vtiger_Utils::CreateTable('vtiger_budgets', "(
	budget_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	budget_name VARCHAR(100) NOT NULL,
	year_start DATE NOT NULL,
	status VARCHAR(10) NOT NULL DEFAULT 'Draft',
	notes VARCHAR(200) DEFAULT NULL,
	created_by INT(19) DEFAULT NULL,
	created_time DATETIME DEFAULT NULL,
	KEY idx_year (year_start, status)
)", true);
Vtiger_Utils::CreateTable('vtiger_budget_lines', "(
	line_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	budget_id INT(19) NOT NULL,
	ledger_id INT(19) NOT NULL,
	cost_centre_id INT(19) DEFAULT NULL,
	m1 DECIMAL(25,2) NOT NULL DEFAULT 0, m2 DECIMAL(25,2) NOT NULL DEFAULT 0, m3 DECIMAL(25,2) NOT NULL DEFAULT 0, m4 DECIMAL(25,2) NOT NULL DEFAULT 0,
	m5 DECIMAL(25,2) NOT NULL DEFAULT 0, m6 DECIMAL(25,2) NOT NULL DEFAULT 0, m7 DECIMAL(25,2) NOT NULL DEFAULT 0, m8 DECIMAL(25,2) NOT NULL DEFAULT 0,
	m9 DECIMAL(25,2) NOT NULL DEFAULT 0, m10 DECIMAL(25,2) NOT NULL DEFAULT 0, m11 DECIMAL(25,2) NOT NULL DEFAULT 0, m12 DECIMAL(25,2) NOT NULL DEFAULT 0,
	KEY idx_budget (budget_id)
)", true);
foreach (array('vtiger_budgets', 'vtiger_budget_lines') as $table) {
	$check = $adb->pquery("SHOW TABLES LIKE '$table'");
	if (!$adb->num_rows($check)) {
		fwrite(STDERR, "Table $table was not created.\n");
		exit(1);
	}
}
echo "Budget tables ready. Done. Clear test/templates_c/v7/* and reload.\n";
