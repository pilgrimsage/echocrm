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

/** Pause, resume, end or run a schedule now. */
class Invoice_RecurringAct_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$id = (int)$request->get('schedule');
		$message = null;
		try {
			switch ($request->get('do')) {
				case 'pause':
					Vtiger_Recurring_Utils::setStatus($id, 'Paused');
					break;
				case 'resume':
					Vtiger_Recurring_Utils::setStatus($id, 'Active');
					break;
				case 'end':
					Vtiger_Recurring_Utils::setStatus($id, 'Ended');
					break;
				case 'run':
					list($raised, $problems) = Vtiger_Recurring_Utils::runDue(date('Y-m-d'), $id);
					if ($problems) {
						throw new Exception(implode(' ', $problems));
					}
					$message = $raised ? "$raised invoice" . ($raised == 1 ? '' : 's') . ' raised.' : 'Nothing is due yet.';
					break;
				default:
					throw new Exception('Unknown action.');
			}
		} catch (Exception $e) {
			header('Location: index.php?module=Invoice&view=RecurringList&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=Invoice&view=RecurringList&log=' . $id . ($message ? '&message=' . urlencode($message) : ''));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
