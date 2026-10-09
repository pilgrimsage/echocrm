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
 * Statement of one customer or vendor: every posting to the receivable / payable ledger tagged with
 * them (invoices, credit notes, payments, refunds) with a running balance. Parties are found by name.
 */
class Ledgers_PartyStatement_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		global $adb;
		$vendor = $request->get('type') == 'vendor';
		$partyId = (int)$request->get('party');
		$query = trim((string)$request->get('q'));
		$from = $this->dateParam($request, 'from', date('Y-04-01', strtotime((int)date('n') >= 4 ? 'today' : '-1 year')));
		$to = $this->dateParam($request, 'to', date('Y-m-d'));

		$matches = array();
		if (!$partyId && $query !== '') {
			$sql = $vendor
				? 'SELECT v.vendorid AS id, v.vendorname AS name FROM vtiger_vendor v INNER JOIN vtiger_crmentity c ON c.crmid = v.vendorid AND c.deleted = 0 WHERE v.vendorname LIKE ? ORDER BY v.vendorname LIMIT 30'
				: 'SELECT a.accountid AS id, a.accountname AS name FROM vtiger_account a INNER JOIN vtiger_crmentity c ON c.crmid = a.accountid AND c.deleted = 0 WHERE a.accountname LIKE ? ORDER BY a.accountname LIMIT 30';
			$result = $adb->pquery($sql, array($query . '%'));
			while ($row = $adb->fetch_array($result)) {
				$matches[] = array('id' => $row['id'], 'name' => decode_html($row['name']));
			}
		}

		$name = '';
		$rows = array();
		$opening = 0.0;
		$debit = $credit = 0.0;
		if ($partyId) {
			$nameResult = $adb->pquery($vendor ? 'SELECT vendorname FROM vtiger_vendor WHERE vendorid = ?' : 'SELECT accountname FROM vtiger_account WHERE accountid = ?', array($partyId));
			$name = $adb->num_rows($nameResult) ? decode_html($adb->query_result($nameResult, 0, 0)) : '';
			$ledger = Vtiger_Ledger_Utils::ledgerId($vendor ? 'Accounts Payable' : 'Accounts Receivable');
			$column = $vendor ? 'party_vendor' : 'party_account';
			// customers owe us on the debit side, we owe vendors on the credit side
			$sign = $vendor ? -1 : 1;
			$before = $adb->pquery("SELECT COALESCE(SUM(jl.debit - jl.credit), 0) AS s FROM vtiger_journal_lines jl INNER JOIN vtiger_journal_entries je ON je.entry_id = jl.entry_id
				WHERE jl.$column = ? AND jl.ledger_id = ? AND je.entry_date < ?", array($partyId, $ledger, $from));
			$opening = $sign * (float)$adb->query_result($before, 0, 's');
			$result = $adb->pquery("SELECT je.entry_id, je.entry_date, je.narration, je.source_module, je.source_id, jl.debit, jl.credit FROM vtiger_journal_lines jl
				INNER JOIN vtiger_journal_entries je ON je.entry_id = jl.entry_id
				WHERE jl.$column = ? AND jl.ledger_id = ? AND je.entry_date >= ? AND je.entry_date <= ? ORDER BY je.entry_date, je.entry_id, jl.line_id", array($partyId, $ledger, $from, $to));
			$balance = $opening;
			while ($row = $adb->fetch_array($result)) {
				$balance += $sign * ((float)$row['debit'] - (float)$row['credit']);
				$debit += (float)$row['debit'];
				$credit += (float)$row['credit'];
				$row['running'] = round($balance, 2);
				$row['display_date'] = Vtiger_Date_UIType::getDisplayDateValue($row['entry_date']);
				$row['entry_no'] = Vtiger_Ledger_Utils::entryNo($row['entry_id']);
				$row['url'] = self::sourceUrl($row['source_module'], $row['source_id']);
				$rows[] = $row;
			}
		}

		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('TYPE', $vendor ? 'vendor' : 'customer');
		$viewer->assign('PARTY_ID', $partyId);
		$viewer->assign('PARTY_NAME', $name);
		$viewer->assign('Q', $query);
		$viewer->assign('MATCHES', $matches);
		$viewer->assign('FROM', $from);
		$viewer->assign('TO', $to);
		$viewer->assign('OPENING', round($opening, 2));
		$viewer->assign('ROWS', $rows);
		$viewer->assign('CLOSING', $rows ? $rows[count($rows) - 1]['running'] : round($opening, 2));
		$viewer->assign('TOTAL_DEBIT', round($debit, 2));
		$viewer->assign('TOTAL_CREDIT', round($credit, 2));
		$viewer->view('PartyStatement.tpl', $request->getModule());
	}
}
