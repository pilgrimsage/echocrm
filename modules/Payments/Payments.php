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
 * A payment received or made against a quote, invoice, purchase order or credit/debit note.
 * Registered by bin/create-payments-module.php; the rules live in include/utils/PaymentUtils.php
 * and modules/Payments/PaymentsHandler.php.
 */
class Payments extends Vtiger_CRMEntity {
	var $table_name = 'vtiger_payments';
	var $table_index = 'paymentsid';

	var $customFieldTable = Array('vtiger_paymentscf', 'paymentsid');

	var $tab_name = Array('vtiger_crmentity', 'vtiger_payments', 'vtiger_paymentscf');

	var $tab_name_index = Array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_payments' => 'paymentsid',
		'vtiger_paymentscf' => 'paymentsid');

	var $list_fields = Array(
		'Payment No' => Array('payments' => 'payment_no'),
		'Document' => Array('payments' => 'related_to'),
		'Direction' => Array('payments' => 'direction'),
		'Amount' => Array('payments' => 'amount'),
		'Date' => Array('payments' => 'payment_date'),
		'Method' => Array('payments' => 'payment_method'),
		'Status' => Array('payments' => 'status'),
	);
	var $list_fields_name = Array(
		'Payment No' => 'payment_no',
		'Document' => 'related_to',
		'Direction' => 'direction',
		'Amount' => 'amount',
		'Date' => 'payment_date',
		'Method' => 'payment_method',
		'Status' => 'status',
	);

	var $list_link_field = 'payment_no';

	var $search_fields = Array(
		'Payment No' => Array('payments' => 'payment_no'),
		'Reference' => Array('payments' => 'reference_no'),
	);
	var $search_fields_name = Array(
		'Payment No' => 'payment_no',
		'Reference' => 'reference_no',
	);

	var $popup_fields = Array('payment_no');

	var $def_basicsearch_col = 'payment_no';

	var $def_detailview_recname = 'payment_no';

	var $mandatory_fields = Array('related_to', 'amount', 'payment_date', 'assigned_user_id');

	var $default_order_by = 'payment_date';
	var $default_sort_order = 'DESC';

	function __construct() {
		$this->log = Logger::getLogger('Payments');
		$this->db = PearDatabase::getInstance();
		$this->column_fields = getColumnFields('Payments');
	}
}
