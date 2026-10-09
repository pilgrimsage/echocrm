<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

Class SalesOrder_Edit_View extends Inventory_Edit_View {

	public function process(Vtiger_Request $request) {
		// a note started from an invoice / purchase order: refuse when that document cannot take one
		$parentModule = $request->get('invoice_id') ? 'Invoice' : ($request->get('purchaseorder_id') ? 'PurchaseOrder' : null);
		if ($parentModule && !$request->get('record')) {
			include_once 'include/utils/NoteUtils.php';
			$parentId = $request->get($parentModule == 'Invoice' ? 'invoice_id' : 'purchaseorder_id');
			$problem = Vtiger_Note_Utils::creationProblem($parentModule, $parentId);
			if ($problem !== null) {
				throw new AppException($problem);
			}
		}
		parent::process($request);
	}

	/**
	 * Return notes start with what is still open on the document: the quantity already covered by
	 * earlier notes is taken off, and lines with nothing left are dropped. Other reasons (discount,
	 * price correction) keep the document's quantities and are limited by its total on save.
	 */
	protected function limitLineItemsFromParent($relatedProducts, $parentRecordModel) {
		include_once 'include/utils/NoteUtils.php';
		$parentModule = $parentRecordModel->getModuleName();
		if (!in_array($parentModule, array('Invoice', 'PurchaseOrder'))) {
			return $relatedProducts;
		}
		$remaining = Vtiger_Note_Utils::remainingQuantities($parentModule, $parentRecordModel->getId());
		$kept = array();
		$position = 0;
		foreach ($relatedProducts as $i => $line) {
			$productId = $line['hdnProductId' . $i];
			if (!isset($remaining[$productId]) || $remaining[$productId] <= 0.0005) {
				continue;
			}
			// the remaining quantity is shared out over the lines of the same product, in order
			$take = min((float)$line['qty' . $i], $remaining[$productId]);
			$remaining[$productId] -= $take;
			if ($take < (float)$line['qty' . $i]) {
				$line['qty' . $i] = $take;
				foreach (array('productTotal', 'totalAfterDiscount', 'netPrice', 'taxTotal') as $key) {
					unset($line[$key . $i]);
				}
			}
			$kept[++$position] = $this->renumberLine($line, $i, $position);
		}
		if ($kept && isset($relatedProducts[1]['final_details'])) {
			$kept[1]['final_details'] = $relatedProducts[1]['final_details'];
		}
		return $kept ? $kept : $relatedProducts;
	}

	private function renumberLine($line, $from, $to) {
		if ($from == $to) {
			return $line;
		}
		$renumbered = array();
		foreach ($line as $key => $value) {
			$renumbered[preg_replace('/' . $from . '$/', (string)$to, $key)] = $value;
		}
		return $renumbered;
	}

	/**
	 * Created from an invoice (credit note) or a purchase order (debit note): the common fields
	 * (parties, addresses, tax mode, region, currency, line items) were copied; give the note its
	 * own type, subject, date and status.
	 */
	protected function prefillFromParentRecord($recordModel, $parentRecordModel) {
		$parentModule = $parentRecordModel->getModuleName();
		if ($parentModule == 'Invoice') {
			$recordModel->set('note_type', 'Credit Note');
			$recordModel->set('subject', 'Credit Note against ' . $parentRecordModel->get('invoice_no'));
			$recordModel->set('invoice_id', $parentRecordModel->getId());
			$recordModel->set('reason', 'Sales Return');
		} elseif ($parentModule == 'PurchaseOrder') {
			$recordModel->set('note_type', 'Debit Note');
			$recordModel->set('subject', 'Debit Note against ' . $parentRecordModel->get('purchaseorder_no'));
			$recordModel->set('purchaseorder_id', $parentRecordModel->getId());
			$recordModel->set('vendor_id', $parentRecordModel->get('vendor_id'));
			$recordModel->set('reason', 'Purchase Return');
		} else {
			return;
		}
		$recordModel->set('duedate', date('Y-m-d'));
		$recordModel->set('sostatus', 'Created');
	}
}