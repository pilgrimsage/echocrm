<?php
/**
 * Recurring invoices: an existing invoice is the template, a schedule says how often to raise a copy.
 *
 * Each due date creates a new Invoice that copies the template's customer, addresses, line items, charges,
 * taxes, cost centre and terms (through the copy vtiger's Invoice class already has), with its own number and
 * the scheduled date as invoice date, the template's payment period as due date and a status of your choice:
 * "Created" leaves it as a draft to check and approve, "Approved" / "Sent" books it straight away. Missed
 * periods are raised dated on their own scheduled day (up to 12 in one run). Every run is logged.
 *
 * Not covered: price indexation, usage-based quantities, proration, pausing for a date range.
 */
class Vtiger_Recurring_Utils {

	const MAX_CATCH_UP = 12;

	public static function frequencies() {
		return array('Weekly' => array('weeks', 1), 'Fortnightly' => array('weeks', 2), 'Monthly' => array('months', 1),
			'Quarterly' => array('months', 3), 'Half-Yearly' => array('months', 6), 'Yearly' => array('months', 12));
	}

	/** The n-th occurrence (0 = the start date itself) of a schedule, keeping the start day of the month (31 Jan, 28 Feb, 31 Mar...). */
	public static function occurrence($start, $frequency, $n) {
		$f = self::frequencies()[$frequency] ?? null;
		if (!$f) {
			throw new Exception('Unknown frequency.');
		}
		if ($f[0] == 'weeks') {
			return date('Y-m-d', strtotime($start . ' +' . ($n * $f[1] * 7) . ' days'));
		}
		$anchorDay = (int)date('j', strtotime($start));
		// count months from the 1st so that 31 January + 1 month is February, not early March
		$monthStart = date('Y-m-01', strtotime(date('Y-m-01', strtotime($start)) . ' +' . ($n * $f[1]) . ' months'));
		$day = min($anchorDay, (int)date('t', strtotime($monthStart)));
		return date('Y-m-', strtotime($monthStart)) . sprintf('%02d', $day);
	}

	public static function get($id) {
		global $adb;
		$result = $adb->pquery('SELECT r.*, i.invoice_no, i.subject AS template_subject, a.accountname FROM vtiger_recurring_invoices r
			LEFT JOIN vtiger_invoice i ON i.invoiceid = r.template_invoice LEFT JOIN vtiger_account a ON a.accountid = i.accountid WHERE r.rec_id = ?', array($id));
		return $adb->num_rows($result) ? $adb->fetch_array($result) : null;
	}

	/** Creates a schedule from an invoice. Returns its id. */
	public static function create($templateInvoice, $frequency, $start, $end, $maxCount, $invoiceStatus, $notes = '') {
		global $adb, $current_user;
		$check = $adb->pquery('SELECT i.invoiceid FROM vtiger_invoice i INNER JOIN vtiger_crmentity c ON c.crmid = i.invoiceid AND c.deleted = 0 WHERE i.invoiceid = ?', array($templateInvoice));
		if (!$adb->num_rows($check)) {
			throw new Exception('The template invoice does not exist.');
		}
		self::occurrence($start ?: '', $frequency, 0);
		if (!$start || !strtotime($start)) {
			throw new Exception('Enter the date of the first invoice.');
		}
		if ($end && $end < $start) {
			throw new Exception('The end date is before the start date.');
		}
		if (!in_array($invoiceStatus, array('Created', 'Approved', 'Sent'), true)) {
			throw new Exception('Choose the status the new invoices get.');
		}
		$adb->pquery('INSERT INTO vtiger_recurring_invoices (template_invoice, frequency, start_date, end_date, max_count, next_date, raised, status, invoice_status, notes, created_by, created_time)
			VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, NOW())', array($templateInvoice, $frequency, $start, $end ?: null, $maxCount ?: null, $start, 'Active', $invoiceStatus, $notes, $current_user->id));
		return $adb->getLastInsertID();
	}

	public static function setStatus($id, $status) {
		global $adb;
		if (!in_array($status, array('Active', 'Paused', 'Ended'), true)) {
			throw new Exception('Unknown status.');
		}
		$adb->pquery('UPDATE vtiger_recurring_invoices SET status = ? WHERE rec_id = ?', array($status, $id));
	}

	private static function log($recId, $date, $invoiceId, $ok, $message) {
		global $adb;
		$adb->pquery('INSERT INTO vtiger_recurring_invoice_log (rec_id, scheduled_date, invoice_id, ok, message, run_time) VALUES (?, ?, ?, ?, ?, NOW())',
			array($recId, $date, $invoiceId, $ok ? 1 : 0, mb_substr($message, 0, 250)));
	}

	/** Raises the invoice of one scheduled date. Returns the new invoice id. */
	public static function generate(array $rec, $date) {
		global $current_user, $adb;
		$template = CRMEntity::getInstance('Invoice');
		$template->retrieve_entity_info($rec['template_invoice'], 'Invoice');
		$new = CRMEntity::getInstance('Invoice');
		$new->mode = '';
		$skip = array('record_id', 'record_module', 'label', 'createdtime', 'modifiedtime', 'modifiedby', 'tags', 'received', 'balance', 'invoicestatus', 'invoicedate', 'duedate',
			'invoice_no', 'salesorder_id', 'starred', 'id');
		foreach ($template->column_fields as $field => $value) {
			if (!in_array($field, $skip, true)) {
				$new->column_fields[$field] = $value;
			}
		}
		$offset = 0;
		if (!empty($template->column_fields['duedate']) && !empty($template->column_fields['invoicedate'])) {
			$offset = (int)round((strtotime(Vtiger_Ledger_Utils::dbDate($template->column_fields['duedate'])) - strtotime(Vtiger_Ledger_Utils::dbDate($template->column_fields['invoicedate']))) / 86400);
		}
		$new->column_fields['invoicedate'] = $date;
		$new->column_fields['duedate'] = date('Y-m-d', strtotime($date . ' +' . max(0, $offset) . ' days'));
		$new->column_fields['invoicestatus'] = $rec['invoice_status'];
		$new->column_fields['received'] = 0;
		$new->column_fields['balance'] = $template->column_fields['hdnGrandTotal'];
		$new->column_fields['subject'] = decode_html($template->column_fields['subject']);
		if (empty($new->column_fields['assigned_user_id'])) {
			$new->column_fields['assigned_user_id'] = $current_user->id;
		}
		// vtiger's own copy of line items, taxes, charges and totals from another inventory record
		$new->_recurring_mode = 'recurringinvoice_from_so';
		$new->_salesorderid = $rec['template_invoice'];
		$new->save('Invoice');
		$adb->pquery('UPDATE vtiger_invoice SET balance = total WHERE invoiceid = ?', array($new->id));
		return $new->id;
	}

	/**
	 * Raises every invoice that is due on or before $today. Returns array(raised count, problems).
	 * A schedule that fails stops for this run and says why in the log; the others carry on.
	 */
	public static function runDue($today, $onlySchedule = null) {
		global $adb;
		$result = $adb->pquery("SELECT * FROM vtiger_recurring_invoices WHERE status = 'Active' AND next_date <= ?" . ($onlySchedule ? ' AND rec_id = ' . (int)$onlySchedule : ''), array($today));
		$raised = 0;
		$problems = array();
		while ($rec = $adb->fetch_array($result)) {
			$count = 0;
			while ($rec['next_date'] <= $today && $count < self::MAX_CATCH_UP) {
				$date = $rec['next_date'];
				if (($rec['end_date'] && $date > $rec['end_date']) || ($rec['max_count'] && $rec['raised'] >= $rec['max_count'])) {
					self::setStatus($rec['rec_id'], 'Ended');
					break;
				}
				try {
					$invoiceId = self::generate($rec, $date);
				} catch (Exception $e) {
					self::log($rec['rec_id'], $date, null, false, $e->getMessage());
					$problems[] = "Schedule {$rec['rec_id']}: " . $e->getMessage();
					break;
				}
				self::log($rec['rec_id'], $date, $invoiceId, true, 'Invoice raised');
				$rec['raised']++;
				$rec['next_date'] = self::occurrence($rec['start_date'], $rec['frequency'], $rec['raised']);
				$ended = ($rec['end_date'] && $rec['next_date'] > $rec['end_date']) || ($rec['max_count'] && $rec['raised'] >= $rec['max_count']);
				$adb->pquery('UPDATE vtiger_recurring_invoices SET raised = ?, next_date = ?, last_run = NOW(), last_invoice = ?, status = ? WHERE rec_id = ?',
					array($rec['raised'], $rec['next_date'], $invoiceId, $ended ? 'Ended' : 'Active', $rec['rec_id']));
				$raised++;
				$count++;
				if ($ended) {
					break;
				}
			}
		}
		return array($raised, $problems);
	}

	/** Schedules with their next date and how many were raised, newest first. */
	public static function listAll() {
		global $adb;
		$result = $adb->pquery('SELECT r.*, i.invoice_no, i.total, a.accountname FROM vtiger_recurring_invoices r
			LEFT JOIN vtiger_invoice i ON i.invoiceid = r.template_invoice LEFT JOIN vtiger_account a ON a.accountid = i.accountid ORDER BY r.status = \'Active\' DESC, r.next_date, r.rec_id DESC');
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$row['accountname'] = decode_html($row['accountname']);
			$rows[] = $row;
		}
		return $rows;
	}

	public static function logFor($recId, $limit = 20) {
		global $adb;
		$result = $adb->pquery('SELECT l.*, i.invoice_no FROM vtiger_recurring_invoice_log l LEFT JOIN vtiger_invoice i ON i.invoiceid = l.invoice_id WHERE l.rec_id = ? ORDER BY l.log_id DESC LIMIT ' . (int)$limit, array($recId));
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$rows[] = $row;
		}
		return $rows;
	}
}
