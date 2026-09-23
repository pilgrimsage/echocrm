<?php
/*+***********************************************************************************
 * Saves a module's chosen identifier field(s) to vtiger_entityname and rebuilds
 * the display label (vtiger_crmentity.label) of every existing record of that
 * module so old records reflect the new identifier immediately.
 *************************************************************************************/

class Settings_Vtiger_EntityIdentifierSave_Action extends Settings_Vtiger_Basic_Action {

	public function process(Vtiger_Request $request) {
		$moduleName = $request->get('sourceModule');
		$fieldNames = $request->get('fieldNames'); // ordered array of field names
		$response = new Vtiger_Response();

		if (empty($moduleName) || empty($fieldNames) || !is_array($fieldNames)) {
			$response->setError('INVALID_REQUEST', 'Module and at least one field are required');
			$response->emit();
			return;
		}

		$moduleModel = Vtiger_Module_Model::getInstance($moduleName);
		if (!$moduleModel) {
			$response->setError('INVALID_MODULE', 'Invalid module');
			$response->emit();
			return;
		}

		// Validate every chosen field actually belongs to this module.
		$moduleFields = $moduleModel->getFields();
		foreach ($fieldNames as $fieldName) {
			if (!isset($moduleFields[$fieldName])) {
				$response->setError('INVALID_FIELD', "Field $fieldName does not belong to $moduleName");
				$response->emit();
				return;
			}
		}

		$db = PearDatabase::getInstance();
		$fieldNameString = implode(',', $fieldNames);

		$existing = $db->pquery('SELECT 1 FROM vtiger_entityname WHERE tabid=?', array($moduleModel->getId()));
		if ($db->num_rows($existing) > 0) {
			$db->pquery('UPDATE vtiger_entityname SET fieldname=? WHERE tabid=?', array($fieldNameString, $moduleModel->getId()));
		} else {
			$db->pquery('INSERT INTO vtiger_entityname(tabid, modulename, tablename, fieldname, entityidfield, entityidcolumn) VALUES (?,?,?,?,?,?)',
				array($moduleModel->getId(), $moduleName, $moduleModel->basetable, $fieldNameString, $moduleModel->basetableid, $moduleModel->basetableid));
		}

		// The name-fields lookup is cached persistently - clear it so the
		// change takes effect without a full cache flush.
		Vtiger_Cache::delete('EntityField', $moduleName);

		$rebuiltCount = $this->rebuildRecordLabels($moduleModel, $fieldNames);

		$response->setResult(array('success' => true, 'rebuiltCount' => $rebuiltCount));
		$response->emit();
	}

	/**
	 * Recompute and persist vtiger_crmentity.label for every existing record
	 * of the module, using the same composition vtiger already trusts
	 * elsewhere (Vtiger_Functions::computeCRMRecordLabels).
	 */
	private function rebuildRecordLabels($moduleModel, $fieldNames) {
		$db = PearDatabase::getInstance();
		$idColumn = $moduleModel->basetableid;
		$baseTable = $moduleModel->basetable;

		$result = $db->pquery("SELECT $idColumn AS recordid FROM $baseTable", array());
		$allIds = array();
		while ($row = $db->fetch_array($result)) {
			$allIds[] = $row['recordid'];
		}

		if (empty($allIds)) {
			return 0;
		}

		$chunks = array_chunk($allIds, 200);
		$total = 0;
		foreach ($chunks as $idsChunk) {
			$labels = Vtiger_Functions::computeCRMRecordLabels($moduleModel->getName(), $idsChunk);
			foreach ($labels as $crmid => $label) {
				$db->pquery('UPDATE vtiger_crmentity SET label=? WHERE crmid=?', array($label, $crmid));
				$total++;
			}
		}
		return $total;
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
