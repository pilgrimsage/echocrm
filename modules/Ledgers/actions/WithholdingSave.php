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
include_once 'include/utils/WithholdingUtils.php';

class Ledgers_WithholdingSave_Action extends Vtiger_Action_Controller {

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
			$code = trim((string)$request->get('code'));
			Vtiger_Withholding_Utils::saveSection((int)$request->get('section_id'), $code, (string)$request->get('description'), (float)$request->get('rate'), (bool)$request->get('active'));
			// the section must also be selectable on the payment form
			$field = Vtiger_Field::getInstance('tds_section', Vtiger_Module::getInstance('Payments'));
			if ($field && !in_array($code, Vtiger_Util_Helper::getPickListValues('tds_section'))) {
				$field->setPicklistValues(array($code));
			}
		} catch (Exception $e) {
			header('Location: index.php?module=Ledgers&view=WithholdingSections&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=Ledgers&view=WithholdingSections&message=' . urlencode('Saved.'));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
