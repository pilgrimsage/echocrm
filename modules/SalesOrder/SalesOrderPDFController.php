<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
include_once 'include/InventoryPDFController.php';
include_once dirname(__FILE__). '/SalesOrderPDFHeaderViewer.php';
/**
 * PDF of a Credit Note (issued to a customer against an invoice) or a Debit Note (issued to a vendor
 * against a purchase order). Both are kept in the Sales Order module; the note type field decides
 * the title, the party and the reference shown. Same two prints as an Invoice (GST layout with
 * HSN/SAC, tax breakup and amount in words, or the standard layout).
 */
class Vtiger_SalesOrderPDFController extends Vtiger_InventoryPDFController{

	private function isDebitNote() {
		return $this->focusColumnValue('note_type') == 'Debit Note';
	}

	function buildHeaderModelTitle() {
		$title = $this->isDebitNote() ? 'Debit Note' : 'Credit Note';
		return sprintf("%s: %s", getTranslatedString($title, $this->moduleName), $this->focusColumnValue('salesorder_no'));
	}

	function getPartyGstinLabel() {
		return $this->isDebitNote() ? 'Vendor GSTIN' : 'Customer GSTIN';
	}

	function getHeaderViewer() {
		$headerViewer = new SalesOrderPDFHeaderViewer();
		$headerViewer->setModel($this->buildHeaderModel());
		return $headerViewer;
	}

	/** Number and date of the document the note was issued against: array(label, no, date). */
	private function getAgainstInfo() {
		global $adb;
		if ($this->isDebitNote()) {
			$id = $this->focusColumnValue('purchaseorder_id');
			$sql = 'SELECT purchaseorder_no AS no, duedate AS date FROM vtiger_purchaseorder WHERE purchaseorderid = ?';
			$label = 'Purchase Order';
		} else {
			$id = $this->focusColumnValue('invoice_id');
			$sql = 'SELECT invoice_no AS no, invoicedate AS date FROM vtiger_invoice WHERE invoiceid = ?';
			$label = 'Invoice';
		}
		$info = array('label' => $label, 'no' => '', 'date' => '');
		if (empty($id)) {
			return $info;
		}
		$result = $adb->pquery($sql, array($id));
		if ($adb->num_rows($result)) {
			$info['no'] = decode_html($adb->query_result($result, 0, 'no'));
			$info['date'] = $adb->query_result($result, 0, 'date');
		}
		return $info;
	}

	function buildHeaderModelColumnCenter() {
		if ($this->isDebitNote()) {
			$party = array(getTranslatedString('Vendor Name', $this->moduleName) => $this->resolveReferenceLabel($this->focusColumnValue('vendor_id'), 'Vendors'));
		} else {
			$party = array(getTranslatedString('Customer Name', $this->moduleName) => $this->resolveReferenceLabel($this->focusColumnValue('account_id'), 'Accounts'));
		}
		$party[getTranslatedString('Reason', $this->moduleName)] = decode_html($this->focusColumnValue('reason'));
		return array_merge($party, $this->buildGstHeaderRows());
	}

	function buildHeaderModelColumnRight() {
		$billingAddressLabel = getTranslatedString('Billing Address', $this->moduleName);
		$shippingAddressLabel = getTranslatedString('Shipping Address', $this->moduleName);

		$noteDate = $this->focusColumnValue('duedate');
		$against = $this->getAgainstInfo();

		$dates = array(
			getTranslatedString('Note Date', $this->moduleName) => $this->formatDate(!empty($noteDate) ? $noteDate : date("Y-m-d")),
		);
		if ($against['no'] !== '') {
			$dates['Against ' . $against['label']] = $against['no'];
			if (!empty($against['date'])) {
				$dates[$against['label'] . ' Date'] = $this->formatDate($against['date']);
			}
		}
		return array(
			'dates' => $dates,
			$billingAddressLabel => $this->buildHeaderBillingAddress(),
			$shippingAddressLabel => $this->buildHeaderShippingAddress()
		);
	}

	function getWatermarkContent() {
		return $this->focusColumnValue('sostatus');
	}

	function getContentViewer() {
		return $this->isGstLayout() ? $this->getGstContentViewer() : parent::getContentViewer();
	}
}
?>