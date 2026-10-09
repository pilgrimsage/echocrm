<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

/** "Statement" and "Transfer" buttons on a bank account. */
class BankAccounts_DetailView_Model extends Vtiger_DetailView_Model {

	public function getDetailViewLinks($linkParams) {
		$links = parent::getDetailViewLinks($linkParams);
		$id = $this->getRecord()->getId();
		foreach (array(
			array('LBL_STATEMENT', 'index.php?module=BankAccounts&view=Statement&record=' . $id),
			array('LBL_TRANSFER_MONEY', 'index.php?module=BankAccounts&view=Transfer&from_account=' . $id),
		) as $link) {
			$links['DETAILVIEWBASIC'][] = Vtiger_Link_Model::getInstanceFromValues(array(
				'linktype' => 'DETAILVIEWBASIC', 'linklabel' => vtranslate($link[0], 'BankAccounts'), 'linkurl' => $link[1], 'linkicon' => ''));
		}
		return $links;
	}
}
