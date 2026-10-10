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
include_once 'include/utils/RecurringUtils.php';
include_once 'include/utils/ReminderUtils.php';

class Invoice_RecurringSave_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$invoice = (int)$request->get('record');
		try {
			Vtiger_Recurring_Utils::create($invoice, (string)$request->get('frequency'), (string)$request->get('start_date'), (string)$request->get('end_date') ?: null,
				(int)$request->get('max_count') ?: null, (string)$request->get('invoice_status'), (string)$request->get('notes'));
		} catch (Exception $e) {
			header('Location: index.php?module=Invoice&view=RecurringSetup&record=' . $invoice . '&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=Invoice&view=RecurringList&message=' . urlencode('The schedule was created.'));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
