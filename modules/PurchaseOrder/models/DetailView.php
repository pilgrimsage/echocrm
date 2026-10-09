<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class PurchaseOrder_DetailView_Model extends Inventory_DetailView_Model {

	public function getDetailViewLinks($linkParams) {
		$currentUserModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();

		$linkModelList = parent::getDetailViewLinks($linkParams);
		$recordModel = $this->getRecord();

		// debit notes (purchase side) are kept in the Sales Order module
		$noteModuleModel = Vtiger_Module_Model::getInstance('SalesOrder');
		include_once 'include/utils/NoteUtils.php';
		// only offered when the purchase order is approved / delivered / received and has something left to return
		if ($noteModuleModel && $noteModuleModel->isActive() && $currentUserModel->hasModuleActionPermission($noteModuleModel->getId(), 'CreateView')
				&& Vtiger_Note_Utils::creationProblem('PurchaseOrder', $recordModel->getId()) === null) {
			$debitNoteLink = array(
				'linktype' => 'DETAILVIEW',
				'linklabel' => vtranslate('LBL_CREATE_DEBIT_NOTE', 'PurchaseOrder'),
				'linkurl' => $recordModel->getCreateDebitNoteUrl(),
				'linkicon' => ''
			);
			$linkModelList['DETAILVIEW'][] = Vtiger_Link_Model::getInstanceFromValues($debitNoteLink);
		}
		return $linkModelList;
	}
}
