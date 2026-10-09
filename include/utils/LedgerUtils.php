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
class Vtiger_Ledger_Utils {

	/** Statuses in which a document is posted to the books. */
	private static $postedStatuses = array(
		'Invoice' => array('Approved', 'Sent', 'Credit Invoice', 'Paid'),
		'PurchaseOrder' => array('Approved', 'Delivered', 'Received Shipment'),
		'SalesOrder' => array('Approved', 'Sent'),
	);

	private static $ledgerCache = array();

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

	public static function isLocked($date) {
		$lock = self::lockDate();
		return $lock && $date && $date <= $lock;
	}

	/** Why something dated $date cannot be posted or changed, or null. */
	public static function lockProblem($date) {
		if (self::isLocked($date)) {
			return 'The books are locked up to ' . self::lockDate() . ', so nothing dated on or before that day can be added or changed.';
		}
		return null;
	}

	// ---- ledgers --------------------------------------------------------------------------------

	/** Id of a ledger by name; created in the given group when it does not exist yet. */
	public static function ledgerId($name, $group = 'Assets') {
		global $adb, $current_user;
		if (isset(self::$ledgerCache[$name])) {
			return self::$ledgerCache[$name];
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
		return array('ledger' => $ledger, 'debit' => round($debit, 2), 'credit' => round($credit, 2), 'account' => $account ?: null, 'vendor' => $vendor ?: null, 'memo' => $memo);
	}

	/** Merges lines of the same ledger and party so the stored entry is as short as it can be, drops empty ones. */
	private static function normalize(array $lines) {
		$merged = array();
		foreach ($lines as $l) {
			if (abs($l['debit']) < 0.005 && abs($l['credit']) < 0.005) {
				continue;
			}
			$key = $l['ledger'] . '|' . $l['account'] . '|' . $l['vendor'] . '|' . $l['memo'];
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
			$adb->pquery('INSERT INTO vtiger_journal_lines (entry_id, ledger_id, debit, credit, party_account, party_vendor, memo) VALUES (?,?,?,?,?,?,?)',
				array($entryId, $l['ledger'], $l['debit'], $l['credit'], $l['account'], $l['vendor'], $l['memo']));
		}
		return $entryId;
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
		$result = $adb->pquery('SELECT ledger_id, debit, credit, party_account, party_vendor, memo FROM vtiger_journal_lines WHERE entry_id = ? ORDER BY line_id', array($entryId));
		$lines = array();
		while ($row = $adb->fetch_array($result)) {
			$lines[] = self::line($row['ledger_id'], (float)$row['debit'], (float)$row['credit'], $row['party_account'], $row['party_vendor'], (string)$row['memo']);
		}
		return $lines;
	}

	private static function signature(array $lines) {
		$parts = array();
		foreach ($lines as $l) {
			$parts[] = implode(':', array($l['ledger'], number_format($l['debit'], 2, '.', ''), number_format($l['credit'], 2, '.', ''), (int)$l['account'], (int)$l['vendor'], $l['memo']));
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
			$adb->pquery('DELETE FROM vtiger_journal_lines WHERE entry_id = ?', array($existing['entry_id']));
			$adb->pquery('DELETE FROM vtiger_journal_entries WHERE entry_id = ?', array($existing['entry_id']));
		}
		if (!$lines) {
			return null;
		}
		if (self::isLocked($date)) {
			throw new Exception(self::lockProblem($date));
		}
		return self::insertEntry($date, $narration, $module, $id, $key, 'auto', $lines);
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
			$lines[] = self::line($r['ledger'], $debit, $credit, null, null, trim((string)($r['memo'] ?? '')));
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
		$result = $adb->pquery("SELECT d.total, d.pre_tax_total, d.adjustment, d.$noColumn AS no, d.$statusColumn AS status,
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
			'note_type' => $row['note_type'], 'deleted' => (int)$row['deleted'],
		);
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
					$lines[] = self::line($goods, 0, $net, null, null, $narration);
					foreach ($taxes as $name => $amount) {
						$lines[] = self::line(self::ledgerId($name, 'Assets'), 0, $amount, null, null, $narration);
					}
					if ($adjustment) {
						$lines[] = self::line(self::ledgerId('Round Off', 'Expenses'), $adjustment > 0 ? 0 : -$adjustment, $adjustment > 0 ? $adjustment : 0);
					}
				} else {
					$lines[] = self::line($goods, $net, 0, null, null, $narration);
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
					$lines[] = self::line($sales, 0, $net, null, null, $narration);
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

	// ---- payments, bank transactions, opening balances -------------------------------------------------

	/**
	 * Posts a Completed payment:
	 *   money in against an invoice or debit note   Dr bank | Cr Accounts Receivable (invoice) or Accounts Payable (debit note)
	 *   money out against a purchase order/credit note  Dr Accounts Payable (PO) or Accounts Receivable (credit note) | Cr bank
	 *   money in against a quote                    Dr bank | Cr Customer Advances
	 */
	public static function syncPayment($paymentId) {
		global $adb;
		$result = $adb->pquery('SELECT p.payment_no, p.related_to, p.direction, p.amount, p.payment_date, p.status, p.bank_account, p.account_id, p.vendor_id, c.deleted
			FROM vtiger_payments p INNER JOIN vtiger_crmentity c ON c.crmid = p.paymentsid WHERE p.paymentsid = ?', array($paymentId));
		if (!$adb->num_rows($result)) {
			return;
		}
		$p = $adb->fetch_array($result);
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
			} elseif ($documentModule == 'PurchaseOrder' || ($documentModule == 'SalesOrder' && $in)) {
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

	/** Per-ledger totals up to a date (and from a date when given): rows of ledger id, name, group, debit, credit. */
	public static function ledgerTotals($asAt, $from = null) {
		global $adb;
		$sql = "SELECT l.ledgersid AS id, l.ledger_name AS name, l.ledger_group AS grp, COALESCE(SUM(jl.debit), 0) AS debit, COALESCE(SUM(jl.credit), 0) AS credit
			FROM vtiger_ledgers l INNER JOIN vtiger_crmentity c ON c.crmid = l.ledgersid AND c.deleted = 0
			LEFT JOIN (SELECT jl2.* FROM vtiger_journal_lines jl2 INNER JOIN vtiger_journal_entries je ON je.entry_id = jl2.entry_id AND je.entry_date <= ?" . ($from ? ' AND je.entry_date >= ?' : '') . ") jl ON jl.ledger_id = l.ledgersid
			GROUP BY l.ledgersid, l.ledger_name, l.ledger_group ORDER BY l.ledger_group, l.ledger_name";
		$params = $from ? array($asAt, $from) : array($asAt);
		$result = $adb->pquery($sql, $params);
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$row['name'] = decode_html($row['name']);
			$row['grp'] = decode_html($row['grp']);
			$row['debit'] = (float)$row['debit'];
			$row['credit'] = (float)$row['credit'];
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
		$before = $adb->pquery('SELECT COALESCE(SUM(jl.debit - jl.credit), 0) AS s FROM vtiger_journal_lines jl INNER JOIN vtiger_journal_entries je ON je.entry_id = jl.entry_id
			WHERE jl.ledger_id = ? AND je.entry_date < ?', array($ledgerId, $from));
		$opening = $sign * (float)$adb->query_result($before, 0, 's');
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

	/** Rebuilds every system entry from the records (used after the first install and to repair). Returns a count per source. */
	public static function rebuildAll() {
		global $adb;
		$adb->pquery("DELETE FROM vtiger_journal_lines WHERE entry_id IN (SELECT entry_id FROM vtiger_journal_entries WHERE entry_type = 'auto')");
		$adb->pquery("DELETE FROM vtiger_journal_entries WHERE entry_type = 'auto'");
		$counts = array();
		$sources = array(
			'BankAccounts' => array('SELECT bankaccountsid FROM vtiger_bankaccounts', 'syncBankOpening'),
			'Ledgers' => array('SELECT ledgersid FROM vtiger_ledgers WHERE opening_balance IS NOT NULL AND opening_balance != 0', 'syncLedgerOpening'),
			'Invoice' => array('SELECT invoiceid FROM vtiger_invoice', null),
			'PurchaseOrder' => array('SELECT purchaseorderid FROM vtiger_purchaseorder', null),
			'SalesOrder' => array('SELECT salesorderid FROM vtiger_salesorder', null),
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
					self::syncDocument($module, $id);
				}
				$counts[$module]++;
			}
		}
		return $counts;
	}
}
