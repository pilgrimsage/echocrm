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

class Ledgers_SettingsSave_Action extends Vtiger_Action_Controller {

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
			foreach (array('lock_date', 'books_start') as $name) {
				$value = (string)$request->get($name);
				Vtiger_Ledger_Utils::setSetting($name, preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null);
			}
			Vtiger_Ledger_Utils::setSetting('budget_control', $request->get('budget_control') == 'block' ? 'block' : null);
			$month = (int)$request->get('fy_start_month');
			if ($month >= 1 && $month <= 12) {
				Vtiger_Ledger_Utils::setSetting('fy_start_month', (string)$month);
			}
			Vtiger_Ledger_Utils::setSetting('require_cost_centre', $request->get('require_cost_centre') ? '1' : null);
			foreach ((array)$request->get('posting') as $key => $ledgerId) {
				if ($ledgerId) {
					Vtiger_Ledger_Utils::setAccount($key, (int)$ledgerId);
				}
			}
		} catch (Exception $e) {
			header('Location: index.php?module=Ledgers&view=AccountingSettings&message=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=Ledgers&view=AccountingSettings&saved=1');
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
