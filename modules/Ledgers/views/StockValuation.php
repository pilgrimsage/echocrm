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
include_once 'include/utils/StockUtils.php';

/** Stock on hand and its value (moving average cost) as at a date, reconciled with the Inventory ledger. */
class Ledgers_StockValuation_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		$asAt = $this->dateParam($request, 'to', date('Y-m-d'));
		$rows = Vtiger_Stock_Utils::valuation($asAt);
		$total = 0.0;
		$qty = 0.0;
		foreach ($rows as $row) {
			$total += (float)$row['value'];
			$qty += (float)$row['qty'];
		}
		$ledger = 0.0;
		foreach (Vtiger_Ledger_Utils::sumsByLedger(null, $asAt, Vtiger_Ledger_Utils::ledgerId('Inventory', 'Assets')) as $s) {
			$ledger += $s[0] - $s[1];
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('TO', $asAt);
		$viewer->assign('ROWS', $rows);
		$viewer->assign('TOTAL', round($total, 2));
		$viewer->assign('QTY', round($qty, 3));
		$viewer->assign('LEDGER', round($ledger, 2));
		$viewer->assign('CHECKS', Vtiger_Stock_Utils::checks());
		$viewer->view('StockValuation.tpl', $request->getModule());
	}
}
