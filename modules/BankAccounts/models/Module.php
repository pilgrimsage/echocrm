<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

/** Adds "Transfer Money" and "Statement" to the bank account screens. */
class BankAccounts_Module_Model extends Vtiger_Module_Model {

	public function getModuleBasicLinks() {
		$links = parent::getModuleBasicLinks();
		if (Users_Privileges_Model::isPermitted($this->getName(), 'CreateView')) {
			$links[] = array('linktype' => 'BASIC', 'linklabel' => 'LBL_TRANSFER_MONEY', 'linkurl' => 'index.php?module=BankAccounts&view=Transfer', 'linkicon' => 'fa-exchange');
		}
		$links[] = array('linktype' => 'BASIC', 'linklabel' => 'LBL_STATEMENT', 'linkurl' => 'index.php?module=BankAccounts&view=Statement', 'linkicon' => 'fa-list');
		return $links;
	}
}
