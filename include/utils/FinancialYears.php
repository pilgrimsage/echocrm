<?php
/**
 * Financial years and year-end closing.
 *
 * A financial year runs 12 months from a start month set in Accounting Settings (April by default, any
 * month works). Closing a year, once it has ended and the year before it is closed:
 *   1. checks the books (blocking problems stop it, warnings must be acknowledged),
 *   2. posts one closing entry dated the last day of the year that clears every income and expense
 *      ledger into Retained Earnings (profit credited, loss debited),
 *   3. locks the books up to that day.
 * Balance sheet balances carry forward by themselves (they are cumulative); the income and expense
 * ledgers start the next year at zero because the closing entry cleared them. Period profit and loss
 * reports leave closing entries out, so a closed year still shows its profit.
 * Only the latest closed year can be reopened, which removes its closing entry and moves the lock back.
 */
class Vtiger_Financial_Years {

	public static function startMonth() {
		$month = (int)Vtiger_Ledger_Utils::getSetting('fy_start_month', 4);
		return ($month >= 1 && $month <= 12) ? $month : 4;
	}

	/** The financial year a date falls in: array(start, end, label, key). */
	public static function yearOf($date) {
		$month = self::startMonth();
		$time = strtotime($date);
		$year = (int)date('Y', $time);
		if ((int)date('n', $time) < $month) {
			$year--;
		}
		$start = sprintf('%04d-%02d-01', $year, $month);
		$end = date('Y-m-d', strtotime($start . ' +1 year -1 day'));
		$label = $month == 1 ? (string)$year : $year . '-' . substr((string)($year + 1), 2);
		return array('start' => $start, 'end' => $end, 'label' => $label, 'key' => (int)str_replace('-', '', $start));
	}

	/** Closing entries are tied to the year by this number (the start date as YYYYMMDD). */
	private static function keyOf($start) {
		return (int)str_replace('-', '', $start);
	}

	/** Years from the first one with any posting (or the books start) to the current one, newest first, each with its closing status. */
	public static function years() {
		global $adb;
		$first = $adb->pquery('SELECT MIN(entry_date) AS d FROM vtiger_journal_entries');
		$earliest = $adb->num_rows($first) ? $adb->query_result($first, 0, 'd') : null;
		$books = Vtiger_Ledger_Utils::getSetting('books_start');
		if ($books && (!$earliest || $books < $earliest)) {
			$earliest = $books;
		}
		$firstYear = self::yearOf($earliest ?: date('Y-m-d'));
		$current = self::yearOf(date('Y-m-d'));
		$closed = array();
		$result = $adb->pquery('SELECT * FROM vtiger_financial_years');
		while ($row = $adb->fetch_array($result)) {
			$closed[$row['year_start']] = $row;
		}
		$years = array();
		for ($start = $firstYear['start']; $start <= $current['start']; $start = date('Y-m-d', strtotime($start . ' +1 year'))) {
			$year = self::yearOf($start);
			$year['closed'] = isset($closed[$start]);
			$year['profit'] = $year['closed'] ? (float)$closed[$start]['profit'] : null;
			$year['closed_time'] = $year['closed'] ? $closed[$start]['closed_time'] : null;
			$year['ended'] = $year['end'] < date('Y-m-d');
			$years[] = $year;
		}
		return array_reverse($years);
	}

	/** The closing preview: income and expense balances that would be cleared, and the profit. */
	public static function preview($start) {
		$year = self::yearOf($start);
		$income = $expenses = array();
		$totalIncome = $totalExpenses = 0.0;
		foreach (Vtiger_Ledger_Utils::ledgerTotals($year['end']) as $row) {
			if ($row['grp'] == 'Income') {
				$balance = round($row['credit'] - $row['debit'], 2);
				if (abs($balance) >= 0.005) {
					$income[] = array('ledger' => $row['id'], 'name' => $row['name'], 'amount' => $balance);
					$totalIncome += $balance;
				}
			} elseif ($row['grp'] == 'Expenses') {
				$balance = round($row['debit'] - $row['credit'], 2);
				if (abs($balance) >= 0.005) {
					$expenses[] = array('ledger' => $row['id'], 'name' => $row['name'], 'amount' => $balance);
					$totalExpenses += $balance;
				}
			}
		}
		return array('year' => $year, 'income' => $income, 'expenses' => $expenses, 'total_income' => round($totalIncome, 2),
			'total_expenses' => round($totalExpenses, 2), 'profit' => round($totalIncome - $totalExpenses, 2));
	}

	/**
	 * What stands in the way of closing a year: array('blocking' => array(text), 'warnings' => array(array(text, url))).
	 */
	public static function checklist($start) {
		global $adb;
		$year = self::yearOf($start);
		$blocking = $warnings = array();
		$existing = $adb->pquery('SELECT 1 FROM vtiger_financial_years WHERE year_start = ?', array($year['start']));
		if ($adb->num_rows($existing)) {
			$blocking[] = 'This year is already closed.';
		}
		if ($year['end'] >= date('Y-m-d')) {
			$blocking[] = 'The year has not ended yet (it ends on ' . $year['end'] . ').';
		}
		foreach (self::years() as $other) {
			if ($other['start'] < $year['start'] && !$other['closed'] && self::hasPostings($other['start'], $other['end'])) {
				$blocking[] = 'The year ' . $other['label'] . ' is not closed yet. Close years in order.';
				break;
			}
		}
		$totals = Vtiger_Ledger_Utils::ledgerTotals($year['end']);
		$debit = $credit = 0.0;
		foreach ($totals as $t) {
			$debit += $t['debit'];
			$credit += $t['credit'];
		}
		if (abs($debit - $credit) > 0.004) {
			$blocking[] = sprintf('The trial balance does not balance (debits %s, credits %s). Rebuild the postings first.', number_format($debit, 2, '.', ''), number_format($credit, 2, '.', ''));
		}

		// warnings
		$suspense = Vtiger_Ledger_Utils::ledgerId('Suspense Account', 'Liabilities');
		$sums = Vtiger_Ledger_Utils::sumsByLedger(null, $year['end'], $suspense);
		$suspenseBalance = round(($sums[$suspense][1] ?? 0) - ($sums[$suspense][0] ?? 0), 2);
		if (abs($suspenseBalance) >= 0.005) {
			$warnings[] = array('The Suspense Account still holds ' . number_format($suspenseBalance, 2) . ': bank entries without a ledger.', 'index.php?module=Ledgers&view=Statement&record=' . $suspense . '&to=' . $year['end']);
		}
		$open = $adb->pquery("SELECT COUNT(*) AS n FROM vtiger_banktransactions t INNER JOIN vtiger_crmentity c ON c.crmid = t.banktransactionsid AND c.deleted = 0
			WHERE t.transaction_date >= ? AND t.transaction_date <= ? AND (t.reconciled IS NULL OR t.reconciled = 0)", array($year['start'], $year['end']));
		if ((int)$adb->query_result($open, 0, 'n') > 0) {
			$warnings[] = array((int)$adb->query_result($open, 0, 'n') . ' bank transactions of the year are not reconciled with a bank statement.', 'index.php?module=BankAccounts&view=Statement');
		}
		$drafts = $adb->pquery("SELECT COUNT(*) AS n FROM vtiger_invoice i INNER JOIN vtiger_crmentity c ON c.crmid = i.invoiceid AND c.deleted = 0
			WHERE i.invoicedate >= ? AND i.invoicedate <= ? AND i.invoicestatus IN ('Created', 'AutoCreated')", array($year['start'], $year['end']));
		if ((int)$adb->query_result($drafts, 0, 'n') > 0) {
			$warnings[] = array((int)$adb->query_result($drafts, 0, 'n') . ' invoices dated in the year are still Created (not approved), so they are not on the books.', 'index.php?module=Invoice&view=List');
		}
		if (Vtiger_Stock_Utils::enabled()) {
			foreach (Vtiger_Stock_Utils::checks() as $check) {
				if ($check['count'] > 0 && strpos($check['title'], 'Inventory ledger') !== false) {
					$warnings[] = array($check['hint'], 'index.php?module=Ledgers&view=StockValuation&to=' . $year['end']);
				}
			}
		}
		return array('blocking' => $blocking, 'warnings' => $warnings);
	}

	private static function hasPostings($from, $to) {
		global $adb;
		$result = $adb->pquery('SELECT 1 FROM vtiger_journal_entries WHERE entry_date >= ? AND entry_date <= ? LIMIT 1', array($from, $to));
		return $adb->num_rows($result) > 0;
	}

	/** Closes a year. Throws with the reason when it cannot be closed. */
	public static function close($start, $acknowledged) {
		global $adb, $current_user;
		$year = self::yearOf($start);
		$check = self::checklist($year['start']);
		if ($check['blocking']) {
			throw new Exception(implode(' ', $check['blocking']));
		}
		if ($check['warnings'] && !$acknowledged) {
			throw new Exception('Tick the box to confirm you want to close the year with the warnings shown.');
		}
		$preview = self::preview($year['start']);
		$lines = array();
		foreach ($preview['income'] as $row) {
			$lines[] = array('ledger' => $row['ledger'], 'debit' => $row['amount'] > 0 ? $row['amount'] : 0, 'credit' => $row['amount'] < 0 ? -$row['amount'] : 0);
		}
		foreach ($preview['expenses'] as $row) {
			$lines[] = array('ledger' => $row['ledger'], 'debit' => $row['amount'] < 0 ? -$row['amount'] : 0, 'credit' => $row['amount'] > 0 ? $row['amount'] : 0);
		}
		$retained = Vtiger_Ledger_Utils::ledgerId('Retained Earnings', 'Equity');
		$profit = $preview['profit'];
		$lines[] = array('ledger' => $retained, 'debit' => $profit < 0 ? -$profit : 0, 'credit' => $profit > 0 ? $profit : 0);
		$entryId = Vtiger_Ledger_Utils::postClosing($year['end'], 'Closing entry, financial year ' . $year['label'], $lines, $year['key']);
		$adb->pquery('INSERT INTO vtiger_financial_years (year_start, year_end, label, profit, closed_by, closed_time, closing_entry_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)',
			array($year['start'], $year['end'], $year['label'], $profit, $current_user->id, $entryId));
		// the closed year can no longer be changed
		Vtiger_Ledger_Utils::setSetting('lock_date', $year['end']);
		return $entryId;
	}

	/** Reopens the latest closed year: removes its closing entry and moves the lock back to the year before. */
	public static function reopen($start) {
		global $adb;
		$year = self::yearOf($start);
		$closed = $adb->pquery('SELECT year_start FROM vtiger_financial_years ORDER BY year_start DESC LIMIT 1');
		if (!$adb->num_rows($closed) || $adb->query_result($closed, 0, 0) != $year['start']) {
			throw new Exception('Only the most recently closed year can be reopened.');
		}
		Vtiger_Ledger_Utils::removeClosing($year['key']);
		$adb->pquery('DELETE FROM vtiger_financial_years WHERE year_start = ?', array($year['start']));
		$previous = $adb->pquery('SELECT year_end FROM vtiger_financial_years ORDER BY year_start DESC LIMIT 1');
		$lock = Vtiger_Ledger_Utils::lockDate();
		if ($lock == $year['end']) {
			Vtiger_Ledger_Utils::setSetting('lock_date', $adb->num_rows($previous) ? $adb->query_result($previous, 0, 0) : null);
		}
	}
}
