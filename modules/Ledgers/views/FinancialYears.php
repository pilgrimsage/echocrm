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
include_once 'include/utils/FinancialYears.php';

/** The financial years with their closing status; closing and reopening are administrators' work. */
class Ledgers_FinancialYears_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$years = Vtiger_Financial_Years::years();
		$latestClosed = null;
		foreach ($years as $year) {
			if ($year['closed'] && ($latestClosed === null || $year['start'] > $latestClosed)) {
				$latestClosed = $year['start'];
			}
		}
		$viewer->assign('YEARS', $years);
		$viewer->assign('LATEST_CLOSED', $latestClosed);
		$viewer->assign('IS_ADMIN', Users_Record_Model::getCurrentUserModel()->isAdminUser());
		$viewer->assign('MESSAGE', $request->get('message'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('FinancialYears.tpl', $request->getModule());
	}
}
