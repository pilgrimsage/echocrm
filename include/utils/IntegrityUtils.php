<?php
/**
 * Health check of the books: finds what a bug, a failed save or a direct database change can leave behind,
 * and repairs what can be repaired safely.
 *
 *   - entries whose debits and credits differ (reported only: the cause has to be understood),
 *   - documents and payments that should be on the books but have no entry (repair: post them),
 *   - entries whose document is gone or no longer posts (repair: remove the entry),
 *   - monthly totals that differ from the lines (repair: rebuild the totals),
 *   - lines that point to a deleted ledger (reported only).
 * Repairs never touch locked periods; those are reported.
 */
class Vtiger_Integrity_Utils {

	const SAMPLE = 10;

	private static function documentTypes() {
		return array(
			'Invoice' => array('vtiger_invoice', 'invoiceid', 'invoice_no', 'invoicestatus', 'Invoice'),
			'PurchaseOrder' => array('vtiger_purchaseorder', 'purchaseorderid', 'purchaseorder_no', 'postatus', 'Purchase order'),
			'SalesOrder' => array('vtiger_salesorder', 'salesorderid', 'salesorder_no', 'sostatus', 'Credit / debit note'),
		);
	}

	private static function rows($sql, array $params = array()) {
		global $adb;
		$result = $adb->pquery($sql, $params);
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$rows[] = $row;
		}
		return $rows;
	}

	/** Documents and payments that should have an entry and have none: array(array(module, id, label)). */
	public static function missing() {
		include_once 'include/utils/LedgerUtils.php';
		$out = array();
		foreach (self::documentTypes() as $module => $t) {
			list($table, $idColumn, $noColumn, $statusColumn, $label) = $t;
			$statuses = Vtiger_Ledger_Utils::postedStatuses()[$module];
			$marks = implode(',', array_fill(0, count($statuses), '?'));
			foreach (self::rows("SELECT d.$idColumn AS id, d.$noColumn AS no FROM $table d INNER JOIN vtiger_crmentity c ON c.crmid = d.$idColumn AND c.deleted = 0
				WHERE d.$statusColumn IN ($marks) AND d.total > 0.004 AND NOT EXISTS (SELECT 1 FROM vtiger_journal_entries e WHERE e.source_module = ? AND e.source_id = d.$idColumn AND e.source_key = 'doc')
				LIMIT 5000", array_merge($statuses, array($module))) as $r) {
				$out[] = array($module, $r['id'], $label . ' ' . decode_html($r['no']));
			}
		}
		foreach (self::rows("SELECT p.paymentsid AS id, p.payment_no AS no FROM vtiger_payments p INNER JOIN vtiger_crmentity c ON c.crmid = p.paymentsid AND c.deleted = 0
			WHERE p.status = 'Completed' AND p.amount > 0 AND NOT EXISTS (SELECT 1 FROM vtiger_journal_entries e WHERE e.source_module = 'Payments' AND e.source_id = p.paymentsid AND e.source_key = 'payment') LIMIT 5000") as $r) {
			$out[] = array('Payments', $r['id'], 'Payment ' . decode_html($r['no']));
		}
		return $out;
	}

	/** System entries whose document or payment is gone or no longer posts: array(array(entry id, text)). */
	public static function orphans() {
		include_once 'include/utils/LedgerUtils.php';
		$out = array();
		foreach (self::documentTypes() as $module => $t) {
			list($table, $idColumn, $noColumn, $statusColumn) = $t;
			$statuses = Vtiger_Ledger_Utils::postedStatuses()[$module];
			$marks = implode(',', array_fill(0, count($statuses), '?'));
			foreach (self::rows("SELECT e.entry_id, e.narration FROM vtiger_journal_entries e WHERE e.source_module = ? AND e.source_key = 'doc' AND e.entry_type = 'auto'
				AND NOT EXISTS (SELECT 1 FROM $table d INNER JOIN vtiger_crmentity c ON c.crmid = d.$idColumn AND c.deleted = 0 WHERE d.$idColumn = e.source_id AND d.$statusColumn IN ($marks) AND d.total > 0.004) LIMIT 5000",
				array_merge(array($module), $statuses)) as $r) {
				$out[] = array($r['entry_id'], 'JV' . $r['entry_id'] . ' ' . decode_html($r['narration']));
			}
		}
		foreach (self::rows("SELECT e.entry_id, e.narration FROM vtiger_journal_entries e WHERE e.source_module = 'Payments' AND e.source_key = 'payment' AND e.entry_type = 'auto'
			AND NOT EXISTS (SELECT 1 FROM vtiger_payments p INNER JOIN vtiger_crmentity c ON c.crmid = p.paymentsid AND c.deleted = 0 WHERE p.paymentsid = e.source_id AND p.status = 'Completed' AND p.amount > 0) LIMIT 5000") as $r) {
			$out[] = array($r['entry_id'], 'JV' . $r['entry_id'] . ' ' . decode_html($r['narration']));
		}
		return $out;
	}

	/** Entries that do not balance: array(array(entry id, debit, credit)). */
	public static function unbalanced() {
		return self::rows('SELECT entry_id, SUM(debit) AS debit, SUM(credit) AS credit FROM vtiger_journal_lines GROUP BY entry_id HAVING ABS(SUM(debit) - SUM(credit)) > 0.004 LIMIT 5000');
	}

	/** Ledger-months where the monthly total differs from the lines (or exists without lines). */
	public static function totalsMismatch() {
		global $adb;
		$hasKind = in_array('kind', $adb->getColumnNames('vtiger_ledger_balances'));
		$kind = $hasKind ? ", IF(je.entry_type = 'closing', 1, 0)" : '';
		$kindSelect = $hasKind ? ', IF(je.entry_type = \'closing\', 1, 0) AS kind' : ', 0 AS kind';
		$join = $hasKind ? ' AND b.kind = a.kind' : '';
		$lines = "SELECT jl.ledger_id, DATE_FORMAT(je.entry_date, '%Y-%m') AS ym$kindSelect, SUM(jl.debit) AS debit, SUM(jl.credit) AS credit FROM vtiger_journal_lines jl
			INNER JOIN vtiger_journal_entries je ON je.entry_id = jl.entry_id GROUP BY jl.ledger_id, DATE_FORMAT(je.entry_date, '%Y-%m')$kind";
		$bKind = $hasKind ? ', b.kind' : '';
		$rows = self::rows("SELECT a.ledger_id, a.ym, a.debit, a.credit, b.debit AS sd, b.credit AS sc FROM ($lines) a LEFT JOIN vtiger_ledger_balances b ON b.ledger_id = a.ledger_id AND b.ym = a.ym$join
			WHERE b.ledger_id IS NULL OR ABS(b.debit - a.debit) > 0.004 OR ABS(b.credit - a.credit) > 0.004 LIMIT 5000");
		$extra = self::rows("SELECT b.ledger_id, b.ym, 0 AS debit, 0 AS credit, b.debit AS sd, b.credit AS sc FROM vtiger_ledger_balances b WHERE (b.debit <> 0 OR b.credit <> 0) AND NOT EXISTS (
			SELECT 1 FROM vtiger_journal_lines jl INNER JOIN vtiger_journal_entries je ON je.entry_id = jl.entry_id WHERE jl.ledger_id = b.ledger_id AND DATE_FORMAT(je.entry_date, '%Y-%m') = b.ym) LIMIT 5000");
		return array_merge($rows, $extra);
	}

	public static function deletedLedgerLines() {
		return self::rows('SELECT jl.line_id, jl.entry_id, jl.ledger_id FROM vtiger_journal_lines jl LEFT JOIN vtiger_crmentity c ON c.crmid = jl.ledger_id AND c.deleted = 0 WHERE c.crmid IS NULL LIMIT 5000');
	}

	/**
	 * All checks: array(array(key, title, count, hint, sample (array of text), fix (repair key or null))).
	 */
	public static function checks() {
		$checks = array();
		$unbalanced = self::unbalanced();
		$checks[] = array('unbalanced', 'Entries where debits and credits differ', count($unbalanced), 'Open the entry in the Journal and find out where it came from; the books are out of balance by its difference.',
			array_map(function ($r) { return 'JV' . $r['entry_id'] . ': debit ' . number_format($r['debit'], 2) . ', credit ' . number_format($r['credit'], 2); }, array_slice($unbalanced, 0, self::SAMPLE)), null);
		$missing = self::missing();
		$checks[] = array('missing', 'Documents and payments that should be on the books but are not', count($missing), 'Post them now. Records dated in a locked period are reported instead.',
			array_map(function ($r) { return $r[2]; }, array_slice($missing, 0, self::SAMPLE)), 'repost');
		$orphans = self::orphans();
		$checks[] = array('orphans', 'Entries whose document or payment is deleted, cancelled or no longer posts', count($orphans), 'Remove these entries so the books follow the documents.',
			array_map(function ($r) { return $r[1]; }, array_slice($orphans, 0, self::SAMPLE)), 'remove');
		$totals = self::totalsMismatch();
		$checks[] = array('totals', 'Monthly totals that differ from the journal lines', count($totals), 'Rebuild the monthly totals from the lines (reports read them).',
			array_map(function ($r) { return 'ledger ' . $r['ledger_id'] . ', ' . $r['ym'] . ': lines ' . number_format($r['debit'], 2) . ' / ' . number_format($r['credit'], 2) . ', total ' . number_format((float)$r['sd'], 2) . ' / ' . number_format((float)$r['sc'], 2); }, array_slice($totals, 0, self::SAMPLE)), 'totals');
		$gone = self::deletedLedgerLines();
		$checks[] = array('ledgers', 'Journal lines that point to a deleted ledger', count($gone), 'Restore the ledger from the recycle bin, or reverse the entry.',
			array_map(function ($r) { return 'JV' . $r['entry_id'] . ', ledger ' . $r['ledger_id']; }, array_slice($gone, 0, self::SAMPLE)), null);
		return $checks;
	}

	/** Posts every missing document / payment. Returns array(posted, problems). */
	public static function repostMissing() {
		include_once 'include/utils/LedgerUtils.php';
		$posted = 0;
		$problems = array();
		foreach (self::missing() as $m) {
			try {
				if ($m[0] == 'Payments') {
					Vtiger_Ledger_Utils::syncPayment($m[1]);
				} else {
					Vtiger_Ledger_Utils::syncDocument($m[0], $m[1]);
				}
				$posted++;
			} catch (Exception $e) {
				$problems[] = $m[2] . ': ' . $e->getMessage();
			}
		}
		return array($posted, $problems);
	}

	/** Removes the entries of documents that are gone. Returns array(removed, problems). */
	public static function removeOrphans() {
		include_once 'include/utils/LedgerUtils.php';
		$removed = 0;
		$problems = array();
		foreach (self::orphans() as $o) {
			try {
				Vtiger_Ledger_Utils::removeSystemEntry($o[0]);
				$removed++;
			} catch (Exception $e) {
				$problems[] = $o[1] . ': ' . $e->getMessage();
			}
		}
		return array($removed, $problems);
	}

	public static function rebuildTotals() {
		include_once 'include/utils/LedgerUtils.php';
		Vtiger_Ledger_Utils::rebuildMonthlyTotals();
	}
}
