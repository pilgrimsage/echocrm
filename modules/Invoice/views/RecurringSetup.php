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

/** Form that turns an invoice into a recurring schedule. */
class Invoice_RecurringSetup_View extends Vtiger_Index_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		global $adb;
		$id = (int)$request->get('record');
		$result = $adb->pquery('SELECT i.invoice_no, i.subject, i.total, i.invoicedate, a.accountname FROM vtiger_invoice i INNER JOIN vtiger_crmentity c ON c.crmid = i.invoiceid AND c.deleted = 0
			LEFT JOIN vtiger_account a ON a.accountid = i.accountid WHERE i.invoiceid = ?', array($id));
		if (!$adb->num_rows($result)) {
			throw new AppException('The invoice does not exist.');
		}
		$invoice = $adb->fetch_array($result);
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('INVOICE_ID', $id);
		$viewer->assign('INVOICE', $invoice);
		$viewer->assign('FREQUENCIES', array_keys(Vtiger_Recurring_Utils::frequencies()));
		$viewer->assign('START', date('Y-m-d', strtotime(($invoice['invoicedate'] ?: date('Y-m-d')) . ' +1 month')));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('RecurringSetup.tpl', $request->getModule());
	}
}
