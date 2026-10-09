<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

/** Adds the journal, reports and accounting settings to the ledger screens. */
class Ledgers_Module_Model extends Vtiger_Module_Model {

	public function getModuleBasicLinks() {
		$links = parent::getModuleBasicLinks();
		foreach (array(
			array('LBL_JOURNAL', 'Journal'), array('LBL_TRIAL_BALANCE', 'TrialBalance'), array('LBL_LEDGER_STATEMENT', 'Statement'),
			array('LBL_PROFIT_LOSS', 'ProfitLoss'), array('LBL_BALANCE_SHEET', 'BalanceSheet'),
		) as $report) {
			$links[] = array('linktype' => 'BASIC', 'linklabel' => $report[0], 'linkurl' => 'index.php?module=Ledgers&view=' . $report[1], 'linkicon' => 'fa-list');
		}
		if (Users_Record_Model::getCurrentUserModel()->isAdminUser()) {
			$links[] = array('linktype' => 'BASIC', 'linklabel' => 'LBL_ACCOUNTING_SETTINGS', 'linkurl' => 'index.php?module=Ledgers&view=AccountingSettings', 'linkicon' => 'fa-cog');
		}
		return $links;
	}
}
