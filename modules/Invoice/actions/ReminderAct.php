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

/** Send one reminder or all that are due, and edit the rules (rules and the automatic switch: administrators only). */
class Invoice_ReminderAct_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	private function adminOnly() {
		if (!Users_Record_Model::getCurrentUserModel()->isAdminUser()) {
			throw new AppException(vtranslate('LBL_PERMISSION_DENIED'));
		}
	}

	public function process(Vtiger_Request $request) {
		$message = null;
		$today = date('Y-m-d');
		try {
			switch ($request->get('do')) {
				case 'send':
					foreach (Vtiger_Reminder_Utils::overdue($today) as $row) {
						if ($row['invoiceid'] == (int)$request->get('invoice') && $row['rule']) {
							$message = 'Reminder: ' . Vtiger_Reminder_Utils::send($row, $row['rule'], $today) . '.';
						}
					}
					break;
				case 'send_all':
					list($sent, $other) = Vtiger_Reminder_Utils::runDue($today, true);
					$message = "$sent sent" . ($other ? ", $other could not be sent (see the log)" : '') . '.';
					break;
				case 'save_rule':
					$this->adminOnly();
					Vtiger_Reminder_Utils::saveRule((int)$request->get('rule'), (string)$request->get('label'), (int)$request->get('days_offset'), (string)$request->get('subject'), (string)$request->get('body'), (bool)$request->get('active'));
					$message = 'Rule saved.';
					break;
				case 'delete_rule':
					$this->adminOnly();
					Vtiger_Reminder_Utils::deleteRule((int)$request->get('rule'));
					$message = 'Rule deleted.';
					break;
				case 'auto':
					$this->adminOnly();
					Vtiger_Ledger_Utils::setSetting('auto_reminders', $request->get('enabled') ? '1' : null);
					$message = $request->get('enabled') ? 'Automatic reminders are on: the daily job sends what is due.' : 'Automatic reminders are off.';
					break;
				default:
					throw new Exception('Unknown action.');
			}
		} catch (Exception $e) {
			header('Location: index.php?module=Invoice&view=Reminders&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=Invoice&view=Reminders' . ($message ? '&message=' . urlencode($message) : ''));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
