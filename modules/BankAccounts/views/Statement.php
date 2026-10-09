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

/** Bank account statement: running balance, totals, and ticking transactions off against the bank's own statement. */
class BankAccounts_Statement_View extends Vtiger_Index_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'DetailView'));
	}

	public function process(Vtiger_Request $request) {
		global $adb;
		$moduleName = $request->getModule();
		$accountId = (int)$request->get('record');
		$accounts = array();
		$result = $adb->pquery("SELECT b.bankaccountsid, b.account_name FROM vtiger_bankaccounts b
			INNER JOIN vtiger_crmentity c ON c.crmid = b.bankaccountsid AND c.deleted = 0 ORDER BY b.account_name");
		while ($row = $adb->fetch_array($result)) {
			$accounts[$row['bankaccountsid']] = decode_html($row['account_name']);
		}
		if (!$accountId && $accounts) {
			$accountId = (int)key($accounts);
		}
		$from = $this->validDate($request->get('from')) ?: date('Y-m-01', strtotime('-2 months'));
		$to = $this->validDate($request->get('to')) ?: date('Y-m-d');
		$unreconciledOnly = (bool)$request->get('unreconciled');

		$statement = $accountId ? Vtiger_Bank_Utils::statement($accountId, $from, $to, $unreconciledOnly) : null;
		if ($statement) {
			foreach ($statement['rows'] as &$row) {
				$row['display_date'] = Vtiger_Date_UIType::getDisplayDateValue($row['transaction_date']);
				$row['locked'] = !empty($row['payment']) || !empty($row['transfer_pair']);
			}
			unset($row);
		}

		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $moduleName);
		$viewer->assign('ACCOUNTS', $accounts);
		$viewer->assign('ACCOUNT_ID', $accountId);
		$viewer->assign('FROM', $from);
		$viewer->assign('TO', $to);
		$viewer->assign('UNRECONCILED_ONLY', $unreconciledOnly);
		$viewer->assign('STATEMENT', $statement);
		$viewer->assign('RECONCILED_BALANCE', $accountId ? Vtiger_Bank_Utils::reconciledBalance($accountId) : 0);
		$viewer->assign('CAN_EDIT', Users_Privileges_Model::isPermitted($moduleName, 'EditView'));
		$viewer->view('Statement.tpl', $moduleName);
	}

	private function validDate($value) {
		return ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) ? $value : null;
	}
}
