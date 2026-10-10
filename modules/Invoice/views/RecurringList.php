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

/** The recurring invoice schedules, their next dates and what they raised. */
class Invoice_RecurringList_View extends Vtiger_Index_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'DetailView'));
	}

	public function process(Vtiger_Request $request) {
		$schedules = Vtiger_Recurring_Utils::listAll();
		$log = (int)$request->get('log');
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('SCHEDULES', $schedules);
		$viewer->assign('LOG_ID', $log);
		$viewer->assign('LOG', $log ? Vtiger_Recurring_Utils::logFor($log) : array());
		$viewer->assign('CAN_EDIT', Users_Privileges_Model::isPermitted('Invoice', 'EditView'));
		$viewer->assign('MESSAGE', $request->get('message'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('RecurringList.tpl', $request->getModule());
	}
}
