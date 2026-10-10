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
			array('LBL_FINANCIAL_YEARS', 'FinancialYears'), array('LBL_BUDGETS', 'Budgets'), array('LBL_BUDGET_REPORT', 'BudgetReport'), array('LBL_PROFIT_LOSS', 'ProfitLoss'), array('LBL_BALANCE_SHEET', 'BalanceSheet'),
			array('LBL_ASSET_REGISTER', 'AssetRegister'), array('LBL_RUN_DEPRECIATION', 'DepreciationRun'), array('LBL_REVALUATION', 'Revaluation'), array('LBL_BOOKS_HEALTH', 'BooksHealth'), array('LBL_AUDIT_TRAIL', 'AuditTrail'), array('LBL_ASSET_CLASSES', 'AssetClasses'), array('LBL_STOCK_VALUATION', 'StockValuation'), array('LBL_STOCK_LEDGER', 'StockLedger'), array('LBL_STOCK_ADJUSTMENT', 'StockAdjustment'), array('LBL_GST_RETURNS', 'GstReturns'), array('LBL_COST_CENTRE_REPORT', 'CostCentreReport'), array('LBL_CUSTOMER_STATEMENT', 'PartyStatement&type=customer'), array('LBL_VENDOR_STATEMENT', 'PartyStatement&type=vendor'),
			array('LBL_RECEIVABLES_AGEING', 'Ageing&type=customer'), array('LBL_PAYABLES_AGEING', 'Ageing&type=vendor'),
		) as $report) {
			$links[] = array('linktype' => 'BASIC', 'linklabel' => $report[0], 'linkurl' => 'index.php?module=Ledgers&view=' . $report[1], 'linkicon' => 'fa-list');
		}
		if (Users_Record_Model::getCurrentUserModel()->isAdminUser()) {
			$links[] = array('linktype' => 'BASIC', 'linklabel' => 'LBL_ACCOUNTING_SETTINGS', 'linkurl' => 'index.php?module=Ledgers&view=AccountingSettings', 'linkicon' => 'fa-cog');
		}
		return $links;
	}
}
