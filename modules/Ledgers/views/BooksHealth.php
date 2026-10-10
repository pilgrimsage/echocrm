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
include_once 'include/utils/IntegrityUtils.php';

/** Health check of the books with one-click repairs. */
class Ledgers_BooksHealth_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$checks = Vtiger_Integrity_Utils::checks();
		$viewer->assign('CHECKS', $checks);
		$viewer->assign('PROBLEMS', array_sum(array_column($checks, 2)));
		$viewer->assign('MESSAGE', $request->get('message'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('BooksHealth.tpl', $request->getModule());
	}
}
