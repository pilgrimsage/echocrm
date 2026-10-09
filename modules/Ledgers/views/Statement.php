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

/** General ledger: every journal line of one ledger in a period, with the running balance. */
class Ledgers_Statement_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		global $adb;
		$ledgers = array();
		$result = $adb->pquery('SELECT l.ledgersid, l.ledger_name, l.ledger_group FROM vtiger_ledgers l
			INNER JOIN vtiger_crmentity c ON c.crmid = l.ledgersid AND c.deleted = 0 ORDER BY l.ledger_group, l.ledger_name');
		while ($row = $adb->fetch_array($result)) {
			$ledgers[$row['ledgersid']] = decode_html($row['ledger_group']) . ' / ' . decode_html($row['ledger_name']);
		}
		$ledgerId = (int)$request->get('record');
		if (!$ledgerId && $ledgers) {
			$ledgerId = (int)key($ledgers);
		}
		$from = $this->dateParam($request, 'from', $this->yearStart());
		$to = $this->dateParam($request, 'to', date('Y-m-d'));
		$book = $ledgerId ? Vtiger_Ledger_Utils::generalLedger($ledgerId, $from, $to) : null;
		if ($book) {
			foreach ($book['rows'] as &$row) {
				$row['display_date'] = Vtiger_Date_UIType::getDisplayDateValue($row['entry_date']);
				$row['url'] = self::sourceUrl($row['source_module'], $row['source_id']);
				$row['entry_no'] = Vtiger_Ledger_Utils::entryNo($row['entry_id']);
			}
			unset($row);
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('LEDGERS', $ledgers);
		$viewer->assign('LEDGER_ID', $ledgerId);
		$viewer->assign('FROM', $from);
		$viewer->assign('TO', $to);
		$viewer->assign('BOOK', $book);
		$viewer->view('Statement.tpl', $request->getModule());
	}
}
