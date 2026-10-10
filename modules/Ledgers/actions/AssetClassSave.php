<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
include_once 'modules/Ledgers/views/ReportBase.php';
include_once 'include/utils/AssetUtils.php';

class Ledgers_AssetClassSave_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function checkPermission(Vtiger_Request $request) {
		parent::checkPermission($request);
		if (!Users_Record_Model::getCurrentUserModel()->isAdminUser()) {
			throw new AppException(vtranslate('LBL_PERMISSION_DENIED'));
		}
		return true;
	}

	public function process(Vtiger_Request $request) {
		try {
			$name = trim((string)$request->get('class_name'));
			Vtiger_Asset_Utils::saveClass((int)$request->get('class_id'), $name, (int)$request->get('asset_ledger'), (int)$request->get('accum_ledger'), (int)$request->get('expense_ledger'),
				(string)$request->get('dep_method'), (float)$request->get('life_years'), (float)$request->get('dep_rate'));
			// the class must also be selectable on the asset form
			$module = Vtiger_Module::getInstance('FixedAssets');
			$field = Vtiger_Field::getInstance('asset_class', $module);
			if ($field && !in_array($name, Vtiger_Util_Helper::getPickListValues('asset_class'))) {
				$field->setPicklistValues(array($name));
			}
		} catch (Exception $e) {
			header('Location: index.php?module=Ledgers&view=AssetClasses&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=Ledgers&view=AssetClasses&message=' . urlencode('Saved.'));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
