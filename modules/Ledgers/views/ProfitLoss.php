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
include_once 'include/utils/FinancialYears.php';

/** Income less expenses over a period, by ledger. */
class Ledgers_ProfitLoss_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		$from = $this->dateParam($request, 'from', $this->yearStart());
		$to = $this->dateParam($request, 'to', date('Y-m-d'));
		list($selectedCostCentre, $costCentres) = $this->costCentreFilter($request);
		$income = $expenses = array();
		$totalIncome = $totalExpenses = 0.0;
		foreach (Vtiger_Ledger_Utils::ledgerTotals($to, $from, $costCentres, false) as $row) {
			$amount = Vtiger_Ledger_Utils::normalBalance($row);
			if (abs($amount) < 0.005) {
				continue;
			}
			if ($row['grp'] == 'Income') {
				$income[] = array('id' => $row['id'], 'name' => $row['name'], 'amount' => round($amount, 2));
				$totalIncome += $amount;
			} elseif ($row['grp'] == 'Expenses') {
				$expenses[] = array('id' => $row['id'], 'name' => $row['name'], 'amount' => round($amount, 2));
				$totalExpenses += $amount;
			}
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('YEARS', Vtiger_Financial_Years::years());
		$viewer->assign('COST_CENTRES', $this->costCentreOptions(true));
		$viewer->assign('CC', $selectedCostCentre);
		$viewer->assign('FROM', $from);
		$viewer->assign('TO', $to);
		$viewer->assign('INCOME', $income);
		$viewer->assign('EXPENSES', $expenses);
		$viewer->assign('TOTAL_INCOME', round($totalIncome, 2));
		$viewer->assign('TOTAL_EXPENSES', round($totalExpenses, 2));
		$viewer->assign('PROFIT', round($totalIncome - $totalExpenses, 2));
		$viewer->view('ProfitLoss.tpl', $request->getModule());
	}
}
