<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

include_once 'include/utils/BankUtils.php';

/** Ticks transactions off (or back on) against the bank's statement. Answers JSON. */
class BankAccounts_Reconcile_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		global $adb;
		$ids = array_filter(array_map('intval', (array)$request->get('ids')));
		$state = $request->get('state') ? 1 : 0;
		$response = new Vtiger_Response();
		if (!$ids) {
			$response->setError('Nothing selected.');
			$response->emit();
			return;
		}
		$marks = implode(',', array_fill(0, count($ids), '?'));
		$adb->pquery("UPDATE vtiger_banktransactions t INNER JOIN vtiger_crmentity c ON c.crmid = t.banktransactionsid AND c.deleted = 0
			SET t.reconciled = ?, t.reconciled_date = " . ($state ? 'CURDATE()' : 'NULL') . " WHERE t.banktransactionsid IN ($marks)", array_merge(array($state), $ids));
		$accounts = $adb->pquery("SELECT DISTINCT bank_account FROM vtiger_banktransactions WHERE banktransactionsid IN ($marks)", $ids);
		$balance = null;
		while ($row = $adb->fetch_array($accounts)) {
			$balance = Vtiger_Bank_Utils::reconciledBalance($row['bank_account']);
		}
		$response->setResult(array('reconciled_balance' => $balance));
		$response->emit();
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
