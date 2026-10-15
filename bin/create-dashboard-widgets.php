<?php
/**
 * Adds the dashboard widgets to the "Add Widget" menu:
 *   - Custom Widget (every user): build a number or a bar / ring chart from any module and saved filter
 *   - Receivables Ageing, Payables Ageing, Bank & Cash Balances, Profit & Loss (users who may open
 *     the Ledgers module, i.e. administrators and the Accountant role)
 * Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-dashboard-widgets.php
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

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

$home = Vtiger_Module::getInstance('Home');
if (!$home) {
	fwrite(STDERR, "The Home module is missing.\n");
	exit(1);
}

$widgets = array(
	'Custom Widget' => 'index.php?module=Home&view=ShowWidget&name=CustomWidget',
	'Receivables Ageing' => 'index.php?module=Ledgers&view=ShowWidget&name=ReceivablesAgeing',
	'Payables Ageing' => 'index.php?module=Ledgers&view=ShowWidget&name=PayablesAgeing',
	'Bank & Cash Balances' => 'index.php?module=Ledgers&view=ShowWidget&name=BankBalances',
	'Profit & Loss' => 'index.php?module=Ledgers&view=ShowWidget&name=ProfitAndLoss',
);
foreach ($widgets as $label => $url) {
	if (strpos($url, 'module=Ledgers') !== false && !Vtiger_Module::getInstance('Ledgers')) {
		echo "Ledgers is not installed, skipped: $label\n";
		continue;
	}
	$exists = $adb->pquery('SELECT 1 FROM vtiger_links WHERE tabid = ? AND linktype = ? AND linklabel = ?', array($home->id, 'DASHBOARDWIDGET', $label));
	if ($adb->num_rows($exists)) {
		echo "Exists: $label\n";
		continue;
	}
	Vtiger_Link::addLink($home->id, 'DASHBOARDWIDGET', $label, $url);
	echo "Added: $label\n";
}
echo "Done. Clear test/templates_c/v7/* and reload the dashboard.\n";
