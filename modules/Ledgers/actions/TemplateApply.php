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
include_once 'include/utils/AccountingTemplates.php';

/** Adds an industry chart of accounts (nothing is removed or renamed). Administrators only. */
class Ledgers_TemplateApply_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'CreateView'));
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
			list($added, $roles) = Vtiger_Accounting_Templates::apply((string)$request->get('template'));
			$message = "Added $added ledger" . ($added == 1 ? '' : 's') . ($roles ? " and set $roles posting account" . ($roles == 1 ? '' : 's') : '') . '.';
		} catch (Exception $e) {
			$message = $e->getMessage();
		}
		header('Location: index.php?module=Ledgers&view=AccountingSettings&message=' . urlencode($message));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
