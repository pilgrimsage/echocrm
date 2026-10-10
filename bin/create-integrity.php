<?php
/**
 * Switches on the audit trail of the books: the table that logs every journal entry created, deleted or
 * reversed (who, when, what). Logging starts from now; earlier history is not reconstructed. The books
 * health check needs nothing installed.
 *
 * Run after bin/create-journal.php. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-integrity.php
 */
if (PHP_SAPI !== 'cli') {
	exit("Run this from the command line.\n");
}
chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/utils/utils.php';
vimport('includes.runtime.EntryPoint');
include_once 'vtlib/Vtiger/Utils.php';
require_once __DIR__ . '/lib/module-setup.php';

global $adb;
Vtiger_Utils::CreateTable('vtiger_journal_audit', "(
	audit_id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	at DATETIME NOT NULL,
	user_id INT(19) DEFAULT NULL,
	action VARCHAR(12) NOT NULL,
	entry_id INT(19) NOT NULL DEFAULT 0,
	entry_date DATE DEFAULT NULL,
	source_module VARCHAR(30) DEFAULT NULL,
	source_id INT(19) DEFAULT NULL,
	source_key VARCHAR(20) DEFAULT NULL,
	entry_type VARCHAR(12) DEFAULT NULL,
	total DECIMAL(25,2) NOT NULL DEFAULT 0,
	narration VARCHAR(250) DEFAULT NULL,
	KEY idx_at (at),
	KEY idx_entry (entry_id),
	KEY idx_source (source_module, source_id)
)", true);
$exists = $adb->pquery("SHOW TABLES LIKE 'vtiger_journal_audit'");
if (!$adb->num_rows($exists)) {
	exit("The table vtiger_journal_audit was not created.\n");
}
echo "Audit trail ready.\n";
