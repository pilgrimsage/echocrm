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
include_once 'include/utils/BudgetUtils.php';

/** Create, save, activate, delete and fill budgets. */
class Ledgers_BudgetAct_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		$id = (int)$request->get('budget');
		$back = 'index.php?module=Ledgers&view=Budgets';
		try {
			switch ($request->get('do')) {
				case 'create':
					$id = Vtiger_Budget_Utils::create((string)$request->get('budget_name'), (string)$request->get('year_start'), (string)$request->get('copy') ?: 'blank',
						(float)$request->get('growth'), (int)$request->get('from_budget'));
					header('Location: index.php?module=Ledgers&view=BudgetEdit&budget=' . $id);
					return;
				case 'save':
					$lines = array();
					$ledgers = (array)$request->get('ledger');
					$costs = (array)$request->get('cost');
					$amounts = (array)$request->get('m');
					foreach ($ledgers as $i => $ledger) {
						$row = array_values((array)($amounts[$i] ?? array()));
						$byMonth = array();
						for ($k = 1; $k <= 12; $k++) {
							$byMonth[$k] = ($row[$k - 1] ?? '') === '' ? 0 : $row[$k - 1];
						}
						$lines[] = array('ledger' => (int)$ledger, 'cost' => (int)($costs[$i] ?? 0), 'm' => $byMonth);
					}
					$count = Vtiger_Budget_Utils::saveLines($id, $lines);
					header('Location: index.php?module=Ledgers&view=BudgetEdit&budget=' . $id . '&message=' . urlencode("Saved $count lines."));
					return;
				case 'activate':
					Vtiger_Budget_Utils::activate($id);
					$back .= '&message=' . urlencode('The budget is now the active one for its year.');
					break;
				case 'delete':
					Vtiger_Budget_Utils::delete($id);
					$back .= '&message=' . urlencode('The budget was deleted.');
					break;
				default:
					throw new Exception('Unknown action.');
			}
		} catch (Exception $e) {
			header('Location: ' . ($request->get('do') == 'save' ? 'index.php?module=Ledgers&view=BudgetEdit&budget=' . $id : $back) . '&error=' . urlencode($e->getMessage()));
			return;
		}
		header('Location: ' . $back);
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
