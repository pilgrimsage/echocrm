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

/** Every button on the review screen: confirm, reject, undo, ignore, choose a transaction, create one, re-run matching. */
class BankAccounts_ImportAct_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$batch = (int)$request->get('batch');
		$line = (int)$request->get('line');
		$message = null;
		try {
			switch ($request->get('do')) {
				case 'confirm':
					Vtiger_Bank_Import::confirm($line);
					break;
				case 'confirm_all':
					$message = Vtiger_Bank_Import::confirmAllSuggested($batch) . ' matches confirmed.';
					break;
				case 'choose':
					Vtiger_Bank_Import::confirm($line, (int)$request->get('transaction'));
					break;
				case 'reject':
					Vtiger_Bank_Import::reject($line);
					break;
				case 'undo':
					Vtiger_Bank_Import::undo($line);
					break;
				case 'ignore':
					Vtiger_Bank_Import::ignore($line);
					break;
				case 'rematch':
					Vtiger_Bank_Import::autoMatch($batch);
					$message = 'Matching run again.';
					break;
				case 'create':
					Vtiger_Bank_Import::createFromLine($line, array(
						'ledger' => (int)$request->get('ledger'), 'transaction_type' => (string)$request->get('transaction_type') ?: null,
						'rule_text' => (string)$request->get('rule_text'),
					), (bool)$request->get('remember') && (string)$request->get('rule_text') !== '');
					break;
				case 'create_ruled':
					$message = $this->createRuled($batch) . ' transactions created from your rules.';
					break;
				default:
					throw new Exception('Unknown action.');
			}
		} catch (Exception $e) {
			header('Location: index.php?module=BankAccounts&view=ImportReview&batch=' . $batch . '&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=BankAccounts&view=ImportReview&batch=' . $batch . ($message ? '&message=' . urlencode($message) : ''));
	}

	/** Books every unmatched line that a bank rule with a ledger recognises. */
	private function createRuled($batchId) {
		global $adb;
		$result = $adb->pquery("SELECT l.line_id, r.ledger_id, r.transaction_type FROM vtiger_bank_statement_lines l INNER JOIN vtiger_bank_rules r ON r.rule_id = l.rule_id
			WHERE l.batch_id = ? AND l.status = 'Unmatched' AND r.ledger_id IS NOT NULL", array($batchId));
		$done = 0;
		while ($row = $adb->fetch_array($result)) {
			try {
				Vtiger_Bank_Import::createFromLine($row['line_id'], array('ledger' => $row['ledger_id'], 'transaction_type' => $row['transaction_type'] ?: null));
				$done++;
			} catch (Exception $e) {
				// leave it for manual review (locked period, inactive account...)
			}
		}
		return $done;
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
