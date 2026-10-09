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
 * A bank, cash, card or wallet account whose balance follows its bank transactions.
 * Registered by bin/create-banking-modules.php.
 */
class BankAccounts extends Vtiger_CRMEntity {
	var $table_name = 'vtiger_bankaccounts';
	var $table_index = 'bankaccountsid';

	var $customFieldTable = Array('vtiger_bankaccountscf', 'bankaccountsid');

	var $tab_name = Array('vtiger_crmentity', 'vtiger_bankaccounts', 'vtiger_bankaccountscf');

	var $tab_name_index = Array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_bankaccounts' => 'bankaccountsid',
		'vtiger_bankaccountscf' => 'bankaccountsid');

	var $list_fields = Array(
		'Account Code' => Array('bankaccounts' => 'bank_no'),
		'Account Name' => Array('bankaccounts' => 'account_name'),
		'Account Type' => Array('bankaccounts' => 'account_type'),
		'Current Balance' => Array('bankaccounts' => 'current_balance'),
		'Status' => Array('bankaccounts' => 'status'),
	);
	var $list_fields_name = Array(
		'Account Code' => 'bank_no',
		'Account Name' => 'account_name',
		'Account Type' => 'account_type',
		'Current Balance' => 'current_balance',
		'Status' => 'status',
	);

	var $list_link_field = 'account_name';

	var $search_fields = Array(
		'Account Code' => Array('bankaccounts' => 'bank_no'),
	);
	var $search_fields_name = Array(
		'Account Code' => 'bank_no',
	);

	var $popup_fields = Array('account_name');

	var $def_basicsearch_col = 'account_name';

	var $def_detailview_recname = 'account_name';

	var $mandatory_fields = Array('account_name', 'assigned_user_id');

	var $default_order_by = 'account_name';
	var $default_sort_order = 'ASC';

	function __construct() {
		$this->log = Logger::getLogger('BankAccounts');
		$this->db = PearDatabase::getInstance();
		$this->column_fields = getColumnFields('BankAccounts');
	}
}
