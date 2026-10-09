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

include_once 'modules/BankAccounts/views/ImportStatement.php';

/** Stages the lines of the uploaded file with the chosen column mapping, matches them and opens the review. */
class BankAccounts_ImportStage_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$account = (int)$request->get('account');
		$token = (string)$request->get('file');
		$back = 'index.php?module=BankAccounts&view=ImportStatement&account=' . $account . '&file=' . $token . '&name=' . urlencode((string)$request->get('name'));
		try {
			$path = BankAccounts_ImportStatement_View::uploadDirectory() . "/$token.csv";
			if (!preg_match('/^[a-f0-9]{32}$/', $token) || !is_file($path)) {
				throw new Exception('The uploaded file is no longer there. Upload it again.');
			}
			$mapping = array();
			foreach (array('date', 'description', 'reference', 'debit', 'credit', 'amount', 'balance') as $key) {
				$value = $request->get('map_' . $key);
				$mapping[$key] = ($value === '' || $value === null) ? null : (int)$value;
			}
			$options = array('header_row' => (int)$request->get('header_row'), 'date_format' => (string)$request->get('date_format') ?: 'auto', 'amount_sign' => (string)$request->get('amount_sign'));
			list($batchId, $staged, $duplicates, $skipped) = Vtiger_Bank_Import::stage($account, (string)$request->get('name'), Vtiger_Bank_Import::readCsv($path), $mapping, $options);
			@unlink($path);
		} catch (Exception $e) {
			header('Location: ' . $back . '&error=' . urlencode($e->getMessage()));
			return;
		}
		$message = "$staged lines imported" . ($duplicates ? ", $duplicates skipped as already imported" : '') . ($skipped ? ", $skipped rows without a date or amount ignored" : '') . '.';
		header('Location: index.php?module=BankAccounts&view=ImportReview&batch=' . $batchId . '&message=' . urlencode($message));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
