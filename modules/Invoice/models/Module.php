<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Invoice_Module_Model extends Inventory_Module_Model {

	/** Adds the recurring invoices and payment reminders screens next to the list actions. */
	public function getModuleBasicLinks() {
		$links = parent::getModuleBasicLinks();
		$links[] = array('linktype' => 'BASIC', 'linklabel' => 'LBL_RECURRING_INVOICES', 'linkurl' => 'index.php?module=Invoice&view=RecurringList', 'linkicon' => 'fa-repeat');
		$links[] = array('linktype' => 'BASIC', 'linklabel' => 'LBL_PAYMENT_REMINDERS', 'linkurl' => 'index.php?module=Invoice&view=Reminders', 'linkicon' => 'fa-bell');
		return $links;
	}
}