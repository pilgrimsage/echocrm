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

/** Budget against actual for a range of months, optionally for one cost centre / project. */
class Ledgers_BudgetReport_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		global $adb;
		$budgets = Vtiger_Budget_Utils::budgets();
		$id = (int)$request->get('budget');
		if (!$id) {
			$active = Vtiger_Budget_Utils::activeFor(date('Y-m-d'));
			$id = $active ? (int)$active['budget_id'] : ($budgets ? (int)$budgets[0]['budget_id'] : 0);
		}
		$report = null;
		$fromIndex = (int)$request->get('from_month');
		$toIndex = (int)$request->get('to_month');
		if ($id && ($budget = Vtiger_Budget_Utils::budget($id))) {
			if (!$fromIndex) {
				$fromIndex = 1;
			}
			if (!$toIndex) {
				// up to the current month when the budget is for the current year, else the whole year
				$year = Vtiger_Financial_Years::yearOf(date('Y-m-d'));
				$toIndex = ($year['start'] == $budget['year_start']) ? (int)round((strtotime(date('Y-m-01')) - strtotime($budget['year_start'])) / (86400 * 30.4)) + 1 : 12;
			}
			list(, , $costCentre) = array(null, null, (int)$request->get('cc'));
			$report = Vtiger_Budget_Utils::report($id, $fromIndex, $toIndex, $costCentre ?: null);
			$fromIndex = max(1, min(12, $fromIndex));
			$toIndex = max($fromIndex, min(12, $toIndex));
		}
		$monthNames = array();
		if ($report) {
			foreach ($report['months'] as $m) {
				$monthNames[] = date('M Y', strtotime($m . '-01'));
			}
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('BUDGETS', $budgets);
		$viewer->assign('BUDGET_ID', $id);
		$viewer->assign('REPORT', $report);
		$viewer->assign('MONTH_NAMES', $monthNames);
		$viewer->assign('FROM_MONTH', $fromIndex);
		$viewer->assign('TO_MONTH', $toIndex);
		$viewer->assign('COST_CENTRES', $this->costCentreOptions(true));
		$viewer->assign('CC', (int)$request->get('cc'));
		$viewer->view('BudgetReport.tpl', $request->getModule());
	}
}
