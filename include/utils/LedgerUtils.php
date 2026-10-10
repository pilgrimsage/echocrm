<?php
/**
 * Double-entry bookkeeping.
 *
 * Every posting is a journal entry (vtiger_journal_entries) with lines (vtiger_journal_lines), each
 * line a debit or a credit to a ledger, and an entry only exists when its debits equal its credits.
 * Entries made by the system are tied to the record they come from (invoice, purchase order, note,
 * payment, bank transaction, opening balance) and are rebuilt whenever that record changes, so the
 * books always agree with the documents. Entries typed in by hand are never changed, only reversed.
 * Books can be locked up to a date: nothing dated on or before it can be posted, changed or reversed.
 *
 * Ledger sign convention: Assets and Expenses carry debit balances; Liabilities, Income and Equity
 * carry credit balances.
 */
include_once 'include/utils/StockUtils.php';

class Vtiger_Ledger_Utils {

	/** Statuses in which a document is posted to the books. */
	private static $postedStatuses = array(
		'Invoice' => array('Approved', 'Sent', 'Credit Invoice', 'Paid'),
		'PurchaseOrder' => array('Approved', 'Delivered', 'Received Shipment'),
		'SalesOrder' => array('Approved', 'Sent'),
	);

	private static $ledgerCache = array();

	/**
	 * The cost centre / project the lines being built belong to (null = none). Set while a document,
	 * payment or bank transaction is posted so every line it creates carries it.
	 */
	private static $dimension = null;

	private static $columnCache = array();

	/** Whether the monthly totals separate closing entries (the column exists once bin/create-financial-years.php has run). */
	private static function hasKind() {
		global $adb;
		static $has = null;
		if ($has === null) {
			$has = in_array('kind', $adb->getColumnNames('vtiger_ledger_balances'));
		}
		return $has;
	}

	/** 'cost_centre' when the table has that column (the dimension is switched on), else 'NULL' for use in SQL. */
	public static function dimensionColumn($table, $alias = '') {
		global $adb;
		if (!isset(self::$columnCache[$table])) {
			self::$columnCache[$table] = in_array('cost_centre', $adb->getColumnNames($table));
		}
		return self::$columnCache[$table] ? ($alias ? $alias . '.' : '') . 'cost_centre' : 'NULL';
	}

	// ---- settings -------------------------------------------------------------------------------

	public static function getSetting($name, $default = null) {
		global $adb;
		$result = $adb->pquery('SELECT value FROM vtiger_accounting_settings WHERE name = ?', array($name));
		return $adb->num_rows($result) ? $adb->query_result($result, 0, 'value') : $default;
	}

	public static function setSetting($name, $value) {
		global $adb;
		$adb->pquery('DELETE FROM vtiger_accounting_settings WHERE name = ?', array($name));
		if ($value !== null && $value !== '') {
			$adb->pquery('INSERT INTO vtiger_accounting_settings (name, value) VALUES (?, ?)', array($name, $value));
		}
	}

	/** Date up to which the books are locked (Y-m-d), or null. */
	public static function lockDate() {
		$date = self::getSetting('lock_date');
		return $date ?: null;
	}

	/**
	 * A date as Y-m-d. Records saved from the full form arrive in database format, but inline edits and
	 * some imports hand over the user's display format (09-24-2026), which compares wrongly as a string.
	 */
	public static function dbDate($value) {
		$value = trim((string)$value);
		if ($value === '' || $value === '0000-00-00') {
			return null;
		}
		if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
			return substr($value, 0, 10);
		}
		$converted = DateTimeField::convertToDBFormat($value);
		return $converted ?: null;
	}

	/** True only while a repair tool (the full repost) rebuilds postings of locked periods from their documents. */
	private static $bypassLock = false;

	public static function isLocked($date) {
		if (self::$bypassLock) {
			return false;
		}
		$date = self::dbDate($date);
		$lock = self::lockDate();
		return $lock && $date && $date <= $lock;
	}

	/** Why something dated $date cannot be posted or changed, or null. */
	public static function lockProblem($date) {
		$date = self::dbDate($date);
		if (self::isLocked($date)) {
			return 'The books are locked up to ' . self::lockDate() . ', so nothing dated on or before that day can be added or changed.';
		}
		return null;
	}

	// ---- ledgers --------------------------------------------------------------------------------

	/**
	 * The ledgers the automatic postings use, by role: key => array(label, default ledger name, group).
	 * What each role points to is set in Accounting Settings, so a business can post sales to
	 * "Service Revenue" or "Tuition Fees" instead of "Sales" without any code change; until it is
	 * set, the default ledger of that name is used (and created when missing).
	 */
	public static function postingRoles() {
		return array(
			'receivable' => array('Customers owe us (receivable)', 'Accounts Receivable', 'Assets'),
			'payable' => array('We owe vendors (payable)', 'Accounts Payable', 'Liabilities'),
			'sales' => array('Sales / revenue', 'Sales', 'Income'),
			'sales_returns' => array('Sales returns (credit notes)', 'Sales Returns', 'Income'),
			'purchases' => array('Purchases / cost of goods', 'Purchases', 'Expenses'),
			'purchase_returns' => array('Purchase returns (debit notes)', 'Purchase Returns', 'Expenses'),
			'round_off' => array('Round off', 'Round Off', 'Expenses'),
			'retained_earnings' => array('Retained earnings (profit carried forward at year end)', 'Retained Earnings', 'Equity'),
			'inventory' => array('Inventory (stock on hand)', 'Inventory', 'Assets'),
			'goods_in_transit' => array('Goods ordered, not yet received', 'Goods in Transit', 'Assets'),
			'cogs' => array('Cost of goods sold', 'Cost of Goods Sold', 'Expenses'),
			'stock_adjustment' => array('Stock write-offs and count differences', 'Stock Adjustments and Write-offs', 'Expenses'),
			'customer_advances' => array('Advances from customers (quote payments)', 'Customer Advances', 'Liabilities'),
			'suspense' => array('Suspense (uncategorised bank entries)', 'Suspense Account', 'Liabilities'),
			'opening_equity' => array('Opening balances', 'Opening Balance Equity', 'Equity'),
			'cash' => array('Cash (payments without a bank account)', 'Cash in Hand', 'Assets'),
			'bank_default' => array('Bank (fallback)', 'Bank Accounts', 'Assets'),
			'output_cgst' => array('Output CGST', 'Output CGST', 'Liabilities'),
			'output_sgst' => array('Output SGST', 'Output SGST', 'Liabilities'),
			'output_igst' => array('Output IGST', 'Output IGST', 'Liabilities'),
			'output_other' => array('Output tax (other)', 'Output Tax (Other)', 'Liabilities'),
			'input_cgst' => array('Input CGST', 'Input CGST', 'Assets'),
			'input_sgst' => array('Input SGST', 'Input SGST', 'Assets'),
			'input_igst' => array('Input IGST', 'Input IGST', 'Assets'),
			'input_other' => array('Input tax (other)', 'Input Tax (Other)', 'Assets'),
		);
	}

	/** Ledger id configured for a role (null when the role has no setting yet). */
	public static function configuredAccount($key) {
		global $adb;
		$result = $adb->pquery('SELECT p.ledger_id FROM vtiger_posting_accounts p INNER JOIN vtiger_crmentity c ON c.crmid = p.ledger_id AND c.deleted = 0 WHERE p.account_key = ?', array($key));
		return $adb->num_rows($result) ? $adb->query_result($result, 0, 0) : null;
	}

	public static function setAccount($key, $ledgerId) {
		global $adb;
		if (!isset(self::postingRoles()[$key])) {
			throw new Exception("Unknown posting account '$key'.");
		}
		if (!self::ledgerGroup($ledgerId)) {
			throw new Exception('The ledger does not exist.');
		}
		$adb->pquery('DELETE FROM vtiger_posting_accounts WHERE account_key = ?', array($key));
		$adb->pquery('INSERT INTO vtiger_posting_accounts (account_key, ledger_id) VALUES (?, ?)', array($key, $ledgerId));
		self::$ledgerCache = array();
	}

	/**
	 * Id of a ledger by name; created in the given group when it does not exist yet. A name that is
	 * the default of a posting role resolves to the ledger configured for that role.
	 */
	public static function ledgerId($name, $group = 'Assets') {
		global $adb, $current_user;
		if (isset(self::$ledgerCache[$name])) {
			return self::$ledgerCache[$name];
		}
		foreach (self::postingRoles() as $key => $role) {
			if ($role[1] === $name) {
				$configured = self::configuredAccount($key);
				if ($configured) {
					return self::$ledgerCache[$name] = $configured;
				}
				break;
			}
		}
		$result = $adb->pquery('SELECT l.ledgersid FROM vtiger_ledgers l INNER JOIN vtiger_crmentity c ON c.crmid = l.ledgersid AND c.deleted = 0 WHERE l.ledger_name = ?', array($name));
		if ($adb->num_rows($result)) {
			return self::$ledgerCache[$name] = $adb->query_result($result, 0, 0);
		}
		$focus = CRMEntity::getInstance('Ledgers');
		$focus->column_fields['ledger_name'] = $name;
		$focus->column_fields['ledger_group'] = $group;
		$focus->column_fields['assigned_user_id'] = $current_user ? $current_user->id : 1;
		$focus->save('Ledgers');
		return self::$ledgerCache[$name] = $focus->id;
	}

	public static function ledgerGroup($ledgerId) {
		global $adb;
		$result = $adb->pquery('SELECT ledger_group FROM vtiger_ledgers WHERE ledgersid = ?', array($ledgerId));
		return $adb->num_rows($result) ? decode_html($adb->query_result($result, 0, 0)) : null;
	}

	public static function isDebitGroup($group) {
		return in_array($group, array('Assets', 'Expenses'), true);
	}

	/** Ledger of a bank account (created on first use, named after the account). */
	public static function bankLedger($accountId) {
		global $adb;
		$result = $adb->pquery('SELECT ledger, account_name, account_type FROM vtiger_bankaccounts WHERE bankaccountsid = ?', array($accountId));
		if (!$adb->num_rows($result)) {
			return self::ledgerId('Bank Accounts');
		}
		$row = $adb->fetch_array($result);
		if (!empty($row['ledger'])) {
			return $row['ledger'];
		}
		$group = $row['account_type'] == 'Credit Card' ? 'Liabilities' : 'Assets';
		$id = self::ledgerId(decode_html($row['account_name']), $group);
		$adb->pquery('UPDATE vtiger_bankaccounts SET ledger = ? WHERE bankaccountsid = ?', array($id, $accountId));
		return $id;
	}

	// ---- entries --------------------------------------------------------------------------------

	/** A line: array(ledger id, debit, credit, party account, party vendor, memo). */
	private static function line($ledger, $debit, $credit, $account = null, $vendor = null, $memo = '') {
		return array('ledger' => $ledger, 'debit' => round($debit, 2), 'credit' => round($credit, 2), 'account' => $account ?: null, 'vendor' => $vendor ?: null, 'memo' => $memo, 'cost' => self::$dimension ?: null);
	}

	/** Merges lines of the same ledger and party so the stored entry is as short as it can be, drops empty ones. */
	private static function normalize(array $lines) {
		$merged = array();
		foreach ($lines as $l) {
			if (abs($l['debit']) < 0.005 && abs($l['credit']) < 0.005) {
				continue;
			}
			$key = $l['ledger'] . '|' . $l['account'] . '|' . $l['vendor'] . '|' . $l['memo'] . '|' . (int)$l['cost'];
			if (!isset($merged[$key])) {
				$merged[$key] = $l;
			} else {
				$merged[$key]['debit'] += $l['debit'];
				$merged[$key]['credit'] += $l['credit'];
			}
		}
		$out = array();
		foreach ($merged as $l) {
			// a ledger that is both debited and credited in one entry is shown as its net
			$net = round($l['debit'] - $l['credit'], 2);
			$l['debit'] = $net > 0 ? $net : 0;
			$l['credit'] = $net < 0 ? -$net : 0;
			if ($l['debit'] || $l['credit']) {
				$out[] = $l;
			}
		}
		return $out;
	}

	public static function balanceProblem(array $lines) {
		$debit = $credit = 0.0;
		foreach ($lines as $l) {
			$debit += $l['debit'];
			$credit += $l['credit'];
		}
		if (abs($debit - $credit) > 0.004) {
			return sprintf('The entry does not balance: debits %s, credits %s.', number_format($debit, 2, '.', ''), number_format($credit, 2, '.', ''));
		}
		return null;
	}

	private static function insertEntry($date, $narration, $module, $id, $key, $type, array $lines, $reversalOf = null) {
		global $adb, $current_user;
		$total = 0.0;
		foreach ($lines as $l) {
			$total += $l['debit'];
		}
		$adb->pquery('INSERT INTO vtiger_journal_entries (entry_date, narration, source_module, source_id, source_key, entry_type, status, reversal_of, total, created_by, created_time)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
			array($date, $narration, $module, $id, $key, $type, 'Posted', $reversalOf, round($total, 2), $current_user ? $current_user->id : 1));
		$entryId = $adb->getLastInsertID();
		foreach ($lines as $l) {
			$adb->pquery('INSERT INTO vtiger_journal_lines (entry_id, ledger_id, debit, credit, party_account, party_vendor, memo, cost_centre) VALUES (?,?,?,?,?,?,?,?)',
				array($entryId, $l['ledger'], $l['debit'], $l['credit'], $l['account'], $l['vendor'], $l['memo'], $l['cost'] ?: null));
		}
		self::adjustMonthlyTotals($date, $lines, 1, $type == 'closing' ? 1 : 0);
		return $entryId;
	}

	/**
	 * Keeps vtiger_ledger_balances (debit and credit per ledger per month) in step with the lines, so
	 * reports add up a few hundred monthly rows instead of every line ever posted.
	 */
	private static function adjustMonthlyTotals($date, array $lines, $sign, $kind = 0) {
		global $adb;
		$month = substr($date, 0, 7);
		foreach ($lines as $l) {
			if (self::hasKind()) {
				$adb->pquery('INSERT INTO vtiger_ledger_balances (ledger_id, ym, kind, debit, credit) VALUES (?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE debit = debit + VALUES(debit), credit = credit + VALUES(credit)',
					array($l['ledger'], $month, $kind, $sign * $l['debit'], $sign * $l['credit']));
			} else {
				$adb->pquery('INSERT INTO vtiger_ledger_balances (ledger_id, ym, debit, credit) VALUES (?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE debit = debit + VALUES(debit), credit = credit + VALUES(credit)',
					array($l['ledger'], $month, $sign * $l['debit'], $sign * $l['credit']));
			}
			if (!empty($l['cost'])) {
				$adb->pquery('INSERT INTO vtiger_dimension_balances (cost_centre_id, ledger_id, ym, debit, credit) VALUES (?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE debit = debit + VALUES(debit), credit = credit + VALUES(credit)',
					array($l['cost'], $l['ledger'], $month, $sign * $l['debit'], $sign * $l['credit']));
			}
		}
	}

	/** Deletes an entry and takes its lines out of the monthly totals. */
	private static function deleteEntry($entryId, $date) {
		global $adb;
		$type = $adb->pquery('SELECT entry_type FROM vtiger_journal_entries WHERE entry_id = ?', array($entryId));
		$kind = ($adb->num_rows($type) && $adb->query_result($type, 0, 0) == 'closing') ? 1 : 0;
		self::adjustMonthlyTotals($date, self::entryLines($entryId), -1, $kind);
		$adb->pquery('DELETE FROM vtiger_journal_lines WHERE entry_id = ?', array($entryId));
		$adb->pquery('DELETE FROM vtiger_journal_entries WHERE entry_id = ?', array($entryId));
	}

	/** Date of a source record's system entry, or null when it has none. */
	public static function entryDate($module, $id, $key) {
		$entry = self::existingEntry($module, $id, $key);
		return $entry ? $entry['entry_date'] : null;
	}

	/**
	 * Refuses a change to a record whose entry (existing, or about to be made on $newDate) falls in
	 * the locked period. Called before saving, while the user can still be told.
	 */
	public static function guard($module, $id, $key, $newDate, $willPost) {
		$newDate = self::dbDate($newDate);
		$problem = self::lockProblem($id ? self::entryDate($module, $id, $key) : null);
		if ($problem === null && $willPost) {
			$problem = self::lockProblem($newDate);
		}
		if ($problem !== null) {
			throw new Exception($problem);
		}
	}

	private static function existingEntry($module, $id, $key) {
		global $adb;
		$result = $adb->pquery("SELECT entry_id, entry_date, narration FROM vtiger_journal_entries WHERE source_module = ? AND source_id = ? AND source_key = ? AND entry_type = 'auto'", array($module, $id, $key));
		return $adb->num_rows($result) ? $adb->fetch_array($result) : null;
	}

	private static function entryLines($entryId) {
		global $adb;
		$result = $adb->pquery('SELECT ledger_id, debit, credit, party_account, party_vendor, memo, cost_centre FROM vtiger_journal_lines WHERE entry_id = ? ORDER BY line_id', array($entryId));
		$lines = array();
		while ($row = $adb->fetch_array($result)) {
			$line = self::line($row['ledger_id'], (float)$row['debit'], (float)$row['credit'], $row['party_account'], $row['party_vendor'], (string)$row['memo']);
			$line['cost'] = $row['cost_centre'] ?: null;
			$lines[] = $line;
		}
		return $lines;
	}

	private static function signature(array $lines) {
		$parts = array();
		foreach ($lines as $l) {
			$parts[] = implode(':', array($l['ledger'], number_format($l['debit'], 2, '.', ''), number_format($l['credit'], 2, '.', ''), (int)$l['account'], (int)$l['vendor'], $l['memo'], (int)$l['cost']));
		}
		sort($parts);
		return implode('|', $parts);
	}

	/**
	 * Makes the system entry of a source record equal to $lines: created, replaced or removed
	 * (when $lines is empty). Unchanged entries are left alone. Throws when the books are locked
	 * for what would change.
	 */
	public static function syncEntry($module, $id, $key, $date, $narration, array $lines) {
		global $adb;
		$lines = self::normalize($lines);
		$problem = $lines ? self::balanceProblem($lines) : null;
		if ($problem !== null) {
			throw new Exception("Posting $module $id: $problem");
		}
		$existing = self::existingEntry($module, $id, $key);
		if ($existing && $lines && $existing['entry_date'] == $date && self::signature(self::entryLines($existing['entry_id'])) === self::signature($lines)) {
			if ($existing['narration'] != $narration) {
				$adb->pquery('UPDATE vtiger_journal_entries SET narration = ? WHERE entry_id = ?', array($narration, $existing['entry_id']));
			}
			return $existing['entry_id'];
		}
		if ($existing) {
			if (self::isLocked($existing['entry_date'])) {
				throw new Exception(self::lockProblem($existing['entry_date']));
			}
			self::deleteEntry($existing['entry_id'], $existing['entry_date']);
		}
		if (!$lines) {
			return null;
		}
		if (self::isLocked($date)) {
			throw new Exception(self::lockProblem($date));
		}
		return self::insertEntry($date, $narration, $module, $id, $key, 'auto', $lines);
	}

	/** Why a cost centre / project cannot take postings dated $date, or null. */
	public static function costCentreProblem($costCentreId, $date) {
		global $adb;
		$date = self::dbDate($date);
		$result = $adb->pquery('SELECT c.costcentre_name, c.status, c.start_date, c.end_date FROM vtiger_costcentres c
			INNER JOIN vtiger_crmentity e ON e.crmid = c.costcentresid AND e.deleted = 0 WHERE c.costcentresid = ?', array($costCentreId));
		if (!$adb->num_rows($result)) {
			return 'The cost centre does not exist.';
		}
		$c = $adb->fetch_array($result);
		$name = decode_html($c['costcentre_name']);
		if ($c['status'] != 'Active') {
			return "The cost centre '$name' is closed and cannot take new postings.";
		}
		if ($date && !empty($c['start_date']) && $c['start_date'] != '0000-00-00' && $date < $c['start_date']) {
			return "'$name' starts on {$c['start_date']}; it cannot take postings dated {$date}.";
		}
		if ($date && !empty($c['end_date']) && $c['end_date'] != '0000-00-00' && $date > $c['end_date']) {
			return "'$name' ended on {$c['end_date']}; it cannot take postings dated {$date}.";
		}
		return null;
	}

	/** A cost centre and everything below it (parent chain), as ids. */
	public static function costCentreTree($costCentreId) {
		global $adb;
		$ids = array((int)$costCentreId);
		$result = $adb->pquery('SELECT costcentresid, parent_costcentre FROM vtiger_costcentres WHERE parent_costcentre IS NOT NULL AND parent_costcentre != 0');
		$parents = array();
		while ($row = $adb->fetch_array($result)) {
			$parents[$row['costcentresid']] = $row['parent_costcentre'];
		}
		do {
			$added = false;
			foreach ($parents as $child => $parent) {
				if (in_array($parent, $ids) && !in_array($child, $ids)) {
					$ids[] = (int)$child;
					$added = true;
				}
			}
		} while ($added);
		return $ids;
	}

	/**
	 * Makes the system entry of a source record equal to $raw lines (array(ledger, debit, credit)), all tagged with one
	 * cost centre / project. For postings that are not tied to a document (assets and their depreciation).
	 */
	public static function syncPlainEntry($module, $id, $key, $date, $narration, array $raw, $costCentre = null) {
		self::$dimension = $costCentre ?: null;
		$lines = array();
		foreach ($raw as $r) {
			$lines[] = self::line($r['ledger'], $r['debit'], $r['credit'], null, null, $narration);
		}
		self::$dimension = null;
		return self::syncEntry($module, $id, $key, $date, $narration, $lines);
	}

	/** Like syncPlainEntry for one entry made of several groups, each group producing lines (each with its own cost centre). */
	public static function syncGroupedEntry($module, $id, $key, $date, $narration, array $groups, $linesFor) {
		$lines = array();
		foreach ($groups as $group) {
			foreach ($linesFor($group) as $r) {
				self::$dimension = !empty($r['cost']) ? $r['cost'] : null;
				$lines[] = self::line($r['ledger'], $r['debit'], $r['credit'], null, null, $narration);
			}
		}
		self::$dimension = null;
		return self::syncEntry($module, $id, $key, $date, $narration, $lines);
	}

	/** A manual entry typed in by a person. $lines: array of array(ledger, debit, credit, memo). */
	public static function postManual($date, $narration, array $rawLines) {
		$lines = array();
		foreach ($rawLines as $r) {
			if (empty($r['ledger'])) {
				continue;
			}
			$debit = (float)$r['debit'];
			$credit = (float)$r['credit'];
			if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
				throw new Exception('Each line is either a debit or a credit, and never negative.');
			}
			if (!self::ledgerGroup($r['ledger'])) {
				throw new Exception('A ledger on the entry does not exist.');
			}
			$cost = !empty($r['cost']) ? (int)$r['cost'] : null;
			if ($cost) {
				$problem = self::costCentreProblem($cost, $date);
				if ($problem !== null) {
					throw new Exception($problem);
				}
			}
			// optional spending control (Accounting Settings): expenses must fit the annual budget
			if ($debit - $credit > 0.004) {
				include_once 'include/utils/BudgetUtils.php';
				$over = Vtiger_Budget_Utils::overBudgetProblem($r['ledger'], $cost, $date, $debit - $credit);
				if ($over !== null) {
					throw new Exception($over);
				}
			}
			self::$dimension = $cost;
			$lines[] = self::line($r['ledger'], $debit, $credit, null, null, trim((string)($r['memo'] ?? '')));
			self::$dimension = null;
		}
		$lines = self::normalize($lines);
		if (count($lines) < 2) {
			throw new Exception('An entry needs at least two lines.');
		}
		$problem = self::balanceProblem($lines);
		if ($problem !== null) {
			throw new Exception($problem);
		}
		if (!$date || !strtotime($date)) {
			throw new Exception('Enter the date of the entry.');
		}
		$problem = self::lockProblem($date);
		if ($problem !== null) {
			throw new Exception($problem);
		}
		return self::insertEntry($date, trim($narration), 'Manual', 0, 'manual', 'manual', $lines);
	}

	/**
	 * The year-end closing entry: moves the year's income and expense balances to retained earnings.
	 * Made by FinancialYears::close(); it is typed 'closing' so period profit and loss reports leave it out,
	 * and it survives a rebuild of the system postings.
	 */
	public static function postClosing($date, $narration, array $rawLines, $yearKey) {
		$lines = array();
		foreach ($rawLines as $r) {
			$lines[] = self::line($r['ledger'], $r['debit'], $r['credit'], null, null, $narration);
		}
		self::$dimension = null;
		$lines = self::normalize($lines);
		$problem = $lines ? self::balanceProblem($lines) : null;
		if ($problem !== null) {
			throw new Exception($problem);
		}
		if (!$lines) {
			return null;
		}
		return self::insertEntry($date, $narration, 'FinancialYear', $yearKey, 'closing', 'closing', $lines);
	}

	/** Removes the closing entry of a year (reopening it). */
	public static function removeClosing($yearKey) {
		global $adb;
		$result = $adb->pquery("SELECT entry_id, entry_date FROM vtiger_journal_entries WHERE source_module = 'FinancialYear' AND source_id = ? AND entry_type = 'closing'", array($yearKey));
		while ($row = $adb->fetch_array($result)) {
			self::deleteEntry($row['entry_id'], $row['entry_date']);
		}
	}

	/** Reverses an entry made by a person with an opposite entry dated $date. */
	public static function reverse($entryId, $date) {
		global $adb;
		$result = $adb->pquery('SELECT * FROM vtiger_journal_entries WHERE entry_id = ?', array($entryId));
		if (!$adb->num_rows($result)) {
			throw new Exception('The entry does not exist.');
		}
		$entry = $adb->fetch_array($result);
		if ($entry['entry_type'] != 'manual') {
			throw new Exception('Only entries made by hand can be reversed; system entries follow their document.');
		}
		if ($entry['status'] != 'Posted') {
			throw new Exception('The entry is already reversed.');
		}
		foreach (array($entry['entry_date'], $date) as $d) {
			$problem = self::lockProblem($d);
			if ($problem !== null) {
				throw new Exception($problem);
			}
		}
		$lines = array();
		foreach (self::entryLines($entryId) as $l) {
			$lines[] = self::line($l['ledger'], $l['credit'], $l['debit'], $l['account'], $l['vendor'], $l['memo']);
		}
		$reversalId = self::insertEntry($date, 'Reversal of ' . self::entryNo($entryId) . '. ' . $entry['narration'], 'Manual', 0, 'reversal', 'reversal', $lines, $entryId);
		$adb->pquery("UPDATE vtiger_journal_entries SET status = 'Reversed' WHERE entry_id = ?", array($entryId));
		return $reversalId;
	}

	public static function entryNo($entryId) {
		return 'JV' . $entryId;
	}

	// ---- documents ------------------------------------------------------------------------------

	/** Posting date (Y-m-d) a stored document would be booked on. */
	public static function documentDate($module, $id) {
		global $adb;
		if ($module == 'Invoice') {
			$result = $adb->pquery('SELECT invoicedate AS d FROM vtiger_invoice WHERE invoiceid = ?', array($id));
		} elseif ($module == 'SalesOrder') {
			$result = $adb->pquery('SELECT duedate AS d FROM vtiger_salesorder WHERE salesorderid = ?', array($id));
		} else {
			$result = $adb->pquery('SELECT DATE(createdtime) AS d FROM vtiger_crmentity WHERE crmid = ?', array($id));
		}
		$date = $adb->num_rows($result) ? $adb->query_result($result, 0, 'd') : null;
		if (!$date || $date == '0000-00-00') {
			$result = $adb->pquery('SELECT DATE(createdtime) AS d FROM vtiger_crmentity WHERE crmid = ?', array($id));
			$date = $adb->num_rows($result) ? $adb->query_result($result, 0, 'd') : date('Y-m-d');
		}
		return $date;
	}

	/** Output or input ledger name for a tax label (CGST, SGST, IGST...). */
	private static function taxLedgerName($label, $input) {
		$label = strtoupper($label);
		foreach (array('CGST', 'SGST', 'IGST') as $kind) {
			if (strpos($label, $kind) !== false) {
				return ($input ? 'Input ' : 'Output ') . $kind;
			}
		}
		return $input ? 'Input Tax (Other)' : 'Output Tax (Other)';
	}

	/**
	 * Split of a document's tax by tax ledger, from its line items: array(ledger name => amount).
	 * Falls back to the document's own tax total (grand total less pre-tax total and adjustment) in
	 * one "other" ledger when the lines carry no individual taxes.
	 */
	private static function taxByLedger($module, $id, $docTax, $input) {
		global $adb;
		$labels = array();
		$result = $adb->pquery('SELECT taxname, taxlabel FROM vtiger_inventorytaxinfo');
		while ($row = $adb->fetch_array($result)) {
			$labels[$row['taxname']] = decode_html($row['taxlabel']);
		}
		$weights = array();
		$lines = $adb->pquery('SELECT * FROM vtiger_inventoryproductrel WHERE id = ?', array($id));
		while ($line = $adb->fetch_array($lines)) {
			$gross = (float)$line['quantity'] * (float)$line['listprice'];
			$discount = !empty($line['discount_amount']) ? (float)$line['discount_amount'] : $gross * (float)$line['discount_percent'] / 100;
			$taxable = max(0, $gross - $discount);
			foreach ($labels as $taxName => $label) {
				if (isset($line[$taxName]) && (float)$line[$taxName] > 0) {
					$name = self::taxLedgerName($label, $input);
					$weights[$name] = ($weights[$name] ?? 0) + $taxable * (float)$line[$taxName] / 100;
				}
			}
		}
		$sum = array_sum($weights);
		if ($docTax <= 0.004) {
			return array();
		}
		if ($sum <= 0.004) {
			return array(self::taxLedgerName('', $input) => $docTax);
		}
		$split = array();
		foreach ($weights as $name => $weight) {
			$split[$name] = round($docTax * $weight / $sum, 2);
		}
		// rounding leftovers go to the biggest tax so the parts add up to the document's tax
		$diff = round($docTax - array_sum($split), 2);
		if ($diff != 0) {
			arsort($split);
			$first = key($split);
			$split[$first] = round($split[$first] + $diff, 2);
		}
		return $split;
	}

	/** Header figures of a document: array(total, pre_tax, adjustment, tax, net, party account, party vendor, number, status, note type). */
	private static function documentFigures($module, $id) {
		global $adb;
		$map = array(
			'Invoice' => array('vtiger_invoice', 'invoiceid', 'invoice_no', 'invoicestatus', 'accountid', 'NULL', 'NULL'),
			'PurchaseOrder' => array('vtiger_purchaseorder', 'purchaseorderid', 'purchaseorder_no', 'postatus', 'NULL', 'vendorid', 'NULL'),
			'SalesOrder' => array('vtiger_salesorder', 'salesorderid', 'salesorder_no', 'sostatus', 'accountid', 'vendorid', 'note_type'),
		);
		if (!isset($map[$module])) {
			return null;
		}
		list($table, $idColumn, $noColumn, $statusColumn, $accountColumn, $vendorColumn, $noteColumn) = $map[$module];
		$result = $adb->pquery("SELECT d.total, d.pre_tax_total, d.adjustment, d.$noColumn AS no, d.$statusColumn AS status, " . self::dimensionColumn($table, 'd') . " AS cost_centre,
				" . ($accountColumn == 'NULL' ? 'NULL' : "d.$accountColumn") . " AS account,
				" . ($vendorColumn == 'NULL' ? 'NULL' : "d.$vendorColumn") . " AS vendor,
				" . ($noteColumn == 'NULL' ? 'NULL' : "d.$noteColumn") . " AS note_type, c.deleted
			FROM $table d INNER JOIN vtiger_crmentity c ON c.crmid = d.$idColumn WHERE d.$idColumn = ?", array($id));
		if (!$adb->num_rows($result)) {
			return null;
		}
		$row = $adb->fetch_array($result);
		return array(
			'total' => (float)$row['total'], 'pre' => (float)$row['pre_tax_total'], 'adjustment' => (float)$row['adjustment'],
			'no' => decode_html($row['no']), 'status' => decode_html($row['status']), 'account' => $row['account'], 'vendor' => $row['vendor'],
			'note_type' => $row['note_type'], 'deleted' => (int)$row['deleted'], 'cost_centre' => $row['cost_centre'],
		);
	}

	/**
	 * The cost side of a sale or sales return, from its stock moves: an invoice debits Cost of Goods Sold and
	 * credits Inventory with what left stock; a credit note for returned goods does the reverse.
	 */
	public static function syncCogs($module, $id) {
		global $adb;
		if (!in_array($module, array('Invoice', 'SalesOrder'))) {
			return;
		}
		$f = self::documentFigures($module, $id);
		if (!$f || ($module == 'SalesOrder' && $f['note_type'] == 'Debit Note')) {
			return;
		}
		self::$dimension = $f['cost_centre'] ?: null;
		$value = 0.0;
		$result = $adb->pquery('SELECT SUM(value) AS v FROM vtiger_stock_moves WHERE source_module = ? AND source_id = ?', array($module, $id));
		$value = round((float)$adb->query_result($result, 0, 'v'), 2); // negative for a sale, positive for a return
		$lines = array();
		if (!$f['deleted'] && abs($value) >= 0.005) {
			$cogs = self::ledgerId('Cost of Goods Sold', 'Expenses');
			$inventory = self::ledgerId('Inventory', 'Assets');
			$narration = 'Cost of ' . trim(($module == 'Invoice' ? 'Invoice ' : 'Credit Note ') . $f['no']);
			if ($value < 0) {
				$lines[] = self::line($cogs, -$value, 0, null, null, $narration);
				$lines[] = self::line($inventory, 0, -$value, null, null, $narration);
			} else {
				$lines[] = self::line($inventory, $value, 0, null, null, $narration);
				$lines[] = self::line($cogs, 0, $value, null, null, $narration);
			}
		}
		self::syncEntry($module, $id, 'cogs', self::documentDate($module, $id), 'Cost of goods ' . ($module == 'Invoice' ? 'sold' : 'returned') . ' ' . $f['no'], $lines);
	}

	/** Journal entry of a stock adjustment / opening stock, from its moves and the ledger chosen for the other side. */
	public static function syncStockAdjustment($adjustmentId) {
		global $adb;
		self::$dimension = null;
		$result = $adb->pquery('SELECT * FROM vtiger_stock_adjustments WHERE adjustment_id = ?', array($adjustmentId));
		if (!$adb->num_rows($result)) {
			return;
		}
		$a = $adb->fetch_array($result);
		$moves = $adb->pquery("SELECT SUM(CASE WHEN qty > 0 THEN value ELSE 0 END) AS added, SUM(CASE WHEN qty < 0 THEN -value ELSE 0 END) AS removed
			FROM vtiger_stock_moves WHERE source_module = 'Adjustment' AND source_id = ?", array($adjustmentId));
		$added = round((float)$adb->query_result($moves, 0, 'added'), 2);
		$removed = round((float)$adb->query_result($moves, 0, 'removed'), 2);
		$inventory = self::ledgerId('Inventory', 'Assets');
		$counter = $a['counter_ledger'];
		$narration = trim($a['adjustment_type'] . ' ' . $a['narration']);
		$lines = array();
		if ($added) {
			$lines[] = self::line($inventory, $added, 0, null, null, $narration);
			$lines[] = self::line($counter, 0, $added, null, null, $narration);
		}
		if ($removed) {
			$lines[] = self::line($counter, $removed, 0, null, null, $narration);
			$lines[] = self::line($inventory, 0, $removed, null, null, $narration);
		}
		self::syncEntry('StockAdjustment', $adjustmentId, 'stock', $a['adjustment_date'], $narration ?: 'Stock adjustment', $lines);
	}

	/**
	 * Posts (or unposts) an Invoice, Purchase Order or credit/debit note according to its status:
	 *   Invoice            Dr Accounts Receivable | Cr Sales, output taxes, round off
	 *   Purchase Order     Dr Purchases, input taxes | Cr Accounts Payable
	 *   Credit Note        Dr Sales Returns, output taxes | Cr Accounts Receivable
	 *   Debit Note         Dr Accounts Payable | Cr Purchase Returns, input taxes
	 */
	public static function syncDocument($module, $id) {
		$f = self::documentFigures($module, $id);
		if (!$f) {
			return;
		}
		self::$dimension = $f['cost_centre'] ?: null;
		$date = self::documentDate($module, $id);
		$posted = !$f['deleted'] && in_array($f['status'], self::$postedStatuses[$module], true) && $f['total'] > 0.004;
		$isNote = $module == 'SalesOrder';
		$debitNote = $isNote && $f['note_type'] == 'Debit Note';
		$inward = $module == 'PurchaseOrder' || $debitNote; // purchase side: input taxes, payable
		$label = $isNote ? $f['note_type'] : ($module == 'Invoice' ? 'Invoice' : 'Purchase Order');
		$narration = trim("$label {$f['no']}");

		$lines = array();
		if ($posted) {
			$docTax = max(0, $f['total'] - $f['pre'] - $f['adjustment']);
			// without a pre-tax total the document's tax cannot be told apart, so everything is booked as goods
			$taxes = $f['pre'] > 0.004 ? self::taxByLedger($module, $id, $docTax, $inward) : array();
			$taxSum = array_sum($taxes);
			$adjustment = $f['adjustment'];
			$net = round($f['total'] - $taxSum - $adjustment, 2); // the party's total less tax and round off = value of goods/services

			$account = $f['account'];
			$vendor = $f['vendor'];
			if ($inward) {
				$payable = self::ledgerId('Accounts Payable', 'Liabilities');
				$goods = self::ledgerId($debitNote ? 'Purchase Returns' : 'Purchases', $debitNote ? 'Expenses' : 'Expenses');
				if ($debitNote) {
					$lines[] = self::line($payable, $f['total'], 0, null, $vendor, $narration);
					$fromStock = min(Vtiger_Stock_Utils::movesValue('SalesOrder', $id), 99999999);
					if ($fromStock > 0.004) {
						$lines[] = self::line(self::ledgerId('Inventory', 'Assets'), 0, $fromStock, null, null, $narration);
					}
					// the rest (services, price difference between what was paid and the cost) goes to purchase returns
					$rest = round($net - $fromStock, 2);
					$lines[] = self::line($goods, $rest < 0 ? -$rest : 0, $rest > 0 ? $rest : 0, null, null, $narration);
					foreach ($taxes as $name => $amount) {
						$lines[] = self::line(self::ledgerId($name, 'Assets'), 0, $amount, null, null, $narration);
					}
					if ($adjustment) {
						$lines[] = self::line(self::ledgerId('Round Off', 'Expenses'), $adjustment > 0 ? 0 : -$adjustment, $adjustment > 0 ? $adjustment : 0);
					}
				} else {
					// goods that take part in stock go to Inventory once received (Goods in Transit while only ordered)
					$stock = min($net, Vtiger_Stock_Utils::stockValue('PurchaseOrder', $id));
					if ($stock > 0.004) {
						$lines[] = self::line(self::ledgerId($f['status'] == 'Received Shipment' ? 'Inventory' : 'Goods in Transit', 'Assets'), $stock, 0, null, null, $narration);
					}
					foreach (self::allocateByCategory($id, round($net - $stock, 2), $goods, true) as $ledger => $amount) {
						$lines[] = self::line($ledger, $amount, 0, null, null, $narration);
					}
					foreach ($taxes as $name => $amount) {
						$lines[] = self::line(self::ledgerId($name, 'Assets'), $amount, 0, null, null, $narration);
					}
					if ($adjustment) {
						$lines[] = self::line(self::ledgerId('Round Off', 'Expenses'), $adjustment > 0 ? $adjustment : 0, $adjustment > 0 ? 0 : -$adjustment);
					}
					$lines[] = self::line($payable, 0, $f['total'], null, $vendor, $narration);
				}
			} else {
				$receivable = self::ledgerId('Accounts Receivable', 'Assets');
				$sales = self::ledgerId($isNote ? 'Sales Returns' : 'Sales', $isNote ? 'Income' : 'Income');
				if ($isNote) {
					$lines[] = self::line($sales, $net, 0, null, null, $narration);
					foreach ($taxes as $name => $amount) {
						$lines[] = self::line(self::ledgerId($name, 'Liabilities'), $amount, 0, null, null, $narration);
					}
					if ($adjustment) {
						$lines[] = self::line(self::ledgerId('Round Off', 'Expenses'), $adjustment > 0 ? $adjustment : 0, $adjustment > 0 ? 0 : -$adjustment);
					}
					$lines[] = self::line($receivable, 0, $f['total'], $account, null, $narration);
				} else {
					$lines[] = self::line($receivable, $f['total'], 0, $account, null, $narration);
					foreach (self::allocateByCategory($id, $net, $sales, false) as $ledger => $amount) {
						$lines[] = self::line($ledger, 0, $amount, null, null, $narration);
					}
					foreach ($taxes as $name => $amount) {
						$lines[] = self::line(self::ledgerId($name, 'Liabilities'), 0, $amount, null, null, $narration);
					}
					if ($adjustment) {
						$lines[] = self::line(self::ledgerId('Round Off', 'Expenses'), $adjustment > 0 ? 0 : -$adjustment, $adjustment > 0 ? $adjustment : 0);
					}
				}
			}
		}
		self::syncEntry($module, $id, 'doc', $date, $narration, $lines);
	}

	/**
	 * Splits the value of goods and services of a document over ledgers by product category: each
	 * line goes to the income ledger (sales) or expense ledger (purchases) of its product's category,
	 * lines without one to $defaultLedger. The amounts are scaled to $net so the entry always
	 * balances (document discounts and shipping fall proportionally on all lines).
	 * Returns array(ledger id => amount).
	 */
	private static function allocateByCategory($id, $net, $defaultLedger, $expense) {
		global $adb;
		$column = $expense ? 'expense_ledger' : 'income_ledger';
		$result = $adb->pquery("SELECT l.quantity, l.listprice, l.discount_percent, l.discount_amount, pc.$column AS category_ledger
			FROM vtiger_inventoryproductrel l
			LEFT JOIN vtiger_products p ON p.productid = l.productid
			LEFT JOIN vtiger_productcategories pc ON pc.productcategoriesid = p.productcategory
			WHERE l.id = ?", array($id));
		$weights = array();
		while ($row = $adb->fetch_array($result)) {
			$gross = (float)$row['quantity'] * (float)$row['listprice'];
			$discount = !empty($row['discount_amount']) ? (float)$row['discount_amount'] : $gross * (float)$row['discount_percent'] / 100;
			$value = max(0, $gross - $discount);
			$ledger = !empty($row['category_ledger']) ? $row['category_ledger'] : $defaultLedger;
			$weights[$ledger] = ($weights[$ledger] ?? 0) + $value;
		}
		$total = array_sum($weights);
		if ($total <= 0.004 || count($weights) == 0) {
			return array($defaultLedger => $net);
		}
		$split = array();
		foreach ($weights as $ledger => $weight) {
			$split[$ledger] = round($net * $weight / $total, 2);
		}
		$diff = round($net - array_sum($split), 2);
		if ($diff != 0) {
			arsort($split);
			$first = key($split);
			$split[$first] = round($split[$first] + $diff, 2);
		}
		return $split;
	}

	// ---- payments, bank transactions, opening balances -------------------------------------------------

	/** Cost centre of a quote, invoice, purchase order or note (null when none or the dimension is off). */
	public static function documentCostCentre($module, $id) {
		global $adb;
		$tables = array('Invoice' => array('vtiger_invoice', 'invoiceid'), 'PurchaseOrder' => array('vtiger_purchaseorder', 'purchaseorderid'),
			'Quotes' => array('vtiger_quotes', 'quoteid'), 'SalesOrder' => array('vtiger_salesorder', 'salesorderid'));
		if (!isset($tables[$module]) || self::dimensionColumn($tables[$module][0]) == 'NULL') {
			return null;
		}
		list($table, $idColumn) = $tables[$module];
		$result = $adb->pquery("SELECT cost_centre FROM $table WHERE $idColumn = ?", array($id));
		return $adb->num_rows($result) ? ($adb->query_result($result, 0, 0) ?: null) : null;
	}

	/**
	 * Posts a Completed payment:
	 *   money in against an invoice or debit note   Dr bank | Cr Accounts Receivable (invoice) or Accounts Payable (debit note)
	 *   money out against a purchase order/credit note  Dr Accounts Payable (PO) or Accounts Receivable (credit note) | Cr bank
	 *   money in against a quote                    Dr bank | Cr Customer Advances
	 */
	public static function syncPayment($paymentId) {
		global $adb;
		$result = $adb->pquery('SELECT p.payment_no, p.related_to, p.direction, p.amount, p.payment_date, p.status, p.bank_account, p.account_id, p.vendor_id, ' . self::dimensionColumn('vtiger_payments', 'p') . ' AS cost_centre, c.deleted
			FROM vtiger_payments p INNER JOIN vtiger_crmentity c ON c.crmid = p.paymentsid WHERE p.paymentsid = ?', array($paymentId));
		if (!$adb->num_rows($result)) {
			return;
		}
		$p = $adb->fetch_array($result);
		// a payment belongs to the cost centre of the document it pays
		self::$dimension = $p['cost_centre'] ?: self::documentCostCentre(getSalesEntityType($p['related_to']), $p['related_to']);
		$date = $p['payment_date'];
		$lines = array();
		if (!$p['deleted'] && $p['status'] == 'Completed' && (float)$p['amount'] > 0) {
			$documentModule = getSalesEntityType($p['related_to']);
			$amount = (float)$p['amount'];
			$bank = !empty($p['bank_account']) ? self::bankLedger($p['bank_account']) : self::ledgerId('Cash in Hand', 'Assets');
			$receivable = self::ledgerId('Accounts Receivable', 'Assets');
			$payable = self::ledgerId('Accounts Payable', 'Liabilities');
			$narration = 'Payment ' . $p['payment_no'];
			$in = $p['direction'] != 'Paid';
			if ($documentModule == 'Quotes') {
				$other = self::ledgerId('Customer Advances', 'Liabilities');
				$party = array($p['account_id'], null);
			} elseif ($documentModule == 'PurchaseOrder') {
				$other = $payable;
				$party = array(null, $p['vendor_id']);
			} else {
				$other = $receivable;
				$party = array($p['account_id'], null);
			}
			if ($in) {
				$lines[] = self::line($bank, $amount, 0, null, null, $narration);
				$lines[] = self::line($other, 0, $amount, $party[0], $party[1], $narration);
			} else {
				$lines[] = self::line($other, $amount, 0, $party[0], $party[1], $narration);
				$lines[] = self::line($bank, 0, $amount, null, null, $narration);
			}
		}
		self::syncEntry('Payments', $paymentId, 'payment', $date, 'Payment ' . $p['payment_no'], $lines);
	}

	/**
	 * Posts a bank transaction entered by hand (not one a payment or transfer created):
	 * money in = Dr bank | Cr the chosen ledger, money out = Dr the chosen ledger | Cr bank. A
	 * transaction without a ledger goes to Suspense so it shows up until it is categorised.
	 */
	public static function syncBankTransaction($transactionId) {
		global $adb;
		$result = $adb->pquery('SELECT t.*, c.deleted FROM vtiger_banktransactions t INNER JOIN vtiger_crmentity c ON c.crmid = t.banktransactionsid WHERE t.banktransactionsid = ?', array($transactionId));
		if (!$adb->num_rows($result)) {
			return;
		}
		$t = $adb->fetch_array($result);
		self::$dimension = !empty($t['cost_centre']) ? $t['cost_centre'] : null;
		$lines = array();
		$date = $t['transaction_date'];
		if (!$t['deleted'] && empty($t['payment']) && (float)$t['amount'] > 0) {
			$amount = (float)$t['amount'];
			$bank = self::bankLedger($t['bank_account']);
			$narration = trim(($t['transaction_type'] ?: 'Bank transaction') . ' ' . $t['transaction_no'] . ' ' . decode_html($t['narration']));
			if (!empty($t['transfer_pair'])) {
				// a transfer: the "In" side books the whole movement against the other account's ledger
				if ($t['direction'] == 'In') {
					$from = $adb->pquery('SELECT bank_account FROM vtiger_banktransactions WHERE banktransactionsid = ?', array($t['transfer_pair']));
					$fromLedger = $adb->num_rows($from) ? self::bankLedger($adb->query_result($from, 0, 0)) : self::ledgerId('Suspense Account', 'Liabilities');
					$lines[] = self::line($bank, $amount, 0, null, null, $narration);
					$lines[] = self::line($fromLedger, 0, $amount, null, null, $narration);
				}
			} else {
				$other = !empty($t['ledger']) ? $t['ledger'] : self::ledgerId('Suspense Account', 'Liabilities');
				if ($t['direction'] == 'In') {
					$lines[] = self::line($bank, $amount, 0, $t['party_account'], $t['party_vendor'], $narration);
					$lines[] = self::line($other, 0, $amount, $t['party_account'], $t['party_vendor'], $narration);
				} else {
					$lines[] = self::line($other, $amount, 0, $t['party_account'], $t['party_vendor'], $narration);
					$lines[] = self::line($bank, 0, $amount, $t['party_account'], $t['party_vendor'], $narration);
				}
			}
		}
		self::syncEntry('BankTransactions', $transactionId, 'bank', $date, 'Bank transaction ' . $t['transaction_no'], $lines);
	}

	/** Opening balance of a bank account against Opening Balance Equity. */
	public static function syncBankOpening($accountId) {
		global $adb;
		self::$dimension = null;
		$result = $adb->pquery('SELECT b.opening_balance, b.opening_date, b.account_name, c.deleted FROM vtiger_bankaccounts b
			INNER JOIN vtiger_crmentity c ON c.crmid = b.bankaccountsid WHERE b.bankaccountsid = ?', array($accountId));
		if (!$adb->num_rows($result)) {
			return;
		}
		$b = $adb->fetch_array($result);
		$amount = round((float)$b['opening_balance'], 2);
		$lines = array();
		$date = $b['opening_date'] ?: date('Y-m-d', strtotime('first day of january this year'));
		if (!$b['deleted'] && abs($amount) >= 0.005) {
			$ledger = self::bankLedger($accountId);
			$equity = self::ledgerId('Opening Balance Equity', 'Equity');
			$memo = 'Opening balance ' . decode_html($b['account_name']);
			$lines[] = self::line($ledger, max($amount, 0), max(-$amount, 0), null, null, $memo);
			$lines[] = self::line($equity, max(-$amount, 0), max($amount, 0), null, null, $memo);
		}
		self::syncEntry('BankAccounts', $accountId, 'opening', $date, 'Opening balance', $lines);
	}

	/** Opening balance of a ledger (entered on the ledger) against Opening Balance Equity. */
	public static function syncLedgerOpening($ledgerId) {
		global $adb;
		self::$dimension = null;
		$result = $adb->pquery('SELECT l.opening_balance, l.ledger_group, l.ledger_name, c.deleted FROM vtiger_ledgers l
			INNER JOIN vtiger_crmentity c ON c.crmid = l.ledgersid WHERE l.ledgersid = ?', array($ledgerId));
		if (!$adb->num_rows($result)) {
			return;
		}
		$l = $adb->fetch_array($result);
		$amount = round((float)$l['opening_balance'], 2);
		$lines = array();
		if (!$l['deleted'] && abs($amount) >= 0.005 && decode_html($l['ledger_name']) != 'Opening Balance Equity') {
			$debitSide = self::isDebitGroup(decode_html($l['ledger_group']));
			$debit = $debitSide ? max($amount, 0) : max(-$amount, 0);
			$credit = $debitSide ? max(-$amount, 0) : max($amount, 0);
			$equity = self::ledgerId('Opening Balance Equity', 'Equity');
			$lines[] = self::line($ledgerId, $debit, $credit, null, null, 'Opening balance');
			$lines[] = self::line($equity, $credit, $debit, null, null, 'Opening balance');
		}
		self::syncEntry('Ledgers', $ledgerId, 'opening', self::openingDate(), 'Opening balance ' . decode_html($l['ledger_name']), $lines);
	}

	/** Date ledger opening balances are booked on: the day before the books start (setting), else 1 April of this financial year. */
	public static function openingDate() {
		$start = self::getSetting('books_start');
		if ($start) {
			return $start;
		}
		$year = (int)date('n') >= 4 ? date('Y') : date('Y') - 1;
		return $year . '-04-01';
	}

	// ---- reports ---------------------------------------------------------------------------------

	/**
	 * Whole months of the range [$from, $to] (as year-month strings, null when there are none) and the
	 * partial-month edges (date ranges to read from the lines): array(fromYm, toYm, array(array(a, b)...)).
	 */
	private static function splitRange($from, $to) {
		$fromDate = $from ?: '1900-01-01';
		$firstFullDay = ($fromDate == date('Y-m-01', strtotime($fromDate))) ? $fromDate : date('Y-m-01', strtotime($fromDate . ' +1 month'));
		$lastFullDay = ($to == date('Y-m-t', strtotime($to))) ? $to : date('Y-m-t', strtotime(date('Y-m-01', strtotime($to)) . ' -1 day'));
		if ($firstFullDay > $lastFullDay) {
			return array(null, null, array(array($fromDate, $to)));   // inside a single month
		}
		$edges = array();
		if ($fromDate < $firstFullDay) {
			$edges[] = array($fromDate, date('Y-m-d', strtotime($firstFullDay . ' -1 day')));
		}
		if ($lastFullDay < $to) {
			$edges[] = array(date('Y-m-d', strtotime($lastFullDay . ' +1 day')), $to);
		}
		return array(substr($firstFullDay, 0, 7), substr($lastFullDay, 0, 7), $edges);
	}

	/**
	 * Debit and credit per ledger for dates from $from (null = from the beginning) to $to, as
	 * array(ledger id => array(debit, credit)). Whole months come from the monthly totals; the (at
	 * most two) partial months at the edges are summed from the lines. $costCentres limits the sums
	 * to those cost centres / projects (ids), $ledgerId to one ledger.
	 */
	public static function sumsByLedger($from, $to, $ledgerId = null, $costCentres = null, $includeClosing = true) {
		global $adb;
		$sums = array();
		$add = function ($result) use (&$sums, $adb) {
			while ($row = $adb->fetch_array($result)) {
				$sums[$row['ledger_id']][0] = ($sums[$row['ledger_id']][0] ?? 0) + (float)$row['debit'];
				$sums[$row['ledger_id']][1] = ($sums[$row['ledger_id']][1] ?? 0) + (float)$row['credit'];
			}
		};
		$ledgerFilter = $ledgerId ? ' AND jl.ledger_id = ' . (int)$ledgerId : '';
		$costFilter = $costCentres ? ' AND jl.cost_centre IN (' . implode(',', array_map('intval', $costCentres)) . ')' : '';
		list($fromYm, $toYm, $edges) = self::splitRange($from, $to);
		if ($fromYm !== null) {
			if ($costCentres) {
				$add($adb->pquery('SELECT jl.ledger_id, SUM(jl.debit) AS debit, SUM(jl.credit) AS credit FROM vtiger_dimension_balances jl WHERE jl.ym >= ? AND jl.ym <= ?'
					. str_replace('jl.cost_centre', 'jl.cost_centre_id', $costFilter) . $ledgerFilter . ' GROUP BY jl.ledger_id', array($fromYm, $toYm)));
			} else {
				$add($adb->pquery('SELECT jl.ledger_id, SUM(jl.debit) AS debit, SUM(jl.credit) AS credit FROM vtiger_ledger_balances jl WHERE jl.ym >= ? AND jl.ym <= ?'
					. (!$includeClosing && self::hasKind() ? ' AND jl.kind = 0' : '') . $ledgerFilter . ' GROUP BY jl.ledger_id', array($fromYm, $toYm)));
			}
		}
		foreach ($edges as $edge) {
			if ($edge[0] > $edge[1]) {
				continue;
			}
			$add($adb->pquery('SELECT jl.ledger_id, SUM(jl.debit) AS debit, SUM(jl.credit) AS credit FROM vtiger_journal_entries je
				STRAIGHT_JOIN vtiger_journal_lines jl ON jl.entry_id = je.entry_id WHERE je.entry_date >= ? AND je.entry_date <= ?' . ($includeClosing ? '' : " AND je.entry_type != 'closing'") . $ledgerFilter . $costFilter . '
				GROUP BY jl.ledger_id', array($edge[0], $edge[1])));
		}
		return $sums;
	}

	/**
	 * Income, expenses and net per cost centre / project for a period: array(cost centre id =>
	 * array(income, expenses)). Same month-summary + edge-lines approach as sumsByLedger().
	 */
	public static function costCentreSummary($from, $to) {
		global $adb;
		$summary = array();
		$add = function ($result) use (&$summary, $adb) {
			while ($row = $adb->fetch_array($result)) {
				$group = decode_html($row['grp']);
				if ($group != 'Income' && $group != 'Expenses') {
					continue;
				}
				$net = (float)$row['credit'] - (float)$row['debit'];
				$id = $row['cc'];
				$summary[$id]['income'] = ($summary[$id]['income'] ?? 0) + ($group == 'Income' ? $net : 0);
				$summary[$id]['expenses'] = ($summary[$id]['expenses'] ?? 0) + ($group == 'Expenses' ? -$net : 0);
			}
		};
		list($fromYm, $toYm, $edges) = self::splitRange($from, $to);
		if ($fromYm !== null) {
			$add($adb->pquery('SELECT d.cost_centre_id AS cc, l.ledger_group AS grp, SUM(d.debit) AS debit, SUM(d.credit) AS credit FROM vtiger_dimension_balances d
				INNER JOIN vtiger_ledgers l ON l.ledgersid = d.ledger_id WHERE d.ym >= ? AND d.ym <= ? GROUP BY d.cost_centre_id, l.ledger_group', array($fromYm, $toYm)));
		}
		foreach ($edges as $edge) {
			if ($edge[0] > $edge[1]) {
				continue;
			}
			$add($adb->pquery('SELECT jl.cost_centre AS cc, l.ledger_group AS grp, SUM(jl.debit) AS debit, SUM(jl.credit) AS credit FROM vtiger_journal_entries je
				STRAIGHT_JOIN vtiger_journal_lines jl ON jl.entry_id = je.entry_id INNER JOIN vtiger_ledgers l ON l.ledgersid = jl.ledger_id
				WHERE je.entry_date >= ? AND je.entry_date <= ? AND jl.cost_centre IS NOT NULL GROUP BY jl.cost_centre, l.ledger_group', array($edge[0], $edge[1])));
		}
		return $summary;
	}

	/** Per-ledger totals up to a date (and from a date when given): rows of ledger id, name, group, debit, credit. */
	public static function ledgerTotals($asAt, $from = null, $costCentres = null, $includeClosing = true) {
		global $adb;
		$sums = self::sumsByLedger($from, $asAt, null, $costCentres, $includeClosing);
		$result = $adb->pquery("SELECT l.ledgersid AS id, l.ledger_name AS name, l.ledger_group AS grp FROM vtiger_ledgers l
			INNER JOIN vtiger_crmentity c ON c.crmid = l.ledgersid AND c.deleted = 0 ORDER BY l.ledger_group, l.ledger_name");
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$row['name'] = decode_html($row['name']);
			$row['grp'] = decode_html($row['grp']);
			$row['debit'] = round($sums[$row['id']][0] ?? 0, 2);
			$row['credit'] = round($sums[$row['id']][1] ?? 0, 2);
			$rows[] = $row;
		}
		return $rows;
	}

	/** Signed balance of a ledger row in its normal direction (positive = the usual side). */
	public static function normalBalance($row) {
		return self::isDebitGroup($row['grp']) ? $row['debit'] - $row['credit'] : $row['credit'] - $row['debit'];
	}

	/** General ledger of one ledger: opening before $from, the lines in the period with running balance, closing. */
	public static function generalLedger($ledgerId, $from, $to) {
		global $adb;
		$group = self::ledgerGroup($ledgerId);
		if (!$group) {
			return null;
		}
		$sign = self::isDebitGroup($group) ? 1 : -1;
		$beforeSums = self::sumsByLedger(null, date('Y-m-d', strtotime($from . ' -1 day')), $ledgerId);
		$before = ($beforeSums[$ledgerId][0] ?? 0) - ($beforeSums[$ledgerId][1] ?? 0);
		$opening = $sign * $before;
		$result = $adb->pquery('SELECT je.entry_id, je.entry_date, je.narration, je.source_module, je.source_id, je.entry_type, je.status, jl.debit, jl.credit, jl.memo
			FROM vtiger_journal_lines jl INNER JOIN vtiger_journal_entries je ON je.entry_id = jl.entry_id
			WHERE jl.ledger_id = ? AND je.entry_date >= ? AND je.entry_date <= ? ORDER BY je.entry_date, je.entry_id, jl.line_id', array($ledgerId, $from, $to));
		$rows = array();
		$balance = $opening;
		$debit = $credit = 0.0;
		while ($row = $adb->fetch_array($result)) {
			$balance += $sign * ((float)$row['debit'] - (float)$row['credit']);
			$debit += (float)$row['debit'];
			$credit += (float)$row['credit'];
			$row['running'] = round($balance, 2);
			$rows[] = $row;
		}
		return array('group' => $group, 'opening' => round($opening, 2), 'rows' => $rows, 'debit' => round($debit, 2), 'credit' => round($credit, 2), 'closing' => round($balance, 2));
	}

	// ---- rebuild ---------------------------------------------------------------------------------

	/** Recomputes the monthly totals from the lines (after a repair, or the first time). */
	public static function rebuildMonthlyTotals() {
		global $adb;
		$adb->pquery('DELETE FROM vtiger_dimension_balances');
		$adb->pquery("INSERT INTO vtiger_dimension_balances (cost_centre_id, ledger_id, ym, debit, credit)
			SELECT jl.cost_centre, jl.ledger_id, DATE_FORMAT(je.entry_date, '%Y-%m'), SUM(jl.debit), SUM(jl.credit) FROM vtiger_journal_lines jl
			INNER JOIN vtiger_journal_entries je ON je.entry_id = jl.entry_id WHERE jl.cost_centre IS NOT NULL GROUP BY jl.cost_centre, jl.ledger_id, DATE_FORMAT(je.entry_date, '%Y-%m')");
		$adb->pquery('DELETE FROM vtiger_ledger_balances');
		if (self::hasKind()) {
			$adb->pquery("INSERT INTO vtiger_ledger_balances (ledger_id, ym, kind, debit, credit)
				SELECT jl.ledger_id, DATE_FORMAT(je.entry_date, '%Y-%m'), IF(je.entry_type = 'closing', 1, 0), SUM(jl.debit), SUM(jl.credit) FROM vtiger_journal_lines jl
				INNER JOIN vtiger_journal_entries je ON je.entry_id = jl.entry_id GROUP BY jl.ledger_id, DATE_FORMAT(je.entry_date, '%Y-%m'), IF(je.entry_type = 'closing', 1, 0)");
			return;
		}
		$adb->pquery("INSERT INTO vtiger_ledger_balances (ledger_id, ym, debit, credit)
			SELECT jl.ledger_id, DATE_FORMAT(je.entry_date, '%Y-%m'), SUM(jl.debit), SUM(jl.credit) FROM vtiger_journal_lines jl
			INNER JOIN vtiger_journal_entries je ON je.entry_id = jl.entry_id GROUP BY jl.ledger_id, DATE_FORMAT(je.entry_date, '%Y-%m')");
	}

	/** Rebuilds every system entry from the records (used after the first install and to repair). Returns a count per source. */
	public static function rebuildAll() {
		global $adb;
		// a full repost recreates entries of every period, including locked ones, from the documents that already exist
		self::$bypassLock = true;
		try {
			return self::rebuildEverything();
		} finally {
			self::$bypassLock = false;
		}
	}

	private static function rebuildEverything() {
		global $adb;
		$adb->pquery("DELETE FROM vtiger_journal_lines WHERE entry_id IN (SELECT entry_id FROM vtiger_journal_entries WHERE entry_type = 'auto')");
		$adb->pquery("DELETE FROM vtiger_journal_entries WHERE entry_type = 'auto'");
		self::rebuildMonthlyTotals();
		$counts = array();
		$sources = array(
			'BankAccounts' => array('SELECT bankaccountsid FROM vtiger_bankaccounts', 'syncBankOpening'),
			'Ledgers' => array('SELECT ledgersid FROM vtiger_ledgers WHERE opening_balance IS NOT NULL AND opening_balance != 0', 'syncLedgerOpening'),
			'Invoice' => array('SELECT invoiceid FROM vtiger_invoice', null),
			'PurchaseOrder' => array('SELECT purchaseorderid FROM vtiger_purchaseorder', null),
			'SalesOrder' => array('SELECT salesorderid FROM vtiger_salesorder', null),
			'StockAdjustment' => array('SELECT adjustment_id FROM vtiger_stock_adjustments', 'syncStockAdjustment'),
			'Payments' => array('SELECT paymentsid FROM vtiger_payments', 'syncPayment'),
			'BankTransactions' => array('SELECT banktransactionsid FROM vtiger_banktransactions', 'syncBankTransaction'),
		);
		foreach ($sources as $module => $source) {
			$counts[$module] = 0;
			$result = $adb->pquery($source[0]);
			while ($row = $adb->fetch_array($result)) {
				$id = $row[0];
				if ($source[1]) {
					self::{$source[1]}($id);
				} else {
					Vtiger_Stock_Utils::syncDocument($module, $id); // stock moves first: the entries below read them
					self::syncDocument($module, $id);
				}
				$counts[$module]++;
			}
		}
		self::rebuildMonthlyTotals();
		return $counts;
	}
}
