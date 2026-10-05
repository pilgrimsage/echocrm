<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

Class CreditNote_Edit_View extends Inventory_Edit_View {

	public function process(Vtiger_Request $request) {
		parent::process($request);
	}

	/**
	 * Created from an invoice: the common fields (customer, contact, addresses, tax mode, region,
	 * currency, line items) were copied; give the credit note its own subject, date and status.
	 */
	protected function prefillFromParentRecord($recordModel, $parentRecordModel) {
		if ($parentRecordModel->getModuleName() != 'Invoice') {
			return;
		}
		$recordModel->set('subject', 'Credit Note against ' . $parentRecordModel->get('invoice_no'));
		$recordModel->set('creditnotedate', date('Y-m-d'));
		$recordModel->set('creditnotestatus', 'Created');
		$recordModel->set('invoice_id', $parentRecordModel->getId());
	}
}