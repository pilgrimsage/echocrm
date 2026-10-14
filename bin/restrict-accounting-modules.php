<?php
/**
 * Makes the accounting modules (Ledgers, Bank Accounts, Bank Transactions, Payments, Cost Centres,
 * Fixed Assets) visible to the Administrator profile only. They were created with access for every
 * profile, which let a Sales or Guest user open the books and post entries. After this, give an
 * "Accountant" profile access in Settings > Profiles where it is wanted. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/restrict-accounting-modules.php
 * Afterwards: clear test/templates_c/v7/*.
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

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

foreach (array('Ledgers', 'BankAccounts', 'BankTransactions', 'Payments', 'CostCentres', 'FixedAssets') as $moduleName) {
	$profiles = restrictModuleToAdministrators($moduleName);
	echo $profiles ? "$moduleName: restricted for $profiles non-administrator profiles.\n" : "$moduleName: not installed, skipped.\n";
}
echo "Done. Clear test/templates_c/v7/* and reload.\n";
