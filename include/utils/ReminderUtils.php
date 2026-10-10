<?php
/**
 * Payment reminders for unpaid invoices.
 *
 * A reminder rule says when (days relative to the due date: negative = before, positive = after) and what to
 * write (subject and body with {customer} {invoice_no} {invoice_date} {due_date} {amount_due} {days_overdue}
 * {company}). Each invoice gets each rule at most once. When several rules are already due for an invoice only
 * the latest one is sent and the earlier ones are marked skipped, so a customer is never sent three emails at
 * once. Mail goes to the invoice's contact, else the organization's email address.
 *
 * Nothing is sent without an outgoing mail server (Settings > Outgoing Server): the attempt is logged as
 * "No mail server" and can be repeated. Automatic sending by the daily job is off until it is switched on in
 * the reminder rules screen; sending by hand is always possible.
 *
 * Not covered: SMS / WhatsApp, attaching the invoice PDF, reminders by customer segment or credit limit,
 * interest on late payment, stopping reminders for a disputed invoice.
 */
class Vtiger_Reminder_Utils {

	public static function seedRules() {
		global $adb;
		$have = $adb->pquery('SELECT COUNT(*) FROM vtiger_reminder_rules');
		if ((int)$adb->query_result($have, 0, 0) > 0) {
			return;
		}
		$rules = array(
			array('Friendly reminder (3 days before)', -3, 'Invoice {invoice_no} falls due on {due_date}',
				"Dear {customer},\n\nThis is a friendly reminder that invoice {invoice_no} of {invoice_date} for {amount_due} is due on {due_date}.\n\nIf you have already arranged payment, please ignore this message.\n\nThank you,\n{company}"),
			array('Payment overdue (3 days)', 3, 'Payment overdue: invoice {invoice_no}',
				"Dear {customer},\n\nOur records show that invoice {invoice_no} for {amount_due}, due on {due_date}, is still unpaid ({days_overdue} days overdue).\n\nPlease arrange payment or let us know if there is a problem.\n\nThank you,\n{company}"),
			array('Second reminder (10 days)', 10, 'Second reminder: invoice {invoice_no} is overdue',
				"Dear {customer},\n\nWe have not yet received payment of {amount_due} for invoice {invoice_no}, which was due on {due_date} ({days_overdue} days ago).\n\nPlease settle it as soon as possible.\n\nRegards,\n{company}"),
			array('Final notice (21 days)', 21, 'Final notice: invoice {invoice_no}',
				"Dear {customer},\n\nInvoice {invoice_no} for {amount_due} is now {days_overdue} days overdue. Please pay immediately or contact us today to avoid further action.\n\nRegards,\n{company}"),
		);
		foreach ($rules as $r) {
			$adb->pquery('INSERT INTO vtiger_reminder_rules (label, days_offset, subject, body, active) VALUES (?, ?, ?, ?, 1)', $r);
		}
	}

	public static function rules($onlyActive = false) {
		global $adb;
		$result = $adb->pquery('SELECT * FROM vtiger_reminder_rules' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY days_offset');
		$rules = array();
		while ($row = $adb->fetch_array($result)) {
			$rules[] = $row;
		}
		return $rules;
	}

	public static function saveRule($id, $label, $offset, $subject, $body, $active) {
		global $adb;
		if (trim($label) === '' || trim($subject) === '' || trim($body) === '') {
			throw new Exception('A rule needs a name, a subject and a message.');
		}
		if ($offset < -60 || $offset > 365) {
			throw new Exception('Days must be between -60 (before due) and 365 (after due).');
		}
		if ($id) {
			$adb->pquery('UPDATE vtiger_reminder_rules SET label = ?, days_offset = ?, subject = ?, body = ?, active = ? WHERE rule_id = ?', array($label, (int)$offset, $subject, $body, $active ? 1 : 0, $id));
		} else {
			$adb->pquery('INSERT INTO vtiger_reminder_rules (label, days_offset, subject, body, active) VALUES (?, ?, ?, ?, ?)', array($label, (int)$offset, $subject, $body, $active ? 1 : 0));
		}
	}

	public static function deleteRule($id) {
		global $adb;
		$adb->pquery('DELETE FROM vtiger_reminder_rules WHERE rule_id = ?', array($id));
	}

	public static function mailConfigured() {
		global $adb;
		$result = $adb->pquery("SELECT server FROM vtiger_systems WHERE server_type = 'email'");
		return $adb->num_rows($result) > 0 && trim((string)$adb->query_result($result, 0, 'server')) !== '';
	}

	public static function autoEnabled() {
		return Vtiger_Ledger_Utils::getSetting('auto_reminders') == '1';
	}

	/** Open invoices (posted, with a balance) and what is due for each: array of invoice rows with 'days', 'rule', 'email', 'last_sent'. */
	public static function overdue($today) {
		global $adb;
		$result = $adb->pquery("SELECT i.invoiceid, i.invoice_no, i.invoicedate, i.balance, i.total, i.accountid, i.contactid,
				COALESCE(NULLIF(i.duedate, '0000-00-00'), i.invoicedate) AS due, a.accountname, a.email1 AS account_email, ct.email AS contact_email
			FROM vtiger_invoice i INNER JOIN vtiger_crmentity c ON c.crmid = i.invoiceid AND c.deleted = 0
			LEFT JOIN vtiger_account a ON a.accountid = i.accountid LEFT JOIN vtiger_contactdetails ct ON ct.contactid = i.contactid
			WHERE i.invoicestatus IN ('Approved', 'Sent', 'Credit Invoice') AND i.balance > 0.004
			AND COALESCE(NULLIF(i.duedate, '0000-00-00'), i.invoicedate) <= DATE_ADD(?, INTERVAL 60 DAY)
			ORDER BY due LIMIT 1000", array($today));
		$rules = self::rules(true);
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$row['accountname'] = decode_html($row['accountname']);
			$row['days'] = (int)floor((strtotime($today) - strtotime($row['due'])) / 86400);
			$row['email'] = trim((string)($row['contact_email'] ?: $row['account_email']));
			$done = array();
			$log = $adb->pquery("SELECT rule_id, status, MAX(sent_time) AS t FROM vtiger_reminder_log WHERE invoice_id = ? GROUP BY rule_id, status", array($row['invoiceid']));
			$row['last_sent'] = null;
			while ($l = $adb->fetch_array($log)) {
				if (in_array($l['status'], array('Sent', 'Skipped'), true)) {
					$done[$l['rule_id']] = true;
				}
				if ($l['status'] == 'Sent' && (!$row['last_sent'] || $l['t'] > $row['last_sent'])) {
					$row['last_sent'] = $l['t'];
				}
			}
			$row['rule'] = null;
			$row['skipped'] = array();
			foreach ($rules as $rule) {
				// a "before the due date" rule only applies until the due date has passed
				$applies = $row['days'] >= $rule['days_offset'] && ($rule['days_offset'] > 0 || $row['days'] <= 0);
				if ($applies && empty($done[$rule['rule_id']])) {
					if ($row['rule']) {
						$row['skipped'][] = $row['rule']['rule_id'];
					}
					$row['rule'] = $rule;
				}
			}
			$rows[] = $row;
		}
		return $rows;
	}

	private static function fill($text, array $row) {
		$company = '';
		global $adb;
		$org = $adb->pquery('SELECT organizationname FROM vtiger_organizationdetails LIMIT 1');
		if ($adb->num_rows($org)) {
			$company = decode_html($adb->query_result($org, 0, 0));
		}
		$replace = array(
			'{customer}' => $row['accountname'], '{invoice_no}' => decode_html($row['invoice_no']), '{invoice_date}' => $row['invoicedate'], '{due_date}' => $row['due'],
			'{amount_due}' => number_format((float)$row['balance'], 2), '{days_overdue}' => max(0, (int)$row['days']), '{company}' => $company,
		);
		return strtr($text, $replace);
	}

	/** Sends (or tries to send) a rule's reminder for an invoice and logs what happened. Returns the status. */
	public static function send(array $row, array $rule, $today) {
		global $adb, $current_user;
		foreach ($row['skipped'] ?? array() as $skippedRule) {
			$adb->pquery("INSERT INTO vtiger_reminder_log (invoice_id, rule_id, sent_time, to_address, status, message) VALUES (?, ?, NOW(), '', 'Skipped', 'A later reminder was due')", array($row['invoiceid'], $skippedRule));
		}
		$subject = self::fill($rule['subject'], $row);
		$body = nl2br(htmlspecialchars(self::fill($rule['body'], $row)));
		$status = 'Failed';
		$message = '';
		if ($row['email'] === '') {
			$status = 'No email';
			$message = 'The contact and the organization have no email address.';
		} elseif (!self::mailConfigured()) {
			$status = 'No mail server';
			$message = 'Set up the outgoing mail server first (Settings > Outgoing Server).';
		} else {
			include_once 'modules/Emails/mail.php';
			try {
				$result = send_mail('Invoice', $row['email'], $current_user->user_name, '', $subject, $body);
				$status = ($result === 1 || $result === '1') ? 'Sent' : 'Failed';
				$message = $status == 'Sent' ? '' : ((string)$result !== '' && (string)$result !== '0' ? (string)$result : 'The mail server did not accept the message (check Settings > Outgoing Server).');
			} catch (Exception $e) {
				$message = $e->getMessage();
			}
		}
		$adb->pquery('INSERT INTO vtiger_reminder_log (invoice_id, rule_id, sent_time, to_address, status, message) VALUES (?, ?, NOW(), ?, ?, ?)',
			array($row['invoiceid'], $rule['rule_id'], $row['email'], $status, mb_substr($message, 0, 250)));
		return $status;
	}

	/** The daily job: sends what is due when automatic sending is on. Returns array(sent, not sent). */
	public static function runDue($today, $force = false) {
		if (!$force && !self::autoEnabled()) {
			return array(0, 0);
		}
		$sent = $other = 0;
		foreach (self::overdue($today) as $row) {
			if (!$row['rule']) {
				continue;
			}
			self::send($row, $row['rule'], $today) == 'Sent' ? $sent++ : $other++;
		}
		return array($sent, $other);
	}

	public static function recentLog($limit = 40) {
		global $adb;
		$result = $adb->pquery('SELECT l.*, i.invoice_no, r.label FROM vtiger_reminder_log l LEFT JOIN vtiger_invoice i ON i.invoiceid = l.invoice_id
			LEFT JOIN vtiger_reminder_rules r ON r.rule_id = l.rule_id ORDER BY l.log_id DESC LIMIT ' . (int)$limit);
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$rows[] = $row;
		}
		return $rows;
	}
}
