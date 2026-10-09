<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

include_once 'include/utils/BankUtils.php';

/** The statement as a CSV download. */
class BankAccounts_StatementExport_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'DetailView'));
	}

	public function process(Vtiger_Request $request) {
		$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->get('from')) ? $request->get('from') : null;
		$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->get('to')) ? $request->get('to') : null;
		$statement = Vtiger_Bank_Utils::statement((int)$request->get('record'), $from, $to, (bool)$request->get('unreconciled'));
		if (!$statement) {
			throw new AppException('The bank account does not exist.');
		}
		$name = preg_replace('/[^A-Za-z0-9_-]+/', '_', $statement['account']['name']);
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="statement_' . $name . '_' . ($from ?: 'start') . '_' . ($to ?: 'today') . '.csv"');
		$out = fopen('php://output', 'w');
		fputcsv($out, array('Date', 'Transaction No', 'Type', 'Reference', 'Narration', 'In', 'Out', 'Balance', 'Reconciled'));
		fputcsv($out, array('', '', '', '', 'Opening balance', '', '', $statement['opening'], ''));
		foreach ($statement['rows'] as $row) {
			fputcsv($out, array($row['transaction_date'], $row['transaction_no'], $row['transaction_type'], decode_html($row['reference_no']), decode_html($row['narration']),
				$row['direction'] == 'In' ? $row['amount'] : '', $row['direction'] == 'Out' ? $row['amount'] : '', $row['running'], $row['reconciled'] ? 'Yes' : 'No'));
		}
		fputcsv($out, array('', '', '', '', 'Totals / closing balance', $statement['in'], $statement['out'], $statement['closing'], ''));
		fclose($out);
	}
}
