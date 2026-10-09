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
include_once 'include/utils/StockUtils.php';

class Ledgers_StockAdjustmentSave_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'CreateView'));
	}

	public function process(Vtiger_Request $request) {
		$lines = array();
		$products = (array)$request->get('product');
		$quantities = (array)$request->get('qty');
		$costs = (array)$request->get('cost');
		foreach ($products as $i => $product) {
			$lines[] = array('product' => (int)$product, 'qty' => (float)($quantities[$i] ?? 0), 'cost' => $costs[$i] === '' ? null : (float)$costs[$i]);
		}
		try {
			$type = (string)$request->get('adjustment_type');
			if (!in_array($type, array('Opening Stock', 'Stock Count Correction', 'Write-off', 'Damage', 'Found'), true)) {
				throw new Exception('Choose the kind of adjustment.');
			}
			$date = (string)$request->get('adjustment_date');
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
				throw new Exception('Enter the date.');
			}
			Vtiger_Stock_Utils::createAdjustment($date, $type, (string)$request->get('narration'), $lines, (int)$request->get('counter_ledger') ?: null);
		} catch (Exception $e) {
			header('Location: index.php?module=Ledgers&view=StockAdjustment&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: index.php?module=Ledgers&view=StockAdjustment&message=' . urlencode('Stock adjustment recorded and posted.'));
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
