<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

/**
 * Ledger statement: every bank transaction posted to a ledger (and its sub-ledgers) in a period,
 * with money in, money out and the net for the period.
 */
class Ledgers_Statement_View extends Vtiger_Index_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'DetailView'));
	}

	public function process(Vtiger_Request $request) {
		global $adb;
		$ledgers = array();
		$parents = array();
		$result = $adb->pquery('SELECT l.ledgersid, l.ledger_name, l.ledger_group, l.parent_ledger FROM vtiger_ledgers l
			INNER JOIN vtiger_crmentity c ON c.crmid = l.ledgersid AND c.deleted = 0 ORDER BY l.ledger_group, l.ledger_name');
		while ($row = $adb->fetch_array($result)) {
			$ledgers[$row['ledgersid']] = decode_html($row['ledger_group']) . ' / ' . decode_html($row['ledger_name']);
			$parents[$row['ledgersid']] = $row['parent_ledger'];
		}
		$ledgerId = (int)$request->get('record');
		if (!$ledgerId && $ledgers) {
			$ledgerId = (int)key($ledgers);
		}
		$from = $this->validDate($request->get('from')) ?: date('Y-04-01', strtotime(date('n') >= 4 ? 'today' : '-1 year'));
		$to = $this->validDate($request->get('to')) ?: date('Y-m-d');

		$rows = array();
		$in = $out = 0.0;
		if ($ledgerId) {
			$ids = array($ledgerId);
			do {
				$added = false;
				foreach ($parents as $id => $parent) {
					if ($parent && in_array($parent, $ids) && !in_array($id, $ids)) {
						$ids[] = $id;
						$added = true;
					}
				}
			} while ($added);
			$marks = implode(',', array_fill(0, count($ids), '?'));
			$result = $adb->pquery("SELECT t.banktransactionsid AS id, t.transaction_no, t.transaction_date, t.direction, t.transaction_type, t.amount,
					t.reference_no, t.narration, b.account_name, l.ledger_name
				FROM vtiger_banktransactions t
				INNER JOIN vtiger_crmentity c ON c.crmid = t.banktransactionsid AND c.deleted = 0
				LEFT JOIN vtiger_bankaccounts b ON b.bankaccountsid = t.bank_account
				LEFT JOIN vtiger_ledgers l ON l.ledgersid = t.ledger
				WHERE t.ledger IN ($marks) AND t.transaction_date >= ? AND t.transaction_date <= ?
				ORDER BY t.transaction_date, t.banktransactionsid", array_merge($ids, array($from, $to)));
			while ($row = $adb->fetch_array($result)) {
				$row['display_date'] = Vtiger_Date_UIType::getDisplayDateValue($row['transaction_date']);
				if ($row['direction'] == 'In') {
					$in += (float)$row['amount'];
				} else {
					$out += (float)$row['amount'];
				}
				$rows[] = $row;
			}
		}

		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('LEDGERS', $ledgers);
		$viewer->assign('LEDGER_ID', $ledgerId);
		$viewer->assign('FROM', $from);
		$viewer->assign('TO', $to);
		$viewer->assign('ROWS', $rows);
		$viewer->assign('TOTAL_IN', round($in, 2));
		$viewer->assign('TOTAL_OUT', round($out, 2));
		$viewer->view('Statement.tpl', $request->getModule());
	}

	private function validDate($value) {
		return ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) ? $value : null;
	}
}
