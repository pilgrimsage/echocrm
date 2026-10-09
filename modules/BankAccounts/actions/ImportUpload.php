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

/** Receives the statement file and sends the user on to the column mapping. */
class BankAccounts_ImportUpload_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$account = (int)$request->get('account');
		$file = $_FILES['statement'] ?? null;
		try {
			if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
				throw new Exception('Choose the statement file (CSV).');
			}
			if ($file['size'] > Vtiger_Bank_Import::MAX_FILE_BYTES) {
				throw new Exception('The file is larger than 5 MB. Export a shorter period.');
			}
			if (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), array('csv', 'txt'), true)) {
				throw new Exception('Only CSV files can be imported. In your bank\'s portal choose "download as CSV".');
			}
			if (!$account) {
				throw new Exception('Choose the bank account.');
			}
			$token = bin2hex(random_bytes(16));
			if (!move_uploaded_file($file['tmp_name'], BankAccounts_ImportStatement_View::uploadDirectory() . "/$token.csv")) {
				throw new Exception('The file could not be stored.');
			}
		} catch (Exception $e) {
			header('Location: index.php?module=BankAccounts&view=ImportStatement&account=' . $account . '&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=BankAccounts&view=ImportStatement&account=' . $account . '&file=' . $token . '&name=' . urlencode($file['name']));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
