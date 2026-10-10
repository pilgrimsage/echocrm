<?php
/**
 * Adds recurring invoices and payment reminders: the schedule, log and reminder-rule tables, the starting
 * reminder rules (3 days before the due date, and 3, 10 and 21 days after), and the two daily cron jobs.
 * Automatic sending of reminders stays off until it is switched on in Invoices > Payment Reminders.
 * Run after bin/create-journal.php. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-recurring-reminders.php
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
include_once 'vtlib/Vtiger/Cron.php';
require_once __DIR__ . '/lib/module-setup.php';
include_once 'include/utils/LedgerUtils.php';
include_once 'include/utils/ReminderUtils.php';

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

Vtiger_Utils::CreateTable('vtiger_recurring_invoices', "(
	rec_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	template_invoice INT(19) NOT NULL,
	frequency VARCHAR(12) NOT NULL,
	start_date DATE NOT NULL,
	end_date DATE DEFAULT NULL,
	max_count INT(11) DEFAULT NULL,
	next_date DATE NOT NULL,
	raised INT(11) NOT NULL DEFAULT 0,
	status VARCHAR(10) NOT NULL DEFAULT 'Active',
	invoice_status VARCHAR(20) NOT NULL DEFAULT 'Created',
	notes VARCHAR(200) DEFAULT NULL,
	last_run DATETIME DEFAULT NULL,
	last_invoice INT(19) DEFAULT NULL,
	created_by INT(19) DEFAULT NULL,
	created_time DATETIME DEFAULT NULL,
	KEY idx_due (status, next_date)
)", true);
Vtiger_Utils::CreateTable('vtiger_recurring_invoice_log', "(
	log_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	rec_id INT(19) NOT NULL,
	scheduled_date DATE NOT NULL,
	invoice_id INT(19) DEFAULT NULL,
	ok TINYINT(1) NOT NULL DEFAULT 1,
	message VARCHAR(250) DEFAULT NULL,
	run_time DATETIME DEFAULT NULL,
	KEY idx_rec (rec_id)
)", true);
Vtiger_Utils::CreateTable('vtiger_reminder_rules', "(
	rule_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	label VARCHAR(100) NOT NULL,
	days_offset INT(11) NOT NULL,
	subject VARCHAR(250) NOT NULL,
	body TEXT NOT NULL,
	active TINYINT(1) NOT NULL DEFAULT 1
)", true);
Vtiger_Utils::CreateTable('vtiger_reminder_log', "(
	log_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	invoice_id INT(19) NOT NULL,
	rule_id INT(19) NOT NULL,
	sent_time DATETIME DEFAULT NULL,
	to_address VARCHAR(200) DEFAULT NULL,
	status VARCHAR(20) NOT NULL,
	message VARCHAR(250) DEFAULT NULL,
	KEY idx_invoice (invoice_id, rule_id)
)", true);
addIndex('vtiger_invoice', 'idx_status_balance', 'invoicestatus, balance');
Vtiger_Reminder_Utils::seedRules();
echo "Tables and starting reminder rules ready.\n";

foreach (array(
	array('RecurringInvoices', 'cron/modules/Invoice/RecurringInvoices.service', 'Raises recurring invoices that are due'),
	array('PaymentReminders', 'cron/modules/Invoice/PaymentReminders.service', 'Sends due payment reminders when automatic sending is on'),
) as $task) {
	$found = $adb->pquery('SELECT 1 FROM vtiger_cron_task WHERE name = ?', array($task[0]));
	if (!$adb->num_rows($found)) {
		Vtiger_Cron::register($task[0], $task[1], 86400, 'Invoice', 1, 0, $task[2]);
		echo "Registered the cron job {$task[0]} (daily).\n";
	}
}
echo "Done. Clear test/templates_c/v7/* and reload.\n";
