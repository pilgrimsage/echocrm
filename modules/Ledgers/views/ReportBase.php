<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
include_once 'include/utils/LedgerUtils.php';

/** Common pieces of the accounting report screens: permission, date parameters, entry links. */
abstract class Ledgers_ReportBase_View extends Vtiger_Index_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'DetailView'));
	}

	protected function dateParam(Vtiger_Request $request, $name, $default) {
		$value = $request->get($name);
		return ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) ? $value : $default;
	}

	/** First day of the current financial year (1 April, or the start set in Accounting Settings). */
	protected function yearStart() {
		$year = (int)date('n') >= 4 ? date('Y') : date('Y') - 1;
		return $year . '-04-01';
	}

	/** Cost centres / projects for a select: id => "Type / Name" (only the open ones unless $all). */
	protected function costCentreOptions($all = false) {
		global $adb;
		$options = array();
		if (Vtiger_Ledger_Utils::dimensionColumn('vtiger_journal_lines') == 'NULL') {
			return $options;
		}
		$result = $adb->pquery('SELECT c.costcentresid, c.costcentre_name, c.dimension_type FROM vtiger_costcentres c
			INNER JOIN vtiger_crmentity e ON e.crmid = c.costcentresid AND e.deleted = 0' . ($all ? '' : " WHERE c.status = 'Active'") . ' ORDER BY c.dimension_type, c.costcentre_name');
		while ($row = $adb->fetch_array($result)) {
			$options[$row['costcentresid']] = decode_html($row['dimension_type']) . ' / ' . decode_html($row['costcentre_name']);
		}
		return $options;
	}

	/** The cost centre filter of a report: array(selected id or 0, ids to sum (the centre and everything below it) or null). */
	protected function costCentreFilter(Vtiger_Request $request) {
		$selected = (int)$request->get('cc');
		return array($selected, $selected ? Vtiger_Ledger_Utils::costCentreTree($selected) : null);
	}

	/** Link to the record a journal entry came from, or null. */
	public static function sourceUrl($module, $id) {
		$modules = array('Invoice', 'PurchaseOrder', 'SalesOrder', 'Payments', 'BankTransactions', 'BankAccounts', 'Ledgers');
		return ($id && in_array($module, $modules)) ? 'index.php?module=' . $module . '&view=Detail&record=' . $id : null;
	}
}
