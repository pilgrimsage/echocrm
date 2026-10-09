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
 * Chart of accounts: a ledger is a head that bank transactions are posted to (Sales, Purchases, Rent, Bank Charges...), grouped as Assets, Liabilities, Income, Expenses or Equity.
 * Registered by bin/create-banking-modules.php.
 */
class Ledgers extends Vtiger_CRMEntity {
	var $table_name = 'vtiger_ledgers';
	var $table_index = 'ledgersid';

	var $customFieldTable = Array('vtiger_ledgerscf', 'ledgersid');

	var $tab_name = Array('vtiger_crmentity', 'vtiger_ledgers', 'vtiger_ledgerscf');

	var $tab_name_index = Array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_ledgers' => 'ledgersid',
		'vtiger_ledgerscf' => 'ledgersid');

	var $list_fields = Array(
		'Ledger No' => Array('ledgers' => 'ledger_no'),
		'Ledger Name' => Array('ledgers' => 'ledger_name'),
		'Ledger Group' => Array('ledgers' => 'ledger_group'),
		'Parent Ledger' => Array('ledgers' => 'parent_ledger'),
	);
	var $list_fields_name = Array(
		'Ledger No' => 'ledger_no',
		'Ledger Name' => 'ledger_name',
		'Ledger Group' => 'ledger_group',
		'Parent Ledger' => 'parent_ledger',
	);

	var $list_link_field = 'ledger_name';

	var $search_fields = Array(
		'Ledger No' => Array('ledgers' => 'ledger_no'),
	);
	var $search_fields_name = Array(
		'Ledger No' => 'ledger_no',
	);

	var $popup_fields = Array('ledger_name');

	var $def_basicsearch_col = 'ledger_name';

	var $def_detailview_recname = 'ledger_name';

	var $mandatory_fields = Array('ledger_name', 'assigned_user_id');

	var $default_order_by = 'ledger_name';
	var $default_sort_order = 'ASC';

	function __construct() {
		$this->log = Logger::getLogger('Ledgers');
		$this->db = PearDatabase::getInstance();
		$this->column_fields = getColumnFields('Ledgers');
	}
}
