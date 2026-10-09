<?php
/***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/

include_once 'modules/Vtiger/CRMEntity.php';

/**
 * A line in a bank account: money in or out, from a payment, a transfer or entered by hand.
 * Registered by bin/create-banking-modules.php.
 */
class BankTransactions extends Vtiger_CRMEntity {
	var $table_name = 'vtiger_banktransactions';
	var $table_index = 'banktransactionsid';

	var $customFieldTable = Array('vtiger_banktransactionscf', 'banktransactionsid');

	var $tab_name = Array('vtiger_crmentity', 'vtiger_banktransactions', 'vtiger_banktransactionscf');

	var $tab_name_index = Array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_banktransactions' => 'banktransactionsid',
		'vtiger_banktransactionscf' => 'banktransactionsid');

	var $list_fields = Array(
		'Transaction No' => Array('banktransactions' => 'transaction_no'),
		'Bank Account' => Array('banktransactions' => 'bank_account'),
		'Date' => Array('banktransactions' => 'transaction_date'),
		'Direction' => Array('banktransactions' => 'direction'),
		'Amount' => Array('banktransactions' => 'amount'),
		'Reconciled' => Array('banktransactions' => 'reconciled'),
	);
	var $list_fields_name = Array(
		'Transaction No' => 'transaction_no',
		'Bank Account' => 'bank_account',
		'Date' => 'transaction_date',
		'Direction' => 'direction',
		'Amount' => 'amount',
		'Reconciled' => 'reconciled',
	);

	var $list_link_field = 'transaction_no';

	var $search_fields = Array(
		'Transaction No' => Array('banktransactions' => 'transaction_no'),
	);
	var $search_fields_name = Array(
		'Transaction No' => 'transaction_no',
	);

	var $popup_fields = Array('transaction_no');

	var $def_basicsearch_col = 'transaction_no';

	var $def_detailview_recname = 'transaction_no';

	var $mandatory_fields = Array('transaction_no', 'assigned_user_id');

	var $default_order_by = 'transaction_date';
	var $default_sort_order = 'DESC';

	function __construct() {
		$this->log = Logger::getLogger('BankTransactions');
		$this->db = PearDatabase::getInstance();
		$this->column_fields = getColumnFields('BankTransactions');
	}
}
