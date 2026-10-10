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
include_once 'include/utils/BudgetUtils.php';

/** The budget grid: a row per ledger (and cost centre) with 12 monthly amounts. */
class Ledgers_BudgetEdit_View extends Ledgers_ReportBase_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		global $adb;
		$budget = Vtiger_Budget_Utils::budget((int)$request->get('budget'));
		if (!$budget) {
			throw new AppException('The budget does not exist.');
		}
		$ledgers = array();
		$result = $adb->pquery("SELECT l.ledgersid, l.ledger_name, l.ledger_group FROM vtiger_ledgers l INNER JOIN vtiger_crmentity c ON c.crmid = l.ledgersid AND c.deleted = 0
			WHERE l.ledger_group IN ('Income', 'Expenses') ORDER BY l.ledger_group DESC, l.ledger_name");
		while ($row = $adb->fetch_array($result)) {
			$ledgers[$row['ledgersid']] = decode_html($row['ledger_group']) . ' / ' . decode_html($row['ledger_name']);
		}
		$year = Vtiger_Financial_Years::yearOf($budget['year_start']);
		$months = array();
		foreach (Vtiger_Budget_Utils::months($budget['year_start']) as $m) {
			$months[] = date('M', strtotime($m . '-01'));
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('BUDGET', $budget);
		$viewer->assign('YEAR_LABEL', $year['label']);
		$viewer->assign('MONTHS', $months);
		$viewer->assign('LINES', Vtiger_Budget_Utils::lines($budget['budget_id']));
		$viewer->assign('LEDGERS', $ledgers);
		$viewer->assign('COST_CENTRES', $this->costCentreOptions(true));
		$viewer->assign('MESSAGE', $request->get('message'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('BudgetEdit.tpl', $request->getModule());
	}
}
