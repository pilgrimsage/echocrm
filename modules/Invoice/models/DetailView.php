<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Invoice_DetailView_Model extends Inventory_DetailView_Model {

	public function getDetailViewLinks($linkParams) {
		$currentUserModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();

		$linkModelList = parent::getDetailViewLinks($linkParams);
		$recordModel = $this->getRecord();

		$purchaseOrderModuleModel = Vtiger_Module_Model::getInstance('PurchaseOrder');
		if ($currentUserModel->hasModuleActionPermission($purchaseOrderModuleModel->getId(), 'CreateView')) {
			$basicActionLink = array(
				'linktype' => 'DETAILVIEW',
				'linklabel' => vtranslate('LBL_GENERATE') . ' ' . vtranslate($purchaseOrderModuleModel->getSingularLabelKey(), 'PurchaseOrder'),
				'linkurl' => $recordModel->getCreatePurchaseOrderUrl(),
				'linkicon' => ''
			);
			$linkModelList['DETAILVIEW'][] = Vtiger_Link_Model::getInstanceFromValues($basicActionLink);
		}
		$creditNoteModuleModel = Vtiger_Module_Model::getInstance('SalesOrder'); // credit and debit notes live in the Sales Order module
		include_once 'include/utils/NoteUtils.php';
		// only offered when the invoice is in a status that can be corrected and has something left to credit
		if ($creditNoteModuleModel && $creditNoteModuleModel->isActive() && $currentUserModel->hasModuleActionPermission($creditNoteModuleModel->getId(), 'CreateView')
				&& Vtiger_Note_Utils::creationProblem('Invoice', $recordModel->getId()) === null) {
			$creditNoteLink = array(
				'linktype' => 'DETAILVIEW',
				'linklabel' => vtranslate('LBL_CREATE_CREDIT_NOTE', 'Invoice'),
				'linkurl' => $recordModel->getCreateCreditNoteUrl(),
				'linkicon' => ''
			);
			$linkModelList['DETAILVIEW'][] = Vtiger_Link_Model::getInstanceFromValues($creditNoteLink);
		}
		if (Users_Privileges_Model::isPermitted('Invoice', 'EditView')) {
			$linkModelList['DETAILVIEW'][] = Vtiger_Link_Model::getInstanceFromValues(array(
				'linktype' => 'DETAILVIEW', 'linklabel' => vtranslate('LBL_MAKE_RECURRING', 'Invoice'),
				'linkurl' => 'index.php?module=Invoice&view=RecurringSetup&record=' . $recordModel->getId(), 'linkicon' => ''));
		}
		return $linkModelList;
	}
}
