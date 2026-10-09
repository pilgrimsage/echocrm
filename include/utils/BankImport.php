<?php
/**
 * Bank statement import: reads the bank's CSV, matches its lines with the transactions already in the
 * books, creates the missing ones (with remembered rules for the ledger) and reconciles both.
 *
 * Flow: upload -> map columns -> stage lines in a batch -> automatic matching -> review (confirm
 * matches, create missing transactions, ignore) -> reconciled. Staged lines are kept, so a file can
 * be reviewed in several sittings, and the same line is never staged twice for an account.
 *
 * A statement line is money out when it is a debit/withdrawal and money in when it is a credit/deposit.
 */
class Vtiger_Bank_Import {

	const STATUS_UNMATCHED = 'Unmatched';
	const STATUS_SUGGESTED = 'Suggested';
	const STATUS_MATCHED = 'Matched';
	const STATUS_CREATED = 'Created';
	const STATUS_IGNORED = 'Ignored';

	const DATE_WINDOW_DAYS = 7;
	const MAX_FILE_BYTES = 5242880;

	// ---- reading the file ---------------------------------------------------------------------

	/** Rows of a CSV file as arrays (a leading BOM is removed, delimiter , ; or tab is detected). */
	public static function readCsv($path, $limit = 0) {
		$handle = fopen($path, 'r');
		if (!$handle) {
			throw new Exception('The file could not be read.');
		}
		$first = fgets($handle);
		rewind($handle);
		$first = preg_replace('/^\xEF\xBB\xBF/', '', (string)$first);
		$delimiter = ',';
		$best = substr_count($first, ',');
		foreach (array(';', "\t") as $candidate) {
			if (substr_count($first, $candidate) > $best) {
				$best = substr_count($first, $candidate);
				$delimiter = $candidate;
			}
		}
		$rows = array();
		$bom = true;
		while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
			if ($bom && isset($row[0])) {
				$row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
			}
			$bom = false;
			if (count($row) == 1 && trim((string)$row[0]) === '') {
				continue; // blank line
			}
			$rows[] = array_map('trim', array_map(function ($v) { return mb_convert_encoding((string)$v, 'UTF-8', 'UTF-8, ISO-8859-1'); }, $row));
			if ($limit && count($rows) >= $limit) {
				break;
			}
		}
		fclose($handle);
		return $rows;
	}

	/**
	 * Guesses which column is what from the header row. Returns array(date, description, reference,
	 * debit, credit, amount, balance => column index or null) and the index of the header row.
	 */
	public static function detectMapping(array $rows) {
		$wanted = array(
			'date' => '/^(txn|transaction|value|posting)?\s*date$|^date$|tran\s*date/i',
			'description' => '/narration|description|particulars|remarks|details/i',
			'reference' => '/ref|chq|cheque|utr|instrument|cheque\/ref/i',
			'debit' => '/withdraw|debit|dr\b|paid out|money out/i',
			'credit' => '/deposit|credit|cr\b|paid in|money in/i',
			'amount' => '/^amount$|^amt$/i',
			'balance' => '/balance|bal\b/i',
		);
		foreach (array_slice($rows, 0, 15, true) as $index => $row) {
			$map = array_fill_keys(array_keys($wanted), null);
			foreach ($row as $col => $label) {
				foreach ($wanted as $key => $pattern) {
					if ($map[$key] === null && $label !== '' && preg_match($pattern, $label)) {
						$map[$key] = $col;
						break;
					}
				}
			}
			if ($map['date'] !== null && ($map['debit'] !== null || $map['credit'] !== null || $map['amount'] !== null)) {
				return array($map, $index);
			}
		}
		return array(array_fill_keys(array_keys($wanted), null), 0);
	}

	/** A date string as Y-m-d, trying the given format first (d-m-Y, m-d-Y, Y-m-d, d/m/y, 01-Jan-2026...). */
	public static function parseDate($text, $format = 'auto') {
		$text = trim($text);
		if ($text === '') {
			return null;
		}
		$formats = $format == 'auto' ? array('d-m-Y', 'd/m/Y', 'Y-m-d', 'd-M-Y', 'd M Y', 'd-m-y', 'd/m/y', 'd-M-y') : array($format);
		foreach ($formats as $f) {
			$date = DateTime::createFromFormat('!' . $f, $text);
			$errors = DateTime::getLastErrors();
			if ($date && (!$errors || ($errors['warning_count'] == 0 && $errors['error_count'] == 0))) {
				return $date->format('Y-m-d');
			}
		}
		return null;
	}

	/** A bank amount such as "1,23,456.78", "(500.00)" or "1234.50 Dr" as a float (negative for brackets / minus), or null when empty. */
	public static function parseAmount($text) {
		$text = trim((string)$text);
		if ($text === '' || $text === '-') {
			return null;
		}
		$negative = (bool)preg_match('/^\(.*\)$|^-|\bdr\b/i', $text);
		$number = preg_replace('/[^0-9.]/', '', str_replace(',', '', $text));
		if ($number === '' || !is_numeric($number)) {
			return null;
		}
		return $negative ? -(float)$number : (float)$number;
	}

	// ---- staging --------------------------------------------------------------------------------

	/**
	 * Stages the rows of a file as a batch. $mapping: column indexes (date, description, reference,
	 * debit, credit, amount, balance); $options: header_row, date_format, amount_sign ('negative_is_out'
	 * when one signed "amount" column is used). Returns array(batch id, staged, duplicates, skipped).
	 */
	public static function stage($accountId, $filename, array $rows, array $mapping, array $options) {
		global $adb, $current_user;
		include_once 'include/utils/BankUtils.php';
		if (!Vtiger_Bank_Utils::getAccount($accountId)) {
			throw new Exception('Choose the bank account.');
		}
		if ($mapping['date'] === null || ($mapping['debit'] === null && $mapping['credit'] === null && $mapping['amount'] === null)) {
			throw new Exception('Tell me which column holds the date and which hold the amounts.');
		}
		$lines = array();
		$skipped = 0;
		$dateFormat = $options['date_format'] ?? 'auto';
		foreach (array_slice($rows, (int)$options['header_row'] + 1) as $row) {
			$get = function ($key) use ($row, $mapping) { return ($mapping[$key] !== null && isset($row[$mapping[$key]])) ? $row[$mapping[$key]] : ''; };
			$date = self::parseDate($get('date'), $dateFormat);
			if (!$date) {
				$skipped++; // totals, footers, blank dates
				continue;
			}
			$debit = self::parseAmount($get('debit'));
			$credit = self::parseAmount($get('credit'));
			if ($mapping['amount'] !== null && $debit === null && $credit === null) {
				$amount = self::parseAmount($get('amount'));
				if ($amount !== null) {
					$out = ($options['amount_sign'] ?? 'negative_is_out') == 'negative_is_out' ? $amount < 0 : $amount > 0;
					$debit = $out ? abs($amount) : null;
					$credit = $out ? null : abs($amount);
				}
			}
			$debit = $debit !== null ? abs($debit) : null;
			$credit = $credit !== null ? abs($credit) : null;
			if (($debit === null || $debit == 0) && ($credit === null || $credit == 0)) {
				$skipped++;
				continue;
			}
			$direction = ($debit && $debit > 0) ? 'Out' : 'In';
			$lines[] = array(
				'date' => $date, 'description' => mb_substr($get('description'), 0, 250), 'reference' => mb_substr($get('reference'), 0, 100),
				'direction' => $direction, 'amount' => $direction == 'Out' ? $debit : $credit, 'balance' => self::parseAmount($get('balance')),
			);
		}
		if (!$lines) {
			throw new Exception('No transaction lines were found with that column layout. Check the header row and the date format.');
		}
		$dates = array_column($lines, 'date');
		$last = end($lines);
		$adb->pquery('INSERT INTO vtiger_bank_statement_batches (bank_account, filename, uploaded_by, uploaded_time, from_date, to_date, closing_balance)
			VALUES (?, ?, ?, NOW(), ?, ?, ?)', array($accountId, mb_substr($filename, 0, 200), $current_user->id, min($dates), max($dates), $last['balance']));
		$batchId = $adb->getLastInsertID();
		$staged = $duplicates = 0;
		foreach ($lines as $line) {
			$hash = md5(implode('|', array($accountId, $line['date'], $line['direction'], number_format($line['amount'], 2, '.', ''), $line['reference'], $line['description'])));
			// the same line twice in one file is legitimate (two equal charges); the same file imported twice is not
			$seen = $adb->pquery('SELECT COUNT(*) AS n FROM vtiger_bank_statement_lines WHERE bank_account = ? AND line_hash = ? AND batch_id != ?', array($accountId, $hash, $batchId));
			$occurrence = (int)$adb->query_result($adb->pquery('SELECT COUNT(*) FROM vtiger_bank_statement_lines WHERE batch_id = ? AND line_hash = ?', array($batchId, $hash)), 0, 0);
			$earlier = (int)$adb->query_result($seen, 0, 'n');
			if ($earlier > $occurrence) {
				$duplicates++;
				continue;
			}
			$adb->pquery('INSERT INTO vtiger_bank_statement_lines (batch_id, bank_account, line_date, description, reference, direction, amount, balance, status, line_hash)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array($batchId, $accountId, $line['date'], $line['description'], $line['reference'], $line['direction'], $line['amount'], $line['balance'], self::STATUS_UNMATCHED, $hash));
			$staged++;
		}
		if (!$staged) {
			$adb->pquery('DELETE FROM vtiger_bank_statement_batches WHERE batch_id = ?', array($batchId));
			throw new Exception("All $duplicates lines were imported before; nothing new in this file.");
		}
		self::autoMatch($batchId);
		return array($batchId, $staged, $duplicates, $skipped);
	}

	// ---- matching -------------------------------------------------------------------------------

	/** Words of a statement line worth comparing with references (3+ characters, no pure punctuation). */
	private static function tokens($text) {
		preg_match_all('/[A-Za-z0-9]{4,}/', (string)$text, $matches);
		return array_unique(array_map('strtoupper', $matches[0]));
	}

	/**
	 * Suggests, for every unmatched line of a batch, the not yet reconciled transaction of the same
	 * account with the same direction and amount within a week, preferring one whose reference,
	 * number or payment number appears on the line. A transaction is suggested for one line only.
	 * Lines that match a bank rule get the rule's ledger prepared for creation.
	 */
	public static function autoMatch($batchId) {
		global $adb;
		$lines = $adb->pquery("SELECT * FROM vtiger_bank_statement_lines WHERE batch_id = ? AND status IN (?, ?) ORDER BY line_date, line_id", array($batchId, self::STATUS_UNMATCHED, self::STATUS_SUGGESTED));
		$used = array();
		$rules = self::rules();
		while ($line = $adb->fetch_array($lines)) {
			$tokens = self::tokens($line['description'] . ' ' . $line['reference']);
			$candidates = $adb->pquery("SELECT t.banktransactionsid AS id, t.transaction_no, t.transaction_date, t.reference_no, t.narration, t.payment, p.payment_no
				FROM vtiger_banktransactions t INNER JOIN vtiger_crmentity c ON c.crmid = t.banktransactionsid AND c.deleted = 0
				LEFT JOIN vtiger_payments p ON p.paymentsid = t.payment
				WHERE t.bank_account = ? AND t.direction = ? AND ABS(t.amount - ?) < 0.005 AND (t.reconciled IS NULL OR t.reconciled = 0)
				AND t.transaction_date BETWEEN DATE_SUB(?, INTERVAL " . self::DATE_WINDOW_DAYS . " DAY) AND DATE_ADD(?, INTERVAL " . self::DATE_WINDOW_DAYS . " DAY)
				AND NOT EXISTS (SELECT 1 FROM vtiger_bank_statement_lines m WHERE m.matched_transaction = t.banktransactionsid AND m.status = ?)",
				array($line['bank_account'], $line['direction'], $line['amount'], $line['line_date'], $line['line_date'], self::STATUS_MATCHED));
			$best = null;
			$bestScore = -1;
			while ($c = $adb->fetch_array($candidates)) {
				if (isset($used[$c['id']])) {
					continue;
				}
				$score = 10 - abs((strtotime($c['transaction_date']) - strtotime($line['line_date'])) / 86400);
				foreach (array($c['reference_no'], $c['transaction_no'], $c['payment_no']) as $ref) {
					$ref = strtoupper(trim((string)$ref));
					if ($ref !== '' && strlen($ref) >= 4 && (in_array($ref, $tokens) || stripos($line['description'] . ' ' . $line['reference'], $ref) !== false)) {
						$score += 20;
					}
				}
				if ($score > $bestScore) {
					$bestScore = $score;
					$best = $c;
				}
			}
			if ($best) {
				$used[$best['id']] = true;
				$adb->pquery('UPDATE vtiger_bank_statement_lines SET status = ?, matched_transaction = ?, rule_id = NULL WHERE line_id = ?', array(self::STATUS_SUGGESTED, $best['id'], $line['line_id']));
				continue;
			}
			$rule = self::ruleFor($rules, $line);
			$adb->pquery('UPDATE vtiger_bank_statement_lines SET status = ?, matched_transaction = NULL, rule_id = ? WHERE line_id = ?',
				array(self::STATUS_UNMATCHED, $rule ? $rule['rule_id'] : null, $line['line_id']));
		}
	}

	// ---- rules ----------------------------------------------------------------------------------

	public static function rules() {
		global $adb;
		$result = $adb->pquery('SELECT * FROM vtiger_bank_rules ORDER BY LENGTH(contains_text) DESC, rule_id');
		$rules = array();
		while ($row = $adb->fetch_array($result)) {
			$rules[] = $row;
		}
		return $rules;
	}

	/** The first (most specific) rule whose text appears on the statement line, for the same direction. */
	public static function ruleFor(array $rules, array $line) {
		$text = strtolower($line['description'] . ' ' . $line['reference']);
		foreach ($rules as $rule) {
			if (($rule['direction'] == '' || $rule['direction'] == $line['direction']) && stripos($text, strtolower($rule['contains_text'])) !== false) {
				return $rule;
			}
		}
		return null;
	}

	public static function saveRule($text, $direction, $ledgerId, $transactionType) {
		global $adb;
		$text = trim($text);
		if (mb_strlen($text) < 3) {
			throw new Exception('A rule needs at least three characters of text to look for.');
		}
		$adb->pquery('DELETE FROM vtiger_bank_rules WHERE contains_text = ? AND direction = ?', array($text, $direction));
		$adb->pquery('INSERT INTO vtiger_bank_rules (contains_text, direction, ledger_id, transaction_type) VALUES (?, ?, ?, ?)', array($text, $direction, $ledgerId ?: null, $transactionType));
	}

	// ---- acting on lines -------------------------------------------------------------------------

	private static function line($lineId) {
		global $adb;
		$result = $adb->pquery('SELECT * FROM vtiger_bank_statement_lines WHERE line_id = ?', array($lineId));
		if (!$adb->num_rows($result)) {
			throw new Exception('The statement line does not exist.');
		}
		return $adb->fetch_array($result);
	}

	/** Marks a transaction reconciled against a statement line; returns nothing. */
	private static function reconcile($transactionId, $date) {
		global $adb;
		$adb->pquery('UPDATE vtiger_banktransactions SET reconciled = 1, reconciled_date = ? WHERE banktransactionsid = ?', array($date, $transactionId));
	}

	/** Confirms the suggested match of a line (or the transaction chosen by hand): both sides become reconciled. */
	public static function confirm($lineId, $transactionId = null) {
		global $adb;
		include_once 'include/utils/BankUtils.php';
		$line = self::line($lineId);
		if (!in_array($line['status'], array(self::STATUS_SUGGESTED, self::STATUS_UNMATCHED))) {
			throw new Exception('This line is already dealt with.');
		}
		$transactionId = $transactionId ?: $line['matched_transaction'];
		$transaction = Vtiger_Bank_Utils::getTransaction($transactionId);
		if (!$transaction || $transaction['bank_account'] != $line['bank_account']) {
			throw new Exception('Choose a transaction of the same bank account.');
		}
		if ($transaction['direction'] != $line['direction'] || abs((float)$transaction['amount'] - (float)$line['amount']) > 0.004) {
			throw new Exception('The transaction does not have the same direction and amount as the statement line.');
		}
		if (!empty($transaction['reconciled'])) {
			throw new Exception('That transaction is already reconciled with another line.');
		}
		$adb->pquery('UPDATE vtiger_bank_statement_lines SET status = ?, matched_transaction = ? WHERE line_id = ?', array(self::STATUS_MATCHED, $transactionId, $lineId));
		self::reconcile($transactionId, $line['line_date']);
	}

	public static function confirmAllSuggested($batchId) {
		global $adb;
		$result = $adb->pquery('SELECT line_id FROM vtiger_bank_statement_lines WHERE batch_id = ? AND status = ?', array($batchId, self::STATUS_SUGGESTED));
		$done = 0;
		while ($row = $adb->fetch_array($result)) {
			try {
				self::confirm($row['line_id']);
				$done++;
			} catch (Exception $e) {
				// leave it for the review screen
			}
		}
		return $done;
	}

	/** A suggested match that was wrong: the line goes back to unmatched and is not suggested that transaction again for now. */
	public static function reject($lineId) {
		global $adb;
		$adb->pquery('UPDATE vtiger_bank_statement_lines SET status = ?, matched_transaction = NULL WHERE line_id = ? AND status = ?', array(self::STATUS_UNMATCHED, $lineId, self::STATUS_SUGGESTED));
	}

	/** Undo a confirmed match or creation: the transaction becomes unreconciled again. */
	public static function undo($lineId) {
		global $adb;
		$line = self::line($lineId);
		if ($line['matched_transaction'] && in_array($line['status'], array(self::STATUS_MATCHED, self::STATUS_CREATED))) {
			$adb->pquery('UPDATE vtiger_banktransactions SET reconciled = 0, reconciled_date = NULL WHERE banktransactionsid = ?', array($line['matched_transaction']));
		}
		$adb->pquery('UPDATE vtiger_bank_statement_lines SET status = ?, matched_transaction = NULL WHERE line_id = ?', array(self::STATUS_UNMATCHED, $lineId));
	}

	public static function ignore($lineId) {
		global $adb;
		$adb->pquery('UPDATE vtiger_bank_statement_lines SET status = ?, matched_transaction = NULL WHERE line_id = ? AND status IN (?, ?)', array(self::STATUS_IGNORED, $lineId, self::STATUS_UNMATCHED, self::STATUS_SUGGESTED));
	}

	/**
	 * Books a statement line that has no transaction yet: creates the bank transaction (through the
	 * normal save, so validation, the lock date and the journal posting all apply) and reconciles it.
	 * $values: ledger, transaction_type, party_account, party_vendor, narration, cost_centre.
	 */
	public static function createFromLine($lineId, array $values, $rememberRule = false) {
		global $adb;
		include_once 'include/utils/BankUtils.php';
		$line = self::line($lineId);
		if (!in_array($line['status'], array(self::STATUS_UNMATCHED, self::STATUS_SUGGESTED))) {
			throw new Exception('This line is already dealt with.');
		}
		$type = $values['transaction_type'] ?? ($line['direction'] == 'In' ? 'Deposit' : 'Withdrawal');
		$id = Vtiger_Bank_Utils::createTransaction(array(
			'bank_account' => $line['bank_account'], 'transaction_date' => $line['line_date'], 'direction' => $line['direction'],
			'amount' => $line['amount'], 'transaction_type' => $type, 'reference_no' => $line['reference'],
			'narration' => $values['narration'] ?? $line['description'], 'ledger' => $values['ledger'] ?: null,
			'party_account' => $values['party_account'] ?? null, 'party_vendor' => $values['party_vendor'] ?? null,
			'cost_centre' => $values['cost_centre'] ?? null, 'reconciled' => 1,
		), false);
		$adb->pquery('UPDATE vtiger_banktransactions SET reconciled = 1, reconciled_date = ? WHERE banktransactionsid = ?', array($line['line_date'], $id));
		$adb->pquery('UPDATE vtiger_bank_statement_lines SET status = ?, matched_transaction = ? WHERE line_id = ?', array(self::STATUS_CREATED, $id, $lineId));
		if ($rememberRule && !empty($values['rule_text'])) {
			self::saveRule($values['rule_text'], $line['direction'], $values['ledger'] ?: null, $type);
		}
		return $id;
	}

	// ---- review ---------------------------------------------------------------------------------

	/** A batch with its lines, the suggested/matched transactions and counts, for the review screen. */
	public static function batch($batchId) {
		global $adb;
		$result = $adb->pquery('SELECT b.*, a.account_name FROM vtiger_bank_statement_batches b LEFT JOIN vtiger_bankaccounts a ON a.bankaccountsid = b.bank_account WHERE b.batch_id = ?', array($batchId));
		if (!$adb->num_rows($result)) {
			return null;
		}
		$batch = $adb->fetch_array($result);
		$batch['account_name'] = decode_html($batch['account_name']);
		$lines = $adb->pquery("SELECT l.*, t.transaction_no, t.transaction_date AS txn_date, t.narration AS txn_narration, t.reference_no AS txn_reference, r.ledger_id AS rule_ledger, r.contains_text AS rule_text, g.ledger_name AS rule_ledger_name
			FROM vtiger_bank_statement_lines l LEFT JOIN vtiger_banktransactions t ON t.banktransactionsid = l.matched_transaction
			LEFT JOIN vtiger_bank_rules r ON r.rule_id = l.rule_id LEFT JOIN vtiger_ledgers g ON g.ledgersid = r.ledger_id
			WHERE l.batch_id = ? ORDER BY l.line_date, l.line_id", array($batchId));
		$batch['lines'] = array();
		$batch['counts'] = array(self::STATUS_UNMATCHED => 0, self::STATUS_SUGGESTED => 0, self::STATUS_MATCHED => 0, self::STATUS_CREATED => 0, self::STATUS_IGNORED => 0);
		while ($line = $adb->fetch_array($lines)) {
			$line['description'] = decode_html($line['description']);
			$batch['counts'][$line['status']]++;
			$batch['lines'][] = $line;
		}
		include_once 'include/utils/BankUtils.php';
		$batch['reconciled_balance'] = Vtiger_Bank_Utils::reconciledBalance($batch['bank_account']);
		return $batch;
	}

	/** Not yet reconciled transactions of an account that could be matched by hand to a line of that amount. */
	public static function candidatesFor($lineId) {
		global $adb;
		$line = self::line($lineId);
		$result = $adb->pquery("SELECT t.banktransactionsid AS id, t.transaction_no, t.transaction_date, t.narration, t.reference_no FROM vtiger_banktransactions t
			INNER JOIN vtiger_crmentity c ON c.crmid = t.banktransactionsid AND c.deleted = 0
			WHERE t.bank_account = ? AND t.direction = ? AND ABS(t.amount - ?) < 0.005 AND (t.reconciled IS NULL OR t.reconciled = 0)
			AND NOT EXISTS (SELECT 1 FROM vtiger_bank_statement_lines m WHERE m.matched_transaction = t.banktransactionsid AND m.status IN ('Matched', 'Suggested'))
			ORDER BY ABS(DATEDIFF(t.transaction_date, ?)) LIMIT 10", array($line['bank_account'], $line['direction'], $line['amount'], $line['line_date']));
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$rows[] = $row;
		}
		return $rows;
	}
}
