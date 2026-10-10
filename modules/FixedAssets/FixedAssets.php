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
 * A fixed asset (equipment, vehicles, furniture, software...) with its depreciation rules. Acquisition,
 * monthly depreciation and disposal are posted to the journal; see include/utils/AssetUtils.php.
 * Registered by bin/create-fixed-assets.php.
 */
class FixedAssets extends Vtiger_CRMEntity {
	var $table_name = 'vtiger_fixedassets';
	var $table_index = 'fixedassetsid';

	var $customFieldTable = Array('vtiger_fixedassetscf', 'fixedassetsid');

	var $tab_name = Array('vtiger_crmentity', 'vtiger_fixedassets', 'vtiger_fixedassetscf');

	var $tab_name_index = Array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_fixedassets' => 'fixedassetsid',
		'vtiger_fixedassetscf' => 'fixedassetsid');

	var $list_fields = Array(
		'Asset No' => Array('fixedassets' => 'asset_no'),
		'Asset' => Array('fixedassets' => 'asset_name'),
		'Class' => Array('fixedassets' => 'asset_class'),
		'Cost' => Array('fixedassets' => 'cost'),
		'Book Value' => Array('fixedassets' => 'book_value'),
		'Status' => Array('fixedassets' => 'asset_status'),
		'Assigned To' => Array('crmentity' => 'smownerid'),
	);
	var $list_fields_name = Array(
		'Asset No' => 'asset_no',
		'Asset' => 'asset_name',
		'Class' => 'asset_class',
		'Cost' => 'cost',
		'Book Value' => 'book_value',
		'Status' => 'asset_status',
		'Assigned To' => 'assigned_user_id',
	);

	var $list_link_field = 'asset_name';

	var $search_fields = Array(
		'Asset No' => Array('fixedassets' => 'asset_no'),
		'Asset' => Array('fixedassets' => 'asset_name'),
	);
	var $search_fields_name = Array(
		'Asset No' => 'asset_no',
		'Asset' => 'asset_name',
	);

	var $popup_fields = Array('asset_name');

	var $def_basicsearch_col = 'asset_name';

	var $def_detailview_recname = 'asset_name';

	var $mandatory_fields = Array('asset_name', 'assigned_user_id');

	var $default_order_by = 'asset_name';
	var $default_sort_order = 'ASC';

	function __construct() {
		$this->log = Logger::getLogger('FixedAssets');
		$this->db = PearDatabase::getInstance();
		$this->column_fields = getColumnFields('FixedAssets');
	}
}
