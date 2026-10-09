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

class Ledgers_JournalSave_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'CreateView'));
	}

	public function process(Vtiger_Request $request) {
		$lines = array();
		$ledgers = (array)$request->get('ledger');
		$debits = (array)$request->get('debit');
		$credits = (array)$request->get('credit');
		$memos = (array)$request->get('memo');
		foreach ($ledgers as $i => $ledger) {
			$lines[] = array('ledger' => (int)$ledger, 'debit' => (float)($debits[$i] ?? 0), 'credit' => (float)($credits[$i] ?? 0), 'memo' => (string)($memos[$i] ?? ''));
		}
		try {
			Vtiger_Ledger_Utils::postManual((string)$request->get('entry_date'), (string)$request->get('narration'), $lines);
		} catch (Exception $e) {
			header('Location: index.php?module=Ledgers&view=JournalEntry&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=Ledgers&view=Journal');
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
