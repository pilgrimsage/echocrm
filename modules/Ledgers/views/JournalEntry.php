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

include_once 'modules/Ledgers/views/ReportBase.php';

/** Form for a manual journal entry (adjustments, accruals, corrections). */
class Ledgers_JournalEntry_View extends Ledgers_ReportBase_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'CreateView'));
	}

	public function process(Vtiger_Request $request) {
		global $adb;
		$ledgers = array();
		$result = $adb->pquery('SELECT l.ledgersid, l.ledger_name, l.ledger_group FROM vtiger_ledgers l
			INNER JOIN vtiger_crmentity c ON c.crmid = l.ledgersid AND c.deleted = 0 ORDER BY l.ledger_group, l.ledger_name');
		while ($row = $adb->fetch_array($result)) {
			$ledgers[$row['ledgersid']] = decode_html($row['ledger_group']) . ' / ' . decode_html($row['ledger_name']);
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('LEDGERS', $ledgers);
		$viewer->assign('COST_CENTRES', $this->costCentreOptions());
		$viewer->assign('TODAY', date('Y-m-d'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('JournalEntry.tpl', $request->getModule());
	}
}
