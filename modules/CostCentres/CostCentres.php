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
 * Cost centres, projects, branches and departments: a dimension tagged on documents, payments, bank
 * transactions and journal lines, so income and cost can be reported and budgeted by it.
 * Registered by bin/create-cost-centres.php.
 */
class CostCentres extends Vtiger_CRMEntity {
	var $table_name = 'vtiger_costcentres';
	var $table_index = 'costcentresid';

	var $customFieldTable = Array('vtiger_costcentrescf', 'costcentresid');

	var $tab_name = Array('vtiger_crmentity', 'vtiger_costcentres', 'vtiger_costcentrescf');

	var $tab_name_index = Array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_costcentres' => 'costcentresid',
		'vtiger_costcentrescf' => 'costcentresid');

	var $list_fields = Array(
		'Code' => Array('costcentres' => 'costcentre_no'),
		'Name' => Array('costcentres' => 'costcentre_name'),
		'Type' => Array('costcentres' => 'dimension_type'),
		'Status' => Array('costcentres' => 'status'),
		'Budget' => Array('costcentres' => 'budget'),
		'Assigned To' => Array('crmentity' => 'smownerid'),
	);
	var $list_fields_name = Array(
		'Code' => 'costcentre_no',
		'Name' => 'costcentre_name',
		'Type' => 'dimension_type',
		'Status' => 'status',
		'Budget' => 'budget',
		'Assigned To' => 'assigned_user_id',
	);

	var $list_link_field = 'costcentre_name';

	var $search_fields = Array(
		'Code' => Array('costcentres' => 'costcentre_no'),
		'Name' => Array('costcentres' => 'costcentre_name'),
	);
	var $search_fields_name = Array(
		'Code' => 'costcentre_no',
		'Name' => 'costcentre_name',
	);

	var $popup_fields = Array('costcentre_name');

	var $def_basicsearch_col = 'costcentre_name';

	var $def_detailview_recname = 'costcentre_name';

	var $mandatory_fields = Array('costcentre_name', 'assigned_user_id');

	var $default_order_by = 'costcentre_name';
	var $default_sort_order = 'ASC';

	function __construct() {
		$this->log = Logger::getLogger('CostCentres');
		$this->db = PearDatabase::getInstance();
		$this->column_fields = getColumnFields('CostCentres');
	}
}
