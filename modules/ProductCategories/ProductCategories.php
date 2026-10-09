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
 * Product categories: a record per category (optionally under a parent category) that products
 * point to through their "Product Category" field. Registered by bin/create-product-categories-module.php.
 */
class ProductCategories extends Vtiger_CRMEntity {
	var $table_name = 'vtiger_productcategories';
	var $table_index = 'productcategoriesid';

	var $customFieldTable = Array('vtiger_productcategoriescf', 'productcategoriesid');

	var $tab_name = Array('vtiger_crmentity', 'vtiger_productcategories', 'vtiger_productcategoriescf');

	var $tab_name_index = Array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_productcategories' => 'productcategoriesid',
		'vtiger_productcategoriescf' => 'productcategoriesid');

	var $list_fields = Array(
		'Category No' => Array('productcategories' => 'category_no'),
		'Category Name' => Array('productcategories' => 'category_name'),
		'Parent Category' => Array('productcategories' => 'parent_category'),
		'Assigned To' => Array('crmentity' => 'smownerid'),
	);
	var $list_fields_name = Array(
		'Category No' => 'category_no',
		'Category Name' => 'category_name',
		'Parent Category' => 'parent_category',
		'Assigned To' => 'assigned_user_id',
	);

	var $list_link_field = 'category_name';

	var $search_fields = Array(
		'Category No' => Array('productcategories' => 'category_no'),
		'Category Name' => Array('productcategories' => 'category_name'),
	);
	var $search_fields_name = Array(
		'Category No' => 'category_no',
		'Category Name' => 'category_name',
	);

	var $popup_fields = Array('category_name');

	var $def_basicsearch_col = 'category_name';

	var $def_detailview_recname = 'category_name';

	var $mandatory_fields = Array('category_name', 'assigned_user_id');

	var $default_order_by = 'category_name';
	var $default_sort_order = 'ASC';

	function __construct() {
		$this->log = Logger::getLogger('ProductCategories');
		$this->db = PearDatabase::getInstance();
		$this->column_fields = getColumnFields('ProductCategories');
	}
}
