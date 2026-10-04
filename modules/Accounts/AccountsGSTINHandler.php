<?php
/*+***********************************************************************************
 * Validates and normalizes the GSTIN on an Account before it is saved, whichever way the
 * record is saved (full form, quick create, inline edit, import, webservice). An invalid
 * number stops the save with a message; a valid one is stored upper-case without spaces.
 *************************************************************************************/

require_once 'include/events/VTEventHandler.inc';
include_once 'include/utils/GSTUtils.php';

class AccountsGSTINHandler extends VTEventHandler {

	function handleEvent($eventName, $entityData) {
		if ($eventName != 'vtiger.entity.beforesave' || $entityData->getModuleName() != 'Accounts') {
			return;
		}
		// column_fields is a TrackableObject (ArrayAccess), not an array: use isset(), not array_key_exists()
		$data = $entityData->getData();
		$gstin = isset($data['gstin']) ? $data['gstin'] : null;
		if ($gstin === null || trim($gstin) === '') {
			return;
		}

		$problem = Vtiger_GST_Utils::gstinProblem($gstin);
		if ($problem !== null) {
			throw new Exception($problem);
		}
		$entityData->set('gstin', Vtiger_GST_Utils::normalizeGSTIN($gstin));
	}
}
