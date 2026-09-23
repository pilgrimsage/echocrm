<?php
/*+***********************************************************************************
 * Read-only lookup of the admin's chosen Tax System (Settings > Inventory >
 * Tax > Tax System: india/us/all), used by Products/Services edit-view JS to
 * decide which tax fields to show.
 *************************************************************************************/

class Vtiger_GetTaxSystem_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array();
	}

	public function process(Vtiger_Request $request) {
		$taxSystem = Vtiger_CompanyDetails_Model::getInstanceById()->get('tax_system');
		$response = new Vtiger_Response();
		$response->setResult(array('taxSystem' => $taxSystem ? $taxSystem : 'all'));
		$response->emit();
	}

}
