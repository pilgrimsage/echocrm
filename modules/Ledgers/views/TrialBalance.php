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

/** Every ledger's debit and credit totals as at a date; the two columns must agree. */
class Ledgers_TrialBalance_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		$asAt = $this->dateParam($request, 'to', date('Y-m-d'));
		list($selectedCostCentre, $costCentres) = $this->costCentreFilter($request);
		$rows = array();
		$debit = $credit = 0.0;
		foreach (Vtiger_Ledger_Utils::ledgerTotals($asAt, null, $costCentres) as $row) {
			$net = $row['debit'] - $row['credit'];
			if (abs($net) < 0.005 && abs($row['debit']) < 0.005) {
				continue;
			}
			$row['dr'] = $net > 0 ? $net : 0;
			$row['cr'] = $net < 0 ? -$net : 0;
			$debit += $row['dr'];
			$credit += $row['cr'];
			$rows[] = $row;
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('COST_CENTRES', $this->costCentreOptions(true));
		$viewer->assign('CC', $selectedCostCentre);
		$viewer->assign('TO', $asAt);
		$viewer->assign('ROWS', $rows);
		$viewer->assign('TOTAL_DEBIT', round($debit, 2));
		$viewer->assign('TOTAL_CREDIT', round($credit, 2));
		$viewer->view('TrialBalance.tpl', $request->getModule());
	}
}
