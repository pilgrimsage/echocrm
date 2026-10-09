<?php
/**
 * Rules for credit and debit notes (kept in the Sales Order module).
 *
 * A credit note is issued against an Invoice, a debit note against a Purchase Order. The
 * restrictions below are used both to decide whether the "Create ... Note" button is offered and,
 * on save, to refuse a note that would credit more than was invoiced.
 */
class Vtiger_Note_Utils {

	/** Parent module => array(table, id column, number column, total column, status column, party column, allowed statuses). */
	private static function parentInfo($module) {
		$info = array(
			'Invoice' => array('vtiger_invoice', 'invoiceid', 'invoice_no', 'total', 'invoicestatus', 'accountid',
				array('Approved', 'Sent', 'Credit Invoice', 'Paid')),
			'PurchaseOrder' => array('vtiger_purchaseorder', 'purchaseorderid', 'purchaseorder_no', 'total', 'postatus', 'vendorid',
				array('Approved', 'Delivered', 'Received Shipment')),
		);
		return isset($info[$module]) ? $info[$module] : null;
	}

	private static function noteType($parentModule) {
		return $parentModule == 'Invoice' ? 'Credit Note' : 'Debit Note';
	}

	/** Column of vtiger_salesorder that holds the reference to the parent. */
	private static function noteColumn($parentModule) {
		return $parentModule == 'Invoice' ? 'invoiceid' : 'purchaseorderid';
	}

	/**
	 * Why a note cannot be created against this record, or null when it can: the record must
	 * exist, not be deleted, be in a status that is final enough to correct, and still have
	 * something left to credit.
	 */
	public static function creationProblem($parentModule, $parentId) {
		global $adb;
		$info = self::parentInfo($parentModule);
		if (!$info || empty($parentId)) {
			return 'Unknown document.';
		}
		list($table, $idColumn, , , $statusColumn, , $allowed) = $info;
		$result = $adb->pquery("SELECT p.$statusColumn AS status, c.deleted FROM $table p
			INNER JOIN vtiger_crmentity c ON c.crmid = p.$idColumn WHERE p.$idColumn = ?", array($parentId));
		if (!$adb->num_rows($result) || $adb->query_result($result, 0, 'deleted')) {
			return 'The document does not exist.';
		}
		$status = decode_html($adb->query_result($result, 0, 'status'));
		if (!in_array($status, $allowed, true)) {
			$noun = $parentModule == 'Invoice' ? 'an invoice' : 'a purchase order';
			return "A note can only be issued against $noun that is " . implode(', ', $allowed) . " (this one is '$status').";
		}
		if (!self::hasRemainingQuantity($parentModule, $parentId)) {
			return 'Everything on this document is already covered by notes.';
		}
		return null;
	}

	/**
	 * Quantity already returned by active (not cancelled) notes, per product id. Only return notes
	 * use up quantity; notes for a discount or price correction are limited by the total instead.
	 */
	public static function notedQuantities($parentModule, $parentId, $excludeNoteId = 0) {
		global $adb;
		$column = self::noteColumn($parentModule);
		$result = $adb->pquery("SELECT l.productid, SUM(l.quantity) AS qty
			FROM vtiger_inventoryproductrel l
			INNER JOIN vtiger_salesorder n ON n.salesorderid = l.id
			INNER JOIN vtiger_crmentity c ON c.crmid = n.salesorderid AND c.deleted = 0
			WHERE n.$column = ? AND n.note_type = '" . self::noteType($parentModule) . "' AND n.salesorderid != ? AND (n.sostatus IS NULL OR n.sostatus != 'Cancelled')
			AND n.reason IN ('Sales Return', 'Purchase Return')
			GROUP BY l.productid", array($parentId, (int)$excludeNoteId));
		$noted = array();
		while ($row = $adb->fetch_array($result)) {
			$noted[$row['productid']] = (float)$row['qty'];
		}
		return $noted;
	}

	/** Quantity on the parent document, per product id. */
	public static function parentQuantities($parentModule, $parentId) {
		global $adb;
		$result = $adb->pquery('SELECT productid, SUM(quantity) AS qty FROM vtiger_inventoryproductrel WHERE id = ? GROUP BY productid', array($parentId));
		$quantities = array();
		while ($row = $adb->fetch_array($result)) {
			$quantities[$row['productid']] = (float)$row['qty'];
		}
		return $quantities;
	}

	/** Quantity still open for a note, per product id (parent quantity minus what notes cover). */
	public static function remainingQuantities($parentModule, $parentId, $excludeNoteId = 0) {
		$noted = self::notedQuantities($parentModule, $parentId, $excludeNoteId);
		$remaining = array();
		foreach (self::parentQuantities($parentModule, $parentId) as $productId => $qty) {
			$remaining[$productId] = max(0, $qty - (isset($noted[$productId]) ? $noted[$productId] : 0));
		}
		return $remaining;
	}

	private static function hasRemainingQuantity($parentModule, $parentId) {
		foreach (self::remainingQuantities($parentModule, $parentId) as $qty) {
			if ($qty > 0.0005) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Problem with a note about to be saved, or null. $lines is array(productId => quantity) as
	 * entered; $total is the note's grand total (null when not known, e.g. a webservice save).
	 */
	public static function saveProblem($noteType, $parentId, $partyId, $lines, $total, $reason, $noteId = 0) {
		global $adb;
		$parentModule = $noteType == 'Debit Note' ? 'PurchaseOrder' : 'Invoice';
		if (empty($parentId)) {
			return null; // a note not tied to a document is allowed; the type rules are checked by the handler
		}
		$info = self::parentInfo($parentModule);
		list($table, $idColumn, $numberColumn, $totalColumn, , $partyColumn) = $info;

		$problem = null;
		$existing = $adb->pquery('SELECT 1 FROM vtiger_salesorder WHERE salesorderid = ? AND ' . self::noteColumn($parentModule) . ' = ?', array((int)$noteId, $parentId));
		if (!$adb->num_rows($existing)) {
			// new note, or the note was moved to another document: the document must be eligible
			$problem = self::creationProblem($parentModule, $parentId);
			if ($problem !== null) {
				return $problem;
			}
		}

		$result = $adb->pquery("SELECT $numberColumn AS no, $totalColumn AS total, $partyColumn AS party FROM $table WHERE $idColumn = ?", array($parentId));
		if (!$adb->num_rows($result)) {
			return 'The document does not exist.';
		}
		$parentNo = decode_html($adb->query_result($result, 0, 'no'));
		$parentTotal = (float)$adb->query_result($result, 0, 'total');
		$parentParty = $adb->query_result($result, 0, 'party');
		$label = $parentModule == 'Invoice' ? 'invoice' : 'purchase order';

		if (!empty($partyId) && !empty($parentParty) && $partyId != $parentParty) {
			return ucfirst($noteType == 'Debit Note' ? 'the vendor' : 'the customer') . " must be the same as on $label $parentNo.";
		}

		$remaining = self::remainingQuantities($parentModule, $parentId, $noteId);
		$isReturn = in_array($reason, array('Sales Return', 'Purchase Return'), true);
		$productNames = array();
		foreach ($lines as $productId => $qty) {
			if (!isset($remaining[$productId])) {
				return "An item on this note is not on $label $parentNo.";
			}
			if ($isReturn && $qty - $remaining[$productId] > 0.0005) {
				if (!isset($productNames[$productId])) {
					$productNames[$productId] = decode_html(getEntityName('Products', array($productId))[$productId] ?? '');
					if ($productNames[$productId] === '') {
						$productNames[$productId] = decode_html(getEntityName('Services', array($productId))[$productId] ?? "item $productId");
					}
				}
				return sprintf("'%s': quantity %s is more than the %s still open on %s %s.",
					$productNames[$productId], rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.'),
					rtrim(rtrim(number_format($remaining[$productId], 3, '.', ''), '0'), '.'), $label, $parentNo);
			}
		}

		if ($total !== null) {
			$column = self::noteColumn($parentModule);
			$sum = $adb->pquery("SELECT SUM(n.total) AS t FROM vtiger_salesorder n
				INNER JOIN vtiger_crmentity c ON c.crmid = n.salesorderid AND c.deleted = 0
				WHERE n.$column = ? AND n.note_type = '" . self::noteType($parentModule) . "' AND n.salesorderid != ? AND (n.sostatus IS NULL OR n.sostatus != 'Cancelled')", array($parentId, (int)$noteId));
			$already = (float)$adb->query_result($sum, 0, 't');
			if ($already + (float)$total - $parentTotal > 0.01) {
				return sprintf("The notes against %s %s would add up to %s, more than its total of %s.", $label, $parentNo,
					number_format($already + (float)$total, 2, '.', ''), number_format($parentTotal, 2, '.', ''));
			}
		}
		return null;
	}
}
