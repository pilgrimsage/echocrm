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

/** Money transfer between two of the company's accounts (cash to bank, bank to bank...). */
class BankAccounts_Transfer_View extends Vtiger_Index_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'CreateView'));
	}

	public function process(Vtiger_Request $request) {
		global $adb;
		$accounts = array();
		$result = $adb->pquery("SELECT b.bankaccountsid, b.account_name, b.current_balance FROM vtiger_bankaccounts b
			INNER JOIN vtiger_crmentity c ON c.crmid = b.bankaccountsid AND c.deleted = 0 WHERE b.status = 'Active' ORDER BY b.account_name");
		while ($row = $adb->fetch_array($result)) {
			$accounts[$row['bankaccountsid']] = array('name' => decode_html($row['account_name']), 'balance' => (float)$row['current_balance']);
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('ACCOUNTS', $accounts);
		$viewer->assign('FROM_ACCOUNT', (int)$request->get('from_account'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->assign('TODAY', date('Y-m-d'));
		$viewer->view('Transfer.tpl', $request->getModule());
	}
}
