<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

include_once 'include/InventoryPDFController.php';

/**
 * PDF of a Credit Note. Same two prints as an Invoice (GST layout with HSN/SAC, tax breakup and
 * amount in words, or the standard layout, chosen by the company's tax system), with the credit
 * note's own number and date, the reason, and a reference to the original invoice.
 */
class Vtiger_CreditNotePDFController extends Vtiger_InventoryPDFController {

	function buildHeaderModelTitle() {
		return sprintf("%s: %s", getTranslatedString('SINGLE_CreditNote', $this->moduleName), $this->focusColumnValue('creditnote_no'));
	}

	function getWatermarkContent() {
		return $this->focusColumnValue('creditnotestatus');
	}

	function getContentViewer() {
		return $this->isGstLayout() ? $this->getGstContentViewer() : parent::getContentViewer();
	}

	/** Number and date of the invoice this credit note was issued against ('' when unknown). */
	private function getOriginalInvoiceInfo() {
		global $adb;
		$invoiceId = $this->focusColumnValue('invoice_id');
		if (empty($invoiceId)) {
			return array('no' => '', 'date' => '');
		}
		$result = $adb->pquery('SELECT invoice_no, invoicedate FROM vtiger_invoice WHERE invoiceid = ?', array($invoiceId));
		if (!$adb->num_rows($result)) {
			return array('no' => '', 'date' => '');
		}
		return array(
			'no' => decode_html($adb->query_result($result, 0, 'invoice_no')),
			'date' => $adb->query_result($result, 0, 'invoicedate'),
		);
	}

	/**
	 * The header has room for about four boxes in the middle column (more would run into the item
	 * table), so the invoice reference goes in the right column next to the date, and the contact is
	 * left off.
	 */
	function buildHeaderModelColumnCenter() {
		$customerName = $this->resolveReferenceLabel($this->focusColumnValue('account_id'), 'Accounts');

		$modelColumnCenter = array(
			getTranslatedString('Customer Name', $this->moduleName) => $customerName,
			getTranslatedString('Reason', $this->moduleName) => decode_html($this->focusColumnValue('reason')),
		);
		return array_merge($modelColumnCenter, $this->buildGstHeaderRows());
	}

	function buildHeaderModelColumnRight() {
		$billingAddressLabel = getTranslatedString('Billing Address', $this->moduleName);
		$shippingAddressLabel = getTranslatedString('Shipping Address', $this->moduleName);

		$creditNoteDate = $this->focusColumnValue('creditnotedate');
		$invoice = $this->getOriginalInvoiceInfo();

		$dates = array(
			getTranslatedString('Credit Note Date', $this->moduleName) => $this->formatDate(!empty($creditNoteDate) ? $creditNoteDate : date("Y-m-d")),
		);
		if ($invoice['no'] !== '') {
			$dates['Against Invoice'] = $invoice['no'];
			if (!empty($invoice['date'])) {
				$dates['Invoice Date'] = $this->formatDate($invoice['date']);
			}
		}
		return array(
			'dates' => $dates,
			$billingAddressLabel => $this->buildHeaderBillingAddress(),
			$shippingAddressLabel => $this->buildHeaderShippingAddress()
		);
	}
}
