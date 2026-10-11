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
include_once 'include/utils/WithholdingUtils.php';
include_once 'include/utils/FinancialYears.php';

/** Tax withheld by us (owed to the government) and by our customers (claimed back), by section and party. */
class Ledgers_WithholdingRegister_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		$from = $this->dateParam($request, 'from', Vtiger_Financial_Years::yearOf(date('Y-m-d'))['start']);
		$to = $this->dateParam($request, 'to', date('Y-m-d'));
		$rows = array();
		$totals = array('Paid' => 0, 'Received' => 0);
		if (Vtiger_Withholding_Utils::enabled()) {
			list($rows, $totals) = Vtiger_Withholding_Utils::register($from, $to);
		}
		$payable = $receivable = 0.0;
		foreach ((array)Vtiger_Ledger_Utils::ledgerTotals($to) as $l) {
			if ($l['name'] == 'TDS Payable') {
				$payable = Vtiger_Ledger_Utils::normalBalance($l);
			} elseif ($l['name'] == 'TDS Receivable') {
				$receivable = Vtiger_Ledger_Utils::normalBalance($l);
			}
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('ENABLED', Vtiger_Withholding_Utils::enabled());
		$viewer->assign('FROM', $from);
		$viewer->assign('TO', $to);
		$viewer->assign('ROWS', $rows);
		$viewer->assign('TOTALS', $totals);
		$viewer->assign('PAYABLE', round($payable, 2));
		$viewer->assign('RECEIVABLE', round($receivable, 2));
		$viewer->view('WithholdingRegister.tpl', $request->getModule());
	}
}
