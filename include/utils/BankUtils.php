<?php
/**
 * Banking: balances, payment postings, transfers and statements.
 *
 * A bank account's balance is its opening balance plus the transactions in minus those out. The
 * balance is stored on the account (current_balance) and on every transaction (balance_after), and
 * recalculated from the transactions whenever one is saved, deleted or restored, so it can always
 * be rebuilt from the list. Payments marked Completed with a bank account post a transaction
 * automatically; transfers post a pair.
 */
class Vtiger_Bank_Utils {

	/** Set while this class itself saves or deletes a transaction, so the handler's protections let it through. */
	public static $internal = false;

	public static function accountTypesWithoutOverdraft() {
		return array('Cash', 'Wallet / UPI');
	}

	/** Account details, or null: array(id, name, type, status, opening, opening_date, balance). */
	public static function getAccount($id) {
		global $adb;
		if (empty($id)) {
			return null;
		}
		$result = $adb->pquery('SELECT b.bankaccountsid AS id, b.account_name, b.account_type, b.status, b.opening_balance, b.opening_date, b.current_balance
			FROM vtiger_bankaccounts b INNER JOIN vtiger_crmentity c ON c.crmid = b.bankaccountsid AND c.deleted = 0 WHERE b.bankaccountsid = ?', array($id));
		if (!$adb->num_rows($result)) {
			return null;
		}
		$row = $adb->fetch_array($result);
		return array(
			'id' => $row['id'], 'name' => decode_html($row['account_name']), 'type' => decode_html($row['account_type']),
			'status' => decode_html($row['status']), 'opening' => (float)$row['opening_balance'],
			'opening_date' => $row['opening_date'], 'balance' => (float)$row['current_balance'],
		);
	}

	/** Stored transaction fields, or null. */
	public static function getTransaction($id) {
		global $adb;
		if (empty($id)) {
			return null;
		}
		$result = $adb->pquery('SELECT * FROM vtiger_banktransactions WHERE banktransactionsid = ?', array($id));
		return $adb->num_rows($result) ? $adb->fetch_array($result) : null;
	}

	/**
	 * Rebuilds balance_after on every transaction of the account (oldest first, ties by id) and the
	 * account's current_balance.
	 */
	public static function recalculate($accountId) {
		global $adb;
		$account = self::getAccount($accountId);
		if (!$account) {
			return;
		}
		// one statement for the whole account (running totals with a window function): a row-by-row
		// update took 10 s for 50,000 transactions and ran on every save; this takes about 0.2 s
		$adb->pquery("UPDATE vtiger_banktransactions t INNER JOIN (
				SELECT x.id, ? + SUM(x.signed) OVER (ORDER BY x.transaction_date, x.id) AS running FROM (
					SELECT t2.banktransactionsid AS id, t2.transaction_date, IF(t2.direction = 'In', t2.amount, -t2.amount) AS signed
					FROM vtiger_banktransactions t2 INNER JOIN vtiger_crmentity c ON c.crmid = t2.banktransactionsid AND c.deleted = 0
					WHERE t2.bank_account = ?) x
			) r ON r.id = t.banktransactionsid SET t.balance_after = ROUND(r.running, 2)", array($account['opening'], $accountId));
		$result = $adb->pquery("SELECT COALESCE(SUM(IF(t.direction = 'In', t.amount, -t.amount)), 0) AS s FROM vtiger_banktransactions t
			INNER JOIN vtiger_crmentity c ON c.crmid = t.banktransactionsid AND c.deleted = 0 WHERE t.bank_account = ?", array($accountId));
		$balance = $account['opening'] + (float)$adb->query_result($result, 0, 's');
		$adb->pquery('UPDATE vtiger_bankaccounts SET current_balance = ? WHERE bankaccountsid = ?', array(round($balance, 2), $accountId));
	}

	/** Problem with a transaction about to be saved, or null. $new is the stored row of an existing one. */
	public static function saveProblem($data, $transactionId, $stored) {
		$account = self::getAccount($data['bank_account'] ?? null);
		if (!$account) {
			return 'Choose the bank account.';
		}
		$amount = (float)($data['amount'] ?? 0);
		if ($amount <= 0) {
			return 'The amount must be more than zero.';
		}
		if (!in_array($data['direction'] ?? '', array('In', 'Out'), true)) {
			return 'Choose whether the money comes In or goes Out.';
		}
		$date = $data['transaction_date'] ?? '';
		if ($date === '' || !strtotime($date)) {
			return 'Enter the transaction date.';
		}
		if (!$stored && $account['status'] != 'Active') {
			return "The account '{$account['name']}' is inactive.";
		}
		if (!empty($account['opening_date']) && $date < $account['opening_date']) {
			return "The date is before the account's opening date ({$account['opening_date']}).";
		}

		if ($stored) {
			$changed = $stored['bank_account'] != $data['bank_account'] || $stored['direction'] != $data['direction']
				|| abs((float)$stored['amount'] - $amount) > 0.004 || $stored['transaction_date'] != $date;
			if ($changed && !empty($stored['reconciled'])) {
				return 'This transaction is reconciled with the bank statement. Un-reconcile it first to change the amount, date, account or direction.';
			}
			if ($changed && !empty($stored['payment'])) {
				return 'This transaction was posted by a payment. Change the payment instead.';
			}
			if ($changed && !empty($stored['transfer_pair'])) {
				return 'This transaction is one side of a transfer. Delete the transfer and enter it again.';
			}
		}

		// cash and wallets cannot go below zero
		if ($data['direction'] == 'Out' && in_array($account['type'], self::accountTypesWithoutOverdraft(), true)) {
			$available = $account['balance'];
			if ($stored && $stored['bank_account'] == $account['id'] && $stored['direction'] == 'Out') {
				$available += (float)$stored['amount'];
			} elseif ($stored && $stored['bank_account'] == $account['id'] && $stored['direction'] == 'In') {
				$available -= (float)$stored['amount'];
			}
			if ($amount - $available > 0.004) {
				return sprintf("'%s' has only %s available.", $account['name'], number_format($available, 2, '.', ''));
			}
		}
		return null;
	}

	/** Creates a transaction through the normal save (numbering, events). Returns its id. */
	public static function createTransaction(array $values) {
		$focus = CRMEntity::getInstance('BankTransactions');
		$focus->mode = '';
		foreach ($values as $field => $value) {
			$focus->column_fields[$field] = $value;
		}
		if (empty($focus->column_fields['assigned_user_id'])) {
			global $current_user;
			$focus->column_fields['assigned_user_id'] = $current_user->id;
		}
		self::$internal = true;
		try {
			$focus->save('BankTransactions');
		} finally {
			self::$internal = false;
		}
		return $focus->id;
	}

	/** Removes a transaction (to the recycle bin) without the handler's protections. */
	public static function removeTransaction($transactionId) {
		self::$internal = true;
		try {
			$focus = CRMEntity::getInstance('BankTransactions');
			$focus->trash('BankTransactions', $transactionId);
		} finally {
			self::$internal = false;
		}
	}

	/** Moves money between two accounts: one Out and one In transaction, linked to each other. */
	public static function transfer($fromId, $toId, $amount, $date, $reference = '', $narration = '') {
		global $adb;
		if ($fromId == $toId) {
			throw new Exception('Choose two different accounts.');
		}
		$from = self::getAccount($fromId);
		$to = self::getAccount($toId);
		if (!$from || !$to) {
			throw new Exception('Choose both accounts.');
		}
		include_once 'include/utils/LedgerUtils.php';
		$locked = Vtiger_Ledger_Utils::lockProblem($date);
		if ($locked !== null) {
			throw new Exception($locked);
		}
		$out = array('bank_account' => $fromId, 'transaction_date' => $date, 'direction' => 'Out', 'amount' => $amount);
		$problem = self::saveProblem($out, 0, null);
		if ($problem !== null) {
			throw new Exception($problem);
		}
		if ($to['status'] != 'Active') {
			throw new Exception("The account '{$to['name']}' is inactive.");
		}
		$common = array('transaction_date' => $date, 'amount' => $amount, 'reference_no' => $reference);
		$outId = self::createTransaction($common + array('bank_account' => $fromId, 'direction' => 'Out', 'transaction_type' => 'Transfer Out',
			'narration' => trim("Transfer to {$to['name']}. $narration")));
		$inId = self::createTransaction($common + array('bank_account' => $toId, 'direction' => 'In', 'transaction_type' => 'Transfer In',
			'narration' => trim("Transfer from {$from['name']}. $narration")));
		$adb->pquery('UPDATE vtiger_banktransactions SET transfer_pair = ? WHERE banktransactionsid = ?', array($inId, $outId));
		$adb->pquery('UPDATE vtiger_banktransactions SET transfer_pair = ? WHERE banktransactionsid = ?', array($outId, $inId));
		// book the movement between the two accounts' ledgers
		include_once 'include/utils/LedgerUtils.php';
		Vtiger_Ledger_Utils::syncBankTransaction($inId);
		return array($outId, $inId);
	}

	/** Id of the transaction a payment has posted, or null. */
	public static function transactionForPayment($paymentId) {
		global $adb;
		$result = $adb->pquery('SELECT t.banktransactionsid FROM vtiger_banktransactions t
			INNER JOIN vtiger_crmentity c ON c.crmid = t.banktransactionsid AND c.deleted = 0 WHERE t.payment = ?', array($paymentId));
		return $adb->num_rows($result) ? $adb->query_result($result, 0, 0) : null;
	}

	/** Problem with changing a payment whose bank transaction is already reconciled, or null. */
	public static function paymentChangeProblem($paymentId, $newAccountId, $newAmount, $newStatus, $newDate) {
		$transactionId = self::transactionForPayment($paymentId);
		if (!$transactionId) {
			return null;
		}
		$stored = self::getTransaction($transactionId);
		if (empty($stored['reconciled'])) {
			return null;
		}
		if ($newStatus != 'Completed' || $stored['bank_account'] != $newAccountId || abs((float)$stored['amount'] - (float)$newAmount) > 0.004 || $stored['transaction_date'] != $newDate) {
			return 'The bank transaction of this payment is reconciled with the bank statement. Un-reconcile it before changing the payment.';
		}
		return null;
	}

	/**
	 * Keeps the bank transaction of a payment in step with it: a Completed payment with a bank
	 * account has one transaction (In for Received, Out for Paid); a pending or failed payment, a
	 * payment without an account, or a deleted payment has none.
	 */
	public static function syncPayment($paymentId) {
		global $adb;
		$result = $adb->pquery('SELECT p.payment_no, p.related_to, p.direction, p.amount, p.payment_date, p.payment_method, p.reference_no,
				p.status, p.bank_account, p.account_id, p.vendor_id, c.deleted
			FROM vtiger_payments p INNER JOIN vtiger_crmentity c ON c.crmid = p.paymentsid WHERE p.paymentsid = ?', array($paymentId));
		if (!$adb->num_rows($result)) {
			return;
		}
		$p = $adb->fetch_array($result);
		$existing = self::transactionForPayment($paymentId);
		$wanted = !$p['deleted'] && $p['status'] == 'Completed' && !empty($p['bank_account']) && self::getAccount($p['bank_account']);

		if (!$wanted) {
			if ($existing) {
				self::removeTransaction($existing);
			}
			return;
		}
		$values = array(
			'bank_account' => $p['bank_account'],
			'transaction_date' => $p['payment_date'],
			'direction' => $p['direction'] == 'Paid' ? 'Out' : 'In',
			'transaction_type' => $p['direction'] == 'Paid' ? 'Payment Made' : 'Payment Received',
			'amount' => $p['amount'],
			'reference_no' => $p['reference_no'],
			'narration' => 'Payment ' . $p['payment_no'],
			'party_account' => $p['account_id'] ?: null,
			'party_vendor' => $p['vendor_id'] ?: null,
			'payment' => $paymentId,
		);
		if (!$existing) {
			$id = self::createTransaction($values);
			$adb->pquery('UPDATE vtiger_banktransactions SET payment = ? WHERE banktransactionsid = ?', array($paymentId, $id));
			return;
		}
		$oldAccount = self::getTransaction($existing)['bank_account'];
		$adb->pquery('UPDATE vtiger_banktransactions SET bank_account = ?, transaction_date = ?, direction = ?, transaction_type = ?, amount = ?,
				reference_no = ?, narration = ?, party_account = ?, party_vendor = ? WHERE banktransactionsid = ?',
			array($values['bank_account'], $values['transaction_date'], $values['direction'], $values['transaction_type'], $values['amount'],
				$values['reference_no'], $values['narration'], $values['party_account'], $values['party_vendor'], $existing));
		self::recalculate($values['bank_account']);
		if ($oldAccount != $values['bank_account']) {
			self::recalculate($oldAccount);
		}
	}

	/**
	 * Statement of an account for a period: array(opening, rows, in, out, closing). Opening is the
	 * balance before $from; rows carry the running balance.
	 */
	public static function statement($accountId, $from, $to, $unreconciledOnly = false) {
		global $adb;
		$account = self::getAccount($accountId);
		if (!$account) {
			return null;
		}
		$opening = $account['opening'];
		if ($from) {
			$before = $adb->pquery("SELECT SUM(CASE WHEN t.direction = 'In' THEN t.amount ELSE -t.amount END) AS s FROM vtiger_banktransactions t
				INNER JOIN vtiger_crmentity c ON c.crmid = t.banktransactionsid AND c.deleted = 0
				WHERE t.bank_account = ? AND t.transaction_date < ?", array($accountId, $from));
			$opening += (float)$adb->query_result($before, 0, 's');
		}
		$sql = "SELECT t.banktransactionsid AS id, t.transaction_no, t.transaction_date, t.direction, t.transaction_type, t.amount, t.reference_no,
				t.narration, t.reconciled, t.reconciled_date, t.ledger, t.payment, t.transfer_pair
			FROM vtiger_banktransactions t INNER JOIN vtiger_crmentity c ON c.crmid = t.banktransactionsid AND c.deleted = 0
			WHERE t.bank_account = ?";
		$params = array($accountId);
		if ($from) {
			$sql .= ' AND t.transaction_date >= ?';
			$params[] = $from;
		}
		if ($to) {
			$sql .= ' AND t.transaction_date <= ?';
			$params[] = $to;
		}
		if ($unreconciledOnly) {
			$sql .= ' AND (t.reconciled IS NULL OR t.reconciled = 0)';
		}
		$result = $adb->pquery($sql . ' ORDER BY t.transaction_date, t.banktransactionsid', $params);
		$rows = array();
		$in = $out = 0.0;
		$balance = $opening;
		while ($row = $adb->fetch_array($result)) {
			$amount = (float)$row['amount'];
			if ($row['direction'] == 'In') {
				$in += $amount;
				$balance += $amount;
			} else {
				$out += $amount;
				$balance -= $amount;
			}
			$row['running'] = round($balance, 2);
			$rows[] = $row;
		}
		return array('account' => $account, 'opening' => round($opening, 2), 'rows' => $rows, 'in' => round($in, 2), 'out' => round($out, 2), 'closing' => round($balance, 2));
	}

	/** Sum of reconciled transactions' effect on the account: what the bank statement should show. */
	public static function reconciledBalance($accountId) {
		global $adb;
		$account = self::getAccount($accountId);
		$result = $adb->pquery("SELECT SUM(CASE WHEN t.direction = 'In' THEN t.amount ELSE -t.amount END) AS s FROM vtiger_banktransactions t
			INNER JOIN vtiger_crmentity c ON c.crmid = t.banktransactionsid AND c.deleted = 0
			WHERE t.bank_account = ? AND t.reconciled = 1", array($accountId));
		return round($account['opening'] + (float)$adb->query_result($result, 0, 's'), 2);
	}
}
