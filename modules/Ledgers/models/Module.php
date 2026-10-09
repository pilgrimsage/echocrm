<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

/** Adds "Ledger Statement" to the ledger screens. */
class Ledgers_Module_Model extends Vtiger_Module_Model {

	public function getModuleBasicLinks() {
		$links = parent::getModuleBasicLinks();
		$links[] = array('linktype' => 'BASIC', 'linklabel' => 'LBL_LEDGER_STATEMENT', 'linkurl' => 'index.php?module=Ledgers&view=Statement', 'linkicon' => 'fa-list');
		return $links;
	}
}
