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
include_once 'include/utils/RevaluationUtils.php';

/** Closing rates per currency, the unrealised exchange difference per document, and the button that posts it. */
class Ledgers_Revaluation_View extends Ledgers_ReportBase_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$asAt = (string)$request->get('as_at');
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asAt)) {
			$asAt = date('Y-m-d', strtotime('last day of last month'));
		}
		$groups = Vtiger_Revaluation_Utils::openItems($asAt);
		$defaults = Vtiger_Revaluation_Utils::defaultRates();
		$given = (array)$request->get('rate');
		$rates = array();
		foreach ($groups as $currencyId => $group) {
			$rates[$currencyId] = isset($given[$currencyId]) && (float)$given[$currencyId] > 0 ? (float)$given[$currencyId] : ($defaults[$currencyId] ?? 0);
		}
		$rows = array();
		$net = 0;
		$error = $request->get('error');
		try {
			if ($groups) {
				list($rows, $lines, $net) = Vtiger_Revaluation_Utils::preview($asAt, $rates);
			}
		} catch (Exception $e) {
			$error = $e->getMessage();
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('AS_AT', $asAt);
		$viewer->assign('GROUPS', $groups);
		$viewer->assign('RATES', $rates);
		$viewer->assign('ROWS', $rows);
		$viewer->assign('NET', $net);
		$viewer->assign('HISTORY', Vtiger_Revaluation_Utils::history());
		$viewer->assign('MESSAGE', $request->get('message'));
		$viewer->assign('ERROR', $error);
		$viewer->view('Revaluation.tpl', $request->getModule());
	}
}
