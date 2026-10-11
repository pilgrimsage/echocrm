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

/** The withholding sections (code, description, rate). Administrators only. */
class Ledgers_WithholdingSections_View extends Ledgers_ReportBase_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('ENABLED', Vtiger_Withholding_Utils::enabled());
		$viewer->assign('SECTIONS', Vtiger_Withholding_Utils::enabled() ? Vtiger_Withholding_Utils::sections() : array());
		$viewer->assign('IS_ADMIN', Users_Record_Model::getCurrentUserModel()->isAdminUser());
		$viewer->assign('MESSAGE', $request->get('message'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('WithholdingSections.tpl', $request->getModule());
	}
}
