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
include_once 'include/utils/FinancialYears.php';

class Ledgers_FinancialYearAct_Action extends Vtiger_Action_Controller {

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
		$start = (string)$request->get('year');
		try {
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
				throw new Exception('Choose a financial year.');
			}
			if ($request->get('do') == 'reopen') {
				Vtiger_Financial_Years::reopen($start);
				$message = 'The year was reopened and its closing entry removed.';
			} else {
				Vtiger_Financial_Years::close($start, (bool)$request->get('acknowledge'));
				$message = 'The year was closed. The books are locked up to its last day.';
			}
		} catch (Exception $e) {
			header('Location: index.php?module=Ledgers&view=FinancialYearClose&year=' . $start . '&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=Ledgers&view=FinancialYears&message=' . urlencode($message));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
