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

class Ledgers_DepreciationPost_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$period = (string)$request->get('period');
		try {
			$done = Vtiger_Asset_Utils::post($period);
			$months = count($done);
			$message = $months ? sprintf('Depreciation posted for %d month%s, %s in total.', $months, $months == 1 ? '' : 's', number_format(array_sum(array_column($done, 'total')), 2)) : 'Nothing to post: depreciation is up to date.';
		} catch (Exception $e) {
			header('Location: index.php?module=Ledgers&view=DepreciationRun&period=' . urlencode($period) . '&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=Ledgers&view=DepreciationRun&period=' . urlencode($period) . '&message=' . urlencode($message));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
