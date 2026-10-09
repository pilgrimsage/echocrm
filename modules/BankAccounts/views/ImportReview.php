<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
include_once 'include/utils/BankImport.php';

/** Review of an imported statement: confirm matches, book missing transactions, ignore lines. */
class BankAccounts_ImportReview_View extends Vtiger_Index_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'EditView'));
	}

	public function process(Vtiger_Request $request) {
		global $adb;
		$batch = Vtiger_Bank_Import::batch((int)$request->get('batch'));
		if (!$batch) {
			throw new AppException('The import does not exist.');
		}
		$ledgers = array();
		$result = $adb->pquery('SELECT l.ledgersid, l.ledger_name, l.ledger_group FROM vtiger_ledgers l INNER JOIN vtiger_crmentity c ON c.crmid = l.ledgersid AND c.deleted = 0 ORDER BY l.ledger_group, l.ledger_name');
		while ($row = $adb->fetch_array($result)) {
			$ledgers[$row['ledgersid']] = decode_html($row['ledger_group']) . ' / ' . decode_html($row['ledger_name']);
		}
		$show = (string)$request->get('show') ?: 'open';
		foreach ($batch['lines'] as &$line) {
			$line['display_date'] = Vtiger_Date_UIType::getDisplayDateValue($line['line_date']);
			$line['candidates'] = ($line['status'] == 'Unmatched' && $show == 'open') ? Vtiger_Bank_Import::candidatesFor($line['line_id']) : array();
		}
		unset($line);
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('BATCH', $batch);
		$viewer->assign('LEDGERS', $ledgers);
		$viewer->assign('SHOW', $show);
		$viewer->assign('MESSAGE', $request->get('message'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('ImportReview.tpl', $request->getModule());
	}
}
