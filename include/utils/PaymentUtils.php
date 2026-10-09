<?php
/**
 * Payments taken against (or paid for) quotes, invoices, purchase orders and credit/debit notes.
 *
 * A payment record points to one document. This class knows, per document type, where its total
 * and status live, which statuses accept payments, how much is still outstanding, which direction
 * the money moves and how to keep the document's own received/paid and balance fields in step.
 */
class Vtiger_Payment_Utils {

	const STATUS_COMPLETED = 'Completed';
	const STATUS_PENDING = 'Pending';

	/** Modules a payment can be recorded against. */
	public static function documentModules() {
		return array('Invoice', 'Quotes', 'PurchaseOrder');
	}

	/**
	 * Per module: table, id column, number column, total column, status column, statuses that
	 * accept payments (null = any except $blocked), statuses that never do.
	 */
	private static function documentInfo($module) {
		$info = array(
			'Invoice' => array('vtiger_invoice', 'invoiceid', 'invoice_no', 'total', 'invoicestatus', array('Approved', 'Sent', 'Credit Invoice', 'Paid'), array()),
			'Quotes' => array('vtiger_quotes', 'quoteid', 'quote_no', 'total', 'quotestage', null, array('Rejected')),
			'PurchaseOrder' => array('vtiger_purchaseorder', 'purchaseorderid', 'purchaseorder_no', 'total', 'postatus', array('Approved', 'Delivered', 'Received Shipment'), array()),
		);
		return isset($info[$module]) ? $info[$module] : null;
	}

	/** Details of a document: array(module, id, number, total, status, party account, party vendor, note type) or null. */
	public static function getDocument($module, $id) {
		global $adb;
		$info = self::documentInfo($module);
		if (!$info || empty($id)) {
			return null;
		}
		list($table, $idColumn, $numberColumn, $totalColumn, $statusColumn) = $info;
		$partyColumns = array(
			'Invoice' => array('accountid', 'NULL'),
			'Quotes' => array('accountid', 'NULL'),
			'PurchaseOrder' => array('NULL', 'vendorid'),
		);
		list($accountColumn, $vendorColumn) = $partyColumns[$module];
		$noteColumn = 'NULL';
		$result = $adb->pquery("SELECT p.$numberColumn AS no, p.$totalColumn AS total, p.$statusColumn AS status,
				" . ($accountColumn == 'NULL' ? 'NULL' : "p.$accountColumn") . " AS account,
				" . ($vendorColumn == 'NULL' ? 'NULL' : "p.$vendorColumn") . " AS vendor,
				$noteColumn AS note_type, c.deleted
			FROM $table p INNER JOIN vtiger_crmentity c ON c.crmid = p.$idColumn WHERE p.$idColumn = ?", array($id));
		if (!$adb->num_rows($result) || $adb->query_result($result, 0, 'deleted')) {
			return null;
		}
		return array(
			'module' => $module,
			'id' => $id,
			'number' => decode_html($adb->query_result($result, 0, 'no')),
			'total' => (float)$adb->query_result($result, 0, 'total'),
			'status' => decode_html($adb->query_result($result, 0, 'status')),
			'account' => $adb->query_result($result, 0, 'account'),
			'vendor' => $adb->query_result($result, 0, 'vendor'),
			'note_type' => $adb->query_result($result, 0, 'note_type'),
		);
	}

	/** 'Received' when money comes in, 'Paid' when it goes out. */
	public static function directionFor($document) {
		switch ($document['module']) {
			case 'PurchaseOrder':
				return 'Paid';
			default:
				return 'Received';
		}
	}

	/** Sum of payments on a document with the given statuses, optionally leaving one payment out. */
	public static function sumPayments($module, $id, $statuses, $excludePaymentId = 0) {
		global $adb;
		$marks = implode(',', array_fill(0, count($statuses), '?'));
		$params = array_merge(array($id, (int)$excludePaymentId), $statuses);
		$result = $adb->pquery("SELECT SUM(p.amount) AS s FROM vtiger_payments p
			INNER JOIN vtiger_crmentity c ON c.crmid = p.paymentsid AND c.deleted = 0
			WHERE p.related_to = ? AND p.paymentsid != ? AND p.status IN ($marks)", $params);
		return (float)$adb->query_result($result, 0, 's');
	}

	/**
	 * Part of the document's total not yet covered by completed or pending payments or, for an
	 * invoice / purchase order, by the credit / debit notes issued against it (returns reduce what
	 * the customer owes or what is owed to the vendor, so they settle the document like money does).
	 */
	public static function outstanding($document, $excludePaymentId = 0) {
		$taken = self::sumPayments($document['module'], $document['id'], array(self::STATUS_COMPLETED, self::STATUS_PENDING), $excludePaymentId);
		return round($document['total'] - $taken - self::notesTotal($document['module'], $document['id']), 2);
	}

	/** Total of the active credit notes (invoice) or debit notes (purchase order) issued against a document. */
	public static function notesTotal($module, $id) {
		if (!in_array($module, array('Invoice', 'PurchaseOrder'))) {
			return 0.0;
		}
		include_once 'include/utils/NoteUtils.php';
		return Vtiger_Note_Utils::appliedTotal($module, $id);
	}

	/** Why a payment cannot be taken for this document, or null. */
	public static function recordingProblem($module, $id) {
		$document = self::getDocument($module, $id);
		if (!$document) {
			return 'The document does not exist.';
		}
		$info = self::documentInfo($module);
		list(, , , , , $allowed, $blocked) = $info;
		if (($allowed !== null && !in_array($document['status'], $allowed, true)) || in_array($document['status'], $blocked, true)) {
			$what = $allowed !== null ? 'one that is ' . implode(', ', $allowed) : 'one that is not ' . implode(', ', $blocked);
			return "Payments can only be recorded against $what (this document is '{$document['status']}').";
		}
		if ($document['total'] <= 0) {
			return 'The document has no amount to pay.';
		}
		if (self::outstanding($document) <= 0.004) {
			return 'The document is already fully covered by payments and returns.';
		}
		return null;
	}

	/** Problem with a payment about to be saved, or null. */
	public static function saveProblem($module, $id, $amount, $status, $paymentId = 0) {
		$document = self::getDocument($module, $id);
		if (!$document) {
			return 'Choose the document the payment belongs to.';
		}
		if ($amount <= 0) {
			return 'The amount must be more than zero.';
		}
		if (!in_array($status, array('Completed', 'Pending', 'Failed'), true)) {
			return 'Unknown payment status.';
		}
		$existing = self::paymentDocument($paymentId);
		if (!$existing || $existing != $id) {
			// a new payment, or one moved to another document: that document must accept payments
			$problem = self::recordingProblem($module, $id);
			if ($problem !== null) {
				return $problem;
			}
		}
		if ($status != 'Failed') {
			$left = self::outstanding($document, $paymentId);
			if ($amount - $left > 0.004) {
				return sprintf('The amount %s is more than the %s still outstanding on %s.', number_format($amount, 2, '.', ''), number_format($left, 2, '.', ''), $document['number']);
			}
		}
		return null;
	}

	/** Document id a stored payment points to, or null. */
	public static function paymentDocument($paymentId) {
		global $adb;
		if (empty($paymentId)) {
			return null;
		}
		$result = $adb->pquery('SELECT related_to FROM vtiger_payments WHERE paymentsid = ?', array($paymentId));
		return $adb->num_rows($result) ? $adb->query_result($result, 0, 'related_to') : null;
	}

	/**
	 * Brings the document's own fields up to date after a payment was saved, deleted or restored:
	 * invoices keep "received" and "balance" (and become Paid when nothing is left), purchase
	 * orders keep "paid" and "balance". Quotes and notes only list their payments.
	 */
	public static function refreshDocument($module, $id) {
		global $adb;
		$document = self::getDocument($module, $id);
		if (!$document || !in_array($module, array('Invoice', 'PurchaseOrder'))) {
			return;
		}
		$settled = self::sumPayments($module, $id, array(self::STATUS_COMPLETED));
		$credited = self::notesTotal($module, $id);
		$balance = round($document['total'] - $settled - $credited, 2);
		if ($module == 'Invoice') {
			$adb->pquery('UPDATE vtiger_invoice SET received = ?, balance = ? WHERE invoiceid = ?', array($settled, $balance, $id));
			$status = $document['status'];
			$open = array('Approved', 'Sent', 'Credit Invoice', 'Paid');
			if ($balance <= 0.004 && in_array($status, $open, true) && ($settled > 0 || $credited > 0)) {
				// money in settles it as Paid; returns alone leave it as a Credit Invoice
				$adb->pquery('UPDATE vtiger_invoice SET invoicestatus = ? WHERE invoiceid = ?', array($settled > 0 ? 'Paid' : 'Credit Invoice', $id));
			} elseif ($balance > 0.004 && in_array($status, array('Paid', 'Credit Invoice'), true)) {
				$adb->pquery('UPDATE vtiger_invoice SET invoicestatus = ? WHERE invoiceid = ?', array('Sent', $id));
			}
		} else {
			$adb->pquery('UPDATE vtiger_purchaseorder SET paid = ?, balance = ? WHERE purchaseorderid = ?', array($settled, $balance, $id));
		}
	}

	/** URL of the payment form for a document, prefilled; null when no payment can be recorded. */
	public static function getRecordPaymentUrl($module, $id) {
		if (self::recordingProblem($module, $id) !== null) {
			return null;
		}
		$document = self::getDocument($module, $id);
		$params = array(
			'module' => 'Payments',
			'view' => 'Edit',
			'related_to' => $id,
			'direction' => self::directionFor($document),
			'amount' => number_format(self::outstanding($document), 2, '.', ''),
			'payment_date' => DateTimeField::convertToUserFormat(date('Y-m-d')),
			'status' => 'Completed',
			'sourceModule' => $module,
			'sourceRecord' => $id,
			'relationOperation' => 'true',
		);
		if (!empty($document['account'])) {
			$params['account_id'] = $document['account'];
		}
		if (!empty($document['vendor'])) {
			$params['vendor_id'] = $document['vendor'];
		}
		return 'index.php?' . http_build_query($params);
	}
}
