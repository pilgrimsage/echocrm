<?php
/**
 * Adds bank statement import: the staging tables for imported statement lines and the saved rules
 * that categorise them. The code is in include/utils/BankImport.php and modules/BankAccounts.
 * Run after bin/create-banking-modules.php. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-bank-import.php
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

Vtiger_Utils::CreateTable('vtiger_bank_statement_batches', "(
	batch_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	bank_account INT(19) NOT NULL,
	filename VARCHAR(200) DEFAULT NULL,
	uploaded_by INT(19) DEFAULT NULL,
	uploaded_time DATETIME DEFAULT NULL,
	from_date DATE DEFAULT NULL,
	to_date DATE DEFAULT NULL,
	closing_balance DECIMAL(25,2) DEFAULT NULL,
	KEY idx_account (bank_account)
)", true);
Vtiger_Utils::CreateTable('vtiger_bank_statement_lines', "(
	line_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	batch_id INT(19) NOT NULL,
	bank_account INT(19) NOT NULL,
	line_date DATE NOT NULL,
	description VARCHAR(255) DEFAULT NULL,
	reference VARCHAR(100) DEFAULT NULL,
	direction VARCHAR(10) NOT NULL,
	amount DECIMAL(25,2) NOT NULL,
	balance DECIMAL(25,2) DEFAULT NULL,
	status VARCHAR(12) NOT NULL DEFAULT 'Unmatched',
	matched_transaction INT(19) DEFAULT NULL,
	rule_id INT(19) DEFAULT NULL,
	line_hash CHAR(32) NOT NULL,
	KEY idx_batch (batch_id, status),
	KEY idx_hash (bank_account, line_hash),
	KEY idx_match (matched_transaction)
)", true);
Vtiger_Utils::CreateTable('vtiger_bank_rules', "(
	rule_id INT(19) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	contains_text VARCHAR(100) NOT NULL,
	direction VARCHAR(10) NOT NULL DEFAULT '',
	ledger_id INT(19) DEFAULT NULL,
	transaction_type VARCHAR(30) DEFAULT NULL,
	KEY idx_text (contains_text)
)", true);
// an index on the "reconciled" flag keeps matching quick on large accounts
addIndex('vtiger_banktransactions', 'idx_account_reconciled', 'bank_account, reconciled, direction, amount');
echo "Bank import tables ready.\n";
