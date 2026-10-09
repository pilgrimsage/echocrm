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

/** The journal: every entry with its lines, newest first. Entries made by hand can be reversed. */
class Ledgers_Journal_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		global $adb;
		$from = $this->dateParam($request, 'from', date('Y-m-01', strtotime('-2 months')));
		$to = $this->dateParam($request, 'to', date('Y-m-d'));
		$source = (string)$request->get('source');
		$sql = 'SELECT * FROM vtiger_journal_entries WHERE entry_date >= ? AND entry_date <= ?';
		$params = array($from, $to);
		if ($source !== '') {
			$sql .= ' AND source_module = ?';
			$params[] = $source;
		}
		$result = $adb->pquery($sql . ' ORDER BY entry_date DESC, entry_id DESC LIMIT 300', $params);
		$entries = array();
		while ($entry = $adb->fetch_array($result)) {
			$entry['no'] = Vtiger_Ledger_Utils::entryNo($entry['entry_id']);
			$entry['display_date'] = Vtiger_Date_UIType::getDisplayDateValue($entry['entry_date']);
			$entry['url'] = self::sourceUrl($entry['source_module'], $entry['source_id']);
			$entry['can_reverse'] = $entry['entry_type'] == 'manual' && $entry['status'] == 'Posted';
			$lines = $adb->pquery('SELECT l.debit, l.credit, l.memo, g.ledger_name, g.ledgersid FROM vtiger_journal_lines l
				LEFT JOIN vtiger_ledgers g ON g.ledgersid = l.ledger_id WHERE l.entry_id = ? ORDER BY l.line_id', array($entry['entry_id']));
			$entry['lines'] = array();
			while ($line = $adb->fetch_array($lines)) {
				$entry['lines'][] = $line;
			}
			$entries[] = $entry;
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('FROM', $from);
		$viewer->assign('TO', $to);
		$viewer->assign('SOURCE', $source);
		$viewer->assign('ENTRIES', $entries);
		$viewer->assign('LOCK_DATE', Vtiger_Ledger_Utils::lockDate());
		$viewer->assign('CAN_EDIT', Users_Privileges_Model::isPermitted($request->getModule(), 'EditView'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->view('Journal.tpl', $request->getModule());
	}
}
