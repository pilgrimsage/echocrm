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

	/** Link to the record a journal entry came from, or null. */
	public static function sourceUrl($module, $id) {
		$modules = array('Invoice', 'PurchaseOrder', 'SalesOrder', 'Payments', 'BankTransactions', 'BankAccounts', 'Ledgers');
		return ($id && in_array($module, $modules)) ? 'index.php?module=' . $module . '&view=Detail&record=' . $id : null;
	}
}
