<?php
/*+***********************************************************************************
 * Credit / Debit Note rules, checked on every save (form, inline edit, import, webservice):
 * a credit note needs a customer and the invoice it is issued against; a debit note needs a
 * vendor. When the note refers to an invoice or purchase order the rules of Vtiger_Note_Utils
 * apply as well (eligible document, same customer/vendor, quantities and total not above what
 * the document has left). The fields are optional in the field settings because which ones apply depends on
 * the note type.
 *************************************************************************************/

require_once 'include/events/VTEventHandler.inc';
include_once 'include/utils/NoteUtils.php';

class SalesOrderNoteHandler extends VTEventHandler {

	function handleEvent($eventName, $entityData) {
		if ($entityData->getModuleName() != 'SalesOrder') {
			return;
		}
		if ($eventName != 'vtiger.entity.beforesave') {
			$this->refreshParent($entityData);
			return;
		}
		$data = $entityData->getData();
		// only the full form carries all fields; inline edits of other fields must not be blocked
		if (!isset($data['note_type']) || $data['note_type'] === '') {
			$entityData->set('note_type', 'Credit Note');
			$data['note_type'] = 'Credit Note';
		}
		$debit = ($data['note_type'] == 'Debit Note');
		// a note belongs to the cost centre of the invoice / purchase order it is issued against, unless one is chosen
		if (empty($data['cost_centre'])) {
			$parent = $debit ? ($data['purchaseorder_id'] ?? null) : ($data['invoice_id'] ?? null);
			if ($parent) {
				include_once 'include/utils/LedgerUtils.php';
				$inherited = Vtiger_Ledger_Utils::documentCostCentre($debit ? 'PurchaseOrder' : 'Invoice', $parent);
				if ($inherited) {
					$entityData->set('cost_centre', $inherited);
				}
			}
		}
		if ($debit) {
			if (empty($data['vendor_id'])) {
				throw new Exception('A debit note needs a vendor.');
			}
		} else {
			if (empty($data['account_id'])) {
				throw new Exception('A credit note needs a customer (Account Name).');
			}
			if (empty($data['invoice_id'])) {
				throw new Exception('A credit note needs the invoice it is issued against.');
			}
		}

		$parentId = $debit ? ($data['purchaseorder_id'] ?? null) : $data['invoice_id'];
		$partyId = $debit ? ($data['vendor_id'] ?? null) : ($data['account_id'] ?? null);
		$noteId = $entityData->getId() ? (int)$entityData->getId() : 0;
		$lines = $this->lineQuantities();
		$total = ($lines !== null && isset($_REQUEST['total'])) ? (float)$_REQUEST['total'] : null;
		$problem = Vtiger_Note_Utils::saveProblem($data['note_type'], $parentId, $partyId, $lines ?? array(), $total, $data['reason'] ?? '', $noteId);
		if ($problem !== null) {
			throw new Exception($problem);
		}
	}

	/**
	 * A note settles the invoice (credit note) or purchase order (debit note) it was issued against,
	 * like a payment does: after it is saved, deleted or restored, the document's balance and status
	 * are brought up to date.
	 */
	private function refreshParent($entityData) {
		global $adb;
		include_once 'include/utils/PaymentUtils.php';
		$result = $adb->pquery('SELECT note_type, invoiceid, purchaseorderid FROM vtiger_salesorder WHERE salesorderid = ?', array($entityData->getId()));
		if (!$adb->num_rows($result)) {
			return;
		}
		$row = $adb->fetch_array($result);
		if ($row['note_type'] == 'Debit Note' && !empty($row['purchaseorderid'])) {
			Vtiger_Payment_Utils::refreshDocument('PurchaseOrder', $row['purchaseorderid']);
		} elseif ($row['note_type'] != 'Debit Note' && !empty($row['invoiceid'])) {
			Vtiger_Payment_Utils::refreshDocument('Invoice', $row['invoiceid']);
		}
	}

	/**
	 * Quantity per product from the submitted line items (productId => quantity), or null when the
	 * save carries none (inline edit, mass edit, webservice), in which case only the header rules apply.
	 */
	private function lineQuantities() {
		if (!isset($_REQUEST['totalProductCount']) || empty($_REQUEST['hdnProductId1']) && empty($_REQUEST['hdnProductId2'])) {
			return null;
		}
		$lines = array();
		for ($i = 1; $i <= (int)$_REQUEST['totalProductCount']; $i++) {
			if (empty($_REQUEST['hdnProductId' . $i]) || !empty($_REQUEST['deleted' . $i])) {
				continue;
			}
			$productId = (int)$_REQUEST['hdnProductId' . $i];
			$lines[$productId] = ($lines[$productId] ?? 0) + (float)($_REQUEST['qty' . $i] ?? 0);
		}
		return $lines;
	}
}
