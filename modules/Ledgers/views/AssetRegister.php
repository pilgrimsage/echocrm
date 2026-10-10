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

/** Every asset with cost, accumulated depreciation and book value as at a date, with totals per class compared with the ledgers. */
class Ledgers_AssetRegister_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		$asAt = $this->dateParam($request, 'to', date('Y-m-d'));
		$register = Vtiger_Asset_Utils::register($asAt);
		$total = array('cost' => 0.0, 'accumulated' => 0.0, 'book' => 0.0);
		foreach ($register['rows'] as $row) {
			foreach ($total as $k => $v) {
				$total[$k] += $row[$k];
			}
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('TO', $asAt);
		$viewer->assign('ROWS', $register['rows']);
		$viewer->assign('CLASSES', $register['classes']);
		$viewer->assign('TOTAL', array_map(function ($v) { return round($v, 2); }, $total));
		$viewer->view('AssetRegister.tpl', $request->getModule());
	}
}
