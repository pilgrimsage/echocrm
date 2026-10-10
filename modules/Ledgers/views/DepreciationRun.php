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
include_once 'include/utils/AssetUtils.php';

/** Preview of the depreciation due up to a month and the button that posts it. */
class Ledgers_DepreciationRun_View extends Ledgers_ReportBase_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$period = (string)$request->get('period');
		if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
			$period = date('Y-m', strtotime('first day of last month'));
		}
		$preview = array();
		$error = $request->get('error');
		try {
			$preview = Vtiger_Asset_Utils::preview($period);
		} catch (Exception $e) {
			$error = $e->getMessage();
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('PERIOD', $period);
		$viewer->assign('PREVIEW', $preview);
		$viewer->assign('TOTAL', round(array_sum(array_column($preview, 'total')), 2));
		$viewer->assign('MESSAGE', $request->get('message'));
		$viewer->assign('ERROR', $error);
		$viewer->view('DepreciationRun.tpl', $request->getModule());
	}
}
