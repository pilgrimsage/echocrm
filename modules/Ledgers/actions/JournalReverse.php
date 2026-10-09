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

class Ledgers_JournalReverse_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->get('date')) ? $request->get('date') : date('Y-m-d');
		try {
			Vtiger_Ledger_Utils::reverse((int)$request->get('entry'), $date);
		} catch (Exception $e) {
			header('Location: index.php?module=Ledgers&view=Journal&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=Ledgers&view=Journal');
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
