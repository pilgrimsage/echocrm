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

/** Unpaid invoices with the reminder due for each, the rules, and what was sent. */
class Invoice_Reminders_View extends Vtiger_Index_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'DetailView'));
	}

	public function process(Vtiger_Request $request) {
		$today = date('Y-m-d');
		$rows = Vtiger_Reminder_Utils::overdue($today);
		$due = 0;
		foreach ($rows as $row) {
			if ($row['rule']) {
				$due++;
			}
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('ROWS', $rows);
		$viewer->assign('DUE_COUNT', $due);
		$viewer->assign('RULES', Vtiger_Reminder_Utils::rules());
		$viewer->assign('LOG', Vtiger_Reminder_Utils::recentLog());
		$viewer->assign('MAIL_READY', Vtiger_Reminder_Utils::mailConfigured());
		$viewer->assign('AUTO', Vtiger_Reminder_Utils::autoEnabled());
		$viewer->assign('IS_ADMIN', Users_Record_Model::getCurrentUserModel()->isAdminUser());
		$viewer->assign('CAN_EDIT', Users_Privileges_Model::isPermitted('Invoice', 'EditView'));
		$viewer->assign('MESSAGE', $request->get('message'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('Reminders.tpl', $request->getModule());
	}
}
