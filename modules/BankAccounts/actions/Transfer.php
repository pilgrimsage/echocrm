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

class BankAccounts_Transfer_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'CreateView'));
	}

	public function process(Vtiger_Request $request) {
		$from = (int)$request->get('from_account');
		try {
			$amount = (float)$request->get('amount');
			$date = $request->get('transfer_date');
			if ($amount <= 0) {
				throw new Exception('The amount must be more than zero.');
			}
			if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
				throw new Exception('Enter the transfer date.');
			}
			Vtiger_Bank_Utils::transfer($from, (int)$request->get('to_account'), $amount, $date,
				(string)$request->get('reference_no'), (string)$request->get('narration'));
		} catch (Exception $e) {
			header('Location: index.php?module=BankAccounts&view=Transfer&from_account=' . $from . '&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=BankAccounts&view=Statement&record=' . $from);
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
