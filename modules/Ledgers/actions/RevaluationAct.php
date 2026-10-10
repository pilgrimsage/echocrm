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

class Ledgers_RevaluationAct_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$asAt = (string)$request->get('as_at');
		$back = 'index.php?module=Ledgers&view=Revaluation&as_at=' . urlencode($asAt);
		try {
			if ($request->get('do') == 'undo') {
				Vtiger_Revaluation_Utils::undo($asAt);
				$message = 'The revaluation of ' . $asAt . ' and its reversal were removed.';
			} else {
				$rates = array();
				foreach ((array)$request->get('rate') as $currencyId => $rate) {
					$rates[(int)$currencyId] = (float)$rate;
					$back .= '&rate[' . (int)$currencyId . ']=' . urlencode((float)$rate);
				}
				$net = Vtiger_Revaluation_Utils::post($asAt, $rates);
				$message = 'Revaluation posted for ' . $asAt . ': ' . ($net >= 0 ? 'unrealised gain ' : 'unrealised loss ') . number_format(abs($net), 2) . '. It is reversed the next day.';
			}
		} catch (Exception $e) {
			header('Location: ' . $back . '&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: ' . $back . '&message=' . urlencode($message));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
