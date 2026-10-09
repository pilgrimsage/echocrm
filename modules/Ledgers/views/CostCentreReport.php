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

/**
 * Income, expenses and result per cost centre / project for a period, against its budget. A centre's
 * "incl. below" figures add everything under it (sub-centres, sub-projects).
 */
class Ledgers_CostCentreReport_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		global $adb;
		$from = $this->dateParam($request, 'from', $this->yearStart());
		$to = $this->dateParam($request, 'to', date('Y-m-d'));
		$summary = Vtiger_Ledger_Utils::costCentreSummary($from, $to);

		$centres = array();
		if (Vtiger_Ledger_Utils::dimensionColumn('vtiger_journal_lines') != 'NULL') {
			$result = $adb->pquery('SELECT c.costcentresid AS id, c.costcentre_name AS name, c.dimension_type AS type, c.status, c.parent_costcentre AS parent, c.budget
				FROM vtiger_costcentres c INNER JOIN vtiger_crmentity e ON e.crmid = c.costcentresid AND e.deleted = 0 ORDER BY c.dimension_type, c.costcentre_name');
			while ($row = $adb->fetch_array($result)) {
				$row['name'] = decode_html($row['name']);
				$row['type'] = decode_html($row['type']);
				$row['income'] = round($summary[$row['id']]['income'] ?? 0, 2);
				$row['expenses'] = round($summary[$row['id']]['expenses'] ?? 0, 2);
				$row['net'] = round($row['income'] - $row['expenses'], 2);
				$row['tree_income'] = $row['income'];
				$row['tree_expenses'] = $row['expenses'];
				$centres[$row['id']] = $row;
			}
			// add each centre's own figures to all of its ancestors
			foreach ($centres as $id => $centre) {
				$guard = 0;
				for ($parent = $centre['parent']; $parent && isset($centres[$parent]) && $guard < 20; $parent = $centres[$parent]['parent'], $guard++) {
					$centres[$parent]['tree_income'] += $centre['income'];
					$centres[$parent]['tree_expenses'] += $centre['expenses'];
				}
			}
			foreach ($centres as &$centre) {
				$centre['tree_net'] = round($centre['tree_income'] - $centre['tree_expenses'], 2);
				$centre['budget'] = (float)$centre['budget'];
				$centre['used'] = $centre['budget'] > 0 ? round(100 * $centre['tree_expenses'] / $centre['budget']) : null;
			}
			unset($centre);
		}
		$untagged = $this->untagged($from, $to);

		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('FROM', $from);
		$viewer->assign('TO', $to);
		$viewer->assign('CENTRES', $centres);
		$viewer->assign('UNTAGGED', $untagged);
		$viewer->view('CostCentreReport.tpl', $request->getModule());
	}

	/** Company-wide income and expenses without a cost centre, so the table can be reconciled with the Profit & Loss. */
	private function untagged($from, $to) {
		global $adb;
		$total = array('income' => 0.0, 'expenses' => 0.0);
		foreach (Vtiger_Ledger_Utils::ledgerTotals($to, $from) as $row) {
			if ($row['grp'] == 'Income') {
				$total['income'] += $row['credit'] - $row['debit'];
			} elseif ($row['grp'] == 'Expenses') {
				$total['expenses'] += $row['debit'] - $row['credit'];
			}
		}
		$tagged = Vtiger_Ledger_Utils::costCentreSummary($from, $to);
		foreach ($tagged as $centre) {
			$total['income'] -= $centre['income'];
			$total['expenses'] -= $centre['expenses'];
		}
		return array('income' => round($total['income'], 2), 'expenses' => round($total['expenses'], 2), 'net' => round($total['income'] - $total['expenses'], 2));
	}
}
