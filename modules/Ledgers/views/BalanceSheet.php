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

include_once 'modules/Ledgers/views/ReportBase.php';

/** What the business owns, owes and is worth as at a date; the profit to date is shown inside equity. */
class Ledgers_BalanceSheet_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		$asAt = $this->dateParam($request, 'to', date('Y-m-d'));
		$sections = array('Assets' => array(), 'Liabilities' => array(), 'Equity' => array());
		$totals = array('Assets' => 0.0, 'Liabilities' => 0.0, 'Equity' => 0.0);
		$income = $expenses = 0.0;
		foreach (Vtiger_Ledger_Utils::ledgerTotals($asAt) as $row) {
			$amount = Vtiger_Ledger_Utils::normalBalance($row);
			if ($row['grp'] == 'Income') {
				$income += $amount;
			} elseif ($row['grp'] == 'Expenses') {
				$expenses += $amount;
			} elseif (abs($amount) >= 0.005) {
				$sections[$row['grp']][] = array('id' => $row['id'], 'name' => $row['name'], 'amount' => round($amount, 2));
				$totals[$row['grp']] += $amount;
			}
		}
		$profit = round($income - $expenses, 2);
		$totals['Equity'] += $profit;
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('TO', $asAt);
		$viewer->assign('SECTIONS', $sections);
		$viewer->assign('TOTALS', array_map(function ($v) { return round($v, 2); }, $totals));
		$viewer->assign('PROFIT', $profit);
		$viewer->assign('LIABILITIES_AND_EQUITY', round($totals['Liabilities'] + $totals['Equity'], 2));
		$viewer->view('BalanceSheet.tpl', $request->getModule());
	}
}
