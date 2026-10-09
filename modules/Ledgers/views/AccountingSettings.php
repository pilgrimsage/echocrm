<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
include_once 'include/utils/LedgerUtils.php';

include_once 'modules/Ledgers/views/ReportBase.php';

/** Lock date (books closed up to) and the date the books start. Administrators only. */
class Ledgers_AccountingSettings_View extends Ledgers_ReportBase_View {

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
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('LOCK_DATE', Vtiger_Ledger_Utils::lockDate());
		$viewer->assign('BOOKS_START', Vtiger_Ledger_Utils::getSetting('books_start'));
		$viewer->assign('OPENING_DATE', Vtiger_Ledger_Utils::openingDate());
		$viewer->assign('SAVED', $request->get('saved'));
		$viewer->view('AccountingSettings.tpl', $request->getModule());
	}
}
