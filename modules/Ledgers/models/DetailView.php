<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Ledgers_DetailView_Model extends Vtiger_DetailView_Model {

	public function getDetailViewLinks($linkParams) {
		$links = parent::getDetailViewLinks($linkParams);
		$links['DETAILVIEWBASIC'][] = Vtiger_Link_Model::getInstanceFromValues(array(
			'linktype' => 'DETAILVIEWBASIC', 'linklabel' => vtranslate('LBL_LEDGER_STATEMENT', 'Ledgers'),
			'linkurl' => 'index.php?module=Ledgers&view=Statement&record=' . $this->getRecord()->getId(), 'linkicon' => ''));
		return $links;
	}
}
