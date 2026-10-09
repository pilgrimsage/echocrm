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
include_once 'include/utils/GstReturns.php';

/** GST returns for a month: GSTR-1 sections, GSTR-3B, purchase register and data checks. */
class Ledgers_GstReturns_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		$period = (string)$request->get('period');
		list($period, $from, $to) = Vtiger_Gst_Returns::periodBounds($period);
		$section = in_array($request->get('section'), array('gstr1', 'gstr3b', 'purchases', 'checks'), true) ? $request->get('section') : 'gstr1';

		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('PERIOD', $period);
		$viewer->assign('SECTION', $section);
		switch ($section) {
			case 'gstr3b':
				$viewer->assign('G3B', Vtiger_Gst_Returns::gstr3b($period));
				break;
			case 'purchases':
				$viewer->assign('PURCHASES', Vtiger_Gst_Returns::purchases($period));
				break;
			case 'checks':
				$checks = Vtiger_Gst_Returns::checks($period);
				$viewer->assign('CHECKS', $checks);
				break;
			default:
				$viewer->assign('G1', Vtiger_Gst_Returns::gstr1($period));
		}
		$viewer->view('GstReturns.tpl', $request->getModule());
	}
}
