<?php
/**
 * Turns a copy of the development database into installer data: keeps every setting, role, profile,
 * picklist, field, filter, workflow, chart of accounts and so on, and removes the records (customers,
 * documents, payments, journal entries, ...), the history and the users.
 *
 * It only ever touches a database whose name ends in "_installer" and works on its own connection,
 * never the CRM's configured database. Used by bin/dump-db.sh.
 *
 * Usage: php bin/lib/scrub-installer-db.php <host> <port> <user> <password> <database_installer>
 */
if (PHP_SAPI !== 'cli') {
	exit("Run this from the command line.\n");
}
list(, $host, $port, $user, $password, $database) = $argv + array(null, 'localhost', '3306', 'root', '', '');
if (!preg_match('/_installer$/', (string)$database)) {
	fwrite(STDERR, "Refusing to scrub '$database': only a database named *_installer may be scrubbed.\n");
	exit(1);
}
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

function tableExists($pdo, $table) {
	$stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
	$stmt->execute(array($table));
	return (bool)$stmt->fetchColumn();
}

// 1. the tables that hold the fields of every entity module except the chart of accounts
$keepModules = array('Ledgers');
$entityModules = $pdo->query('SELECT t.name, t.tabid FROM vtiger_tab t WHERE t.isentitytype = 1')->fetchAll(PDO::FETCH_ASSOC);
$recordTables = array('vtiger_crmentity');
foreach ($entityModules as $module) {
	if (in_array($module['name'], $keepModules, true)) {
		continue;
	}
	$stmt = $pdo->prepare('SELECT DISTINCT tablename FROM vtiger_field WHERE tabid = ?');
	$stmt->execute(array($module['tabid']));
	foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
		$recordTables[] = $table;
	}
}
// 2. what hangs off records, plus history and logs
$extra = array(
	'vtiger_inventoryproductrel', 'vtiger_inventorychargesrel', 'vtiger_inventoryshippingrel', 'vtiger_inventorysubproductrel',
	'vtiger_pricebookproductrel', 'vtiger_productcurrencyrel', 'vtiger_producttaxrel', 'vtiger_seactivityrel', 'vtiger_salesmanactivityrel',
	'vtiger_activity_reminder', 'vtiger_activity_reminder_popup', 'vtiger_invitees', 'vtiger_crmentity_user_field', 'vtiger_crmentityrel',
	'vtiger_senotesrel', 'vtiger_seattachmentsrel', 'vtiger_attachments', 'vtiger_potstagehistory', 'vtiger_customerdetails',
	'vtiger_modtracker_basic', 'vtiger_modtracker_detail', 'vtiger_modtracker_relations', 'vtiger_tracker', 'vtiger_loginhistory',
	'vtiger_mobile_alerts', 'vtiger_user_module_preferences', 'vtiger_audit_trial',
	'com_vtiger_workflowtask_queue', 'com_vtiger_workflow_activatedonce', 'com_vtiger_workflowtasks_entitymethod_hold',
	'vtiger_import_maps_user', 'vtiger_mail_accounts', 'vtiger_mailmanager_mailattachments', 'vtiger_mailmanager_mailrecord',
	'vtiger_mailscanner_actions', 'vtiger_mailscanner_ids', 'vtiger_mailscanner_folders',
	// accounting transactions (the chart of accounts stays)
	'vtiger_journal_entries', 'vtiger_journal_lines', 'vtiger_journal_audit', 'vtiger_ledger_balances',
	'vtiger_bank_statement_lines', 'vtiger_bank_statement_batches', 'vtiger_asset_depreciation', 'vtiger_stock_movements',
	'vtiger_budget_lines', 'vtiger_budgets', 'vtiger_budget_amounts', 'vtiger_financial_year_closings',
	// the people: users and what is stored per user
	'vtiger_user2role', 'vtiger_users2group', 'vtiger_user_preferences', 'vtiger_homestuff', 'vtiger_homedefault_user',
	'vtiger_dashboard_tabs', 'vtiger_module_dashboard_widgets', 'vtiger_calendar_user_activitytypes', 'vtiger_calendar_user_activitytypes_seq',
	'vtiger_userdashboard', 'vtiger_users_last_import', 'vtiger_user2mergefields', 'vtiger_users', 'vtiger_login_history',
);
$cleared = array();
// the shared record table keeps the chart of accounts only
$count = (int)$pdo->query("SELECT COUNT(*) FROM vtiger_crmentity WHERE setype <> 'Ledgers'")->fetchColumn();
$pdo->exec("DELETE FROM vtiger_crmentity WHERE setype <> 'Ledgers'");
if ($count) {
	$cleared['vtiger_crmentity'] = $count;
}
foreach (array_unique(array_merge($recordTables, $extra)) as $table) {
	if ($table === 'vtiger_crmentity') {
		continue;
	}
	if (tableExists($pdo, $table)) {
		$count = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
		$pdo->exec("TRUNCATE TABLE `$table`");
		if ($count) {
			$cleared[$table] = $count;
		}
	}
}

// 3. the chart of accounts stays
$keep = $pdo->query("SELECT COUNT(*) FROM vtiger_ledgers")->fetchColumn();

// 4. numbering starts again; ids continue after the ledgers that were kept; the first user gets id 1
if (tableExists($pdo, 'vtiger_modentity_num')) {
	$pdo->exec('UPDATE vtiger_modentity_num SET cur_id = start_id');
}
$pdo->exec('UPDATE vtiger_crmentity_seq SET id = (SELECT MAX(crmid) FROM vtiger_crmentity)');
if (tableExists($pdo, 'vtiger_users_seq')) {
	$pdo->exec('UPDATE vtiger_users_seq SET id = 0');
}
// scratch tables from development work are not part of the product
$pdo->exec('DROP TABLE IF EXISTS pdo_import_test');
echo "Scrubbed $database: " . count($cleared) . " tables emptied, $keep ledgers kept.\n";
foreach ($cleared as $table => $count) {
	echo "  $table ($count)\n";
}
