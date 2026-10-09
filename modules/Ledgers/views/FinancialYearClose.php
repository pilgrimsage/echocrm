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

/** Checklist and preview of the closing entry for one year, with the button that closes it. */
class Ledgers_FinancialYearClose_View extends Ledgers_ReportBase_View {

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
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
			throw new AppException('Choose a financial year.');
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('PREVIEW', Vtiger_Financial_Years::preview($start));
		$viewer->assign('CHECK', Vtiger_Financial_Years::checklist($start));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('FinancialYearClose.tpl', $request->getModule());
	}
}
