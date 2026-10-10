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

/** The budgets of every year with their status; new budgets are started here. */
class Ledgers_Budgets_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		$viewer = $this->getViewer($request);
		$current = Vtiger_Financial_Years::yearOf(date('Y-m-d'));
		$years = array();
		foreach (array(-1, 0, 1) as $offset) {
			$years[] = Vtiger_Financial_Years::yearOf(date('Y-m-d', strtotime($current['start'] . " $offset year")));
		}
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('BUDGETS', Vtiger_Budget_Utils::budgets());
		$viewer->assign('YEARS', $years);
		$viewer->assign('CURRENT_YEAR', $current['start']);
		$viewer->assign('CAN_EDIT', Users_Privileges_Model::isPermitted($request->getModule(), 'EditView'));
		$viewer->assign('MESSAGE', $request->get('message'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('Budgets.tpl', $request->getModule());
	}
}
