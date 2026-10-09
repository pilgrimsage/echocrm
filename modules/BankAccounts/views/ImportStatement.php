<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
include_once 'include/utils/BankImport.php';

/** Step 1 and 2 of a statement import: choose the account and file, then say which column is which. */
class BankAccounts_ImportStatement_View extends Vtiger_Index_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public static function uploadDirectory() {
		$dir = 'storage/bank_statements';
		if (!is_dir($dir)) {
			mkdir($dir, 0750, true);
		}
		return $dir;
	}

	public function process(Vtiger_Request $request) {
		global $adb;
		$accounts = array();
		$result = $adb->pquery("SELECT b.bankaccountsid, b.account_name FROM vtiger_bankaccounts b INNER JOIN vtiger_crmentity c ON c.crmid = b.bankaccountsid AND c.deleted = 0
			WHERE b.status = 'Active' AND b.account_type != 'Cash' ORDER BY b.account_name");
		while ($row = $adb->fetch_array($result)) {
			$accounts[$row['bankaccountsid']] = decode_html($row['account_name']);
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('ACCOUNTS', $accounts);
		$viewer->assign('ACCOUNT_ID', (int)$request->get('account'));
		$viewer->assign('ERROR', $request->get('error'));
		$token = (string)$request->get('file');
		if (preg_match('/^[a-f0-9]{32}$/', $token) && is_file(self::uploadDirectory() . "/$token.csv")) {
			$rows = Vtiger_Bank_Import::readCsv(self::uploadDirectory() . "/$token.csv", 40);
			list($mapping, $headerRow) = Vtiger_Bank_Import::detectMapping($rows);
			$viewer->assign('TOKEN', $token);
			$viewer->assign('PREVIEW', array_slice($rows, 0, 12));
			$viewer->assign('COLUMNS', max(array_map('count', $rows)));
			$viewer->assign('MAPPING', $mapping);
			$viewer->assign('HEADER_ROW', $headerRow);
			$viewer->assign('FILENAME', (string)$request->get('name'));
		}
		$viewer->view('ImportStatement.tpl', $request->getModule());
	}
}
