<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
include_once 'modules/Ledgers/views/ReportBase.php';
include_once 'include/utils/IntegrityUtils.php';

class Ledgers_BooksHealthAct_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$fix = (string)$request->get('fix');
		try {
			if ($fix == 'repost') {
				list($n, $problems) = Vtiger_Integrity_Utils::repostMissing();
				$message = $n . ' posted.';
			} elseif ($fix == 'remove') {
				list($n, $problems) = Vtiger_Integrity_Utils::removeOrphans();
				$message = $n . ' entries removed.';
			} elseif ($fix == 'totals') {
				Vtiger_Integrity_Utils::rebuildTotals();
				$problems = array();
				$message = 'The monthly totals were rebuilt from the journal lines.';
			} else {
				throw new Exception('Unknown repair.');
			}
		} catch (Exception $e) {
			header('Location: index.php?module=Ledgers&view=BooksHealth&error=' . urlencode($e->getMessage()));
			return;
		}
		$url = 'index.php?module=Ledgers&view=BooksHealth&message=' . urlencode($message);
		if ($problems) {
			$url .= '&error=' . urlencode(count($problems) . ' could not be repaired: ' . implode(' | ', array_slice($problems, 0, 5)));
		}
		header('Location: ' . $url);
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
