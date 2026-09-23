<?php
/*+***********************************************************************************
 * Settings page to view/change which field(s) compose a module's record
 * name/identifier (supports composite identifiers, e.g. "firstname lastname").
 *************************************************************************************/

class Settings_Vtiger_EntityIdentifierIndex_View extends Settings_Vtiger_Index_View {

	public function __construct() {
		parent::__construct();
		$this->exposeMethod('getModuleFields');
	}

	public function process(Vtiger_Request $request) {
		$mode = $request->getMode();
		if (!empty($mode)) {
			echo $this->invokeExposedMethod($mode, $request);
			return;
		}

		$qualifiedModuleName = $request->getModule(false);
		$viewer = $this->getViewer($request);
		$viewer->assign('QUALIFIED_MODULE', $qualifiedModuleName);
		$viewer->assign('ENTITY_MODULES', Vtiger_Module_Model::getEntityModules());
		$viewer->view('EntityIdentifierIndex.tpl', $qualifiedModuleName);
	}

	/**
	 * Candidate identifier field types - kept to simple, short, human-readable
	 * values. Reference/multipicklist/date/boolean/image fields etc are
	 * excluded since they don't make sense (or don't render as plain text)
	 * in a record's display name.
	 */
	private static $allowedDataTypes = array('string', 'email', 'url', 'integer', 'picklist');

	public function getModuleFields(Vtiger_Request $request) {
		$moduleName = $request->get('sourceModule');
		$moduleModel = Vtiger_Module_Model::getInstance($moduleName);

		$fieldsList = array();
		foreach ($moduleModel->getFields() as $fieldName => $fieldModel) {
			if (!in_array($fieldModel->getFieldDataType(), self::$allowedDataTypes)) {
				continue;
			}
			$fieldsList[] = array(
				'name' => $fieldName,
				'label' => vtranslate($fieldModel->get('label'), $moduleName)
			);
		}

		$db = PearDatabase::getInstance();
		$result = $db->pquery('SELECT fieldname FROM vtiger_entityname WHERE tabid=?', array($moduleModel->getId()));
		$currentFields = array();
		if ($db->num_rows($result) > 0) {
			$currentFields = explode(',', $db->query_result($result, 0, 'fieldname'));
		}

		$response = new Vtiger_Response();
		$response->setResult(array('fields' => $fieldsList, 'currentFields' => $currentFields));
		$response->emit();
	}

	function getPageTitle(Vtiger_Request $request) {
		$qualifiedModuleName = $request->getModule(false);
		return vtranslate('LBL_ENTITY_IDENTIFIER', $qualifiedModuleName);
	}

	public function getHeaderScripts(Vtiger_Request $request) {
		$headerScriptInstances = parent::getHeaderScripts($request);
		$moduleName = $request->getModule();

		$jsFileNames = array(
			"modules.Settings.$moduleName.resources.EntityIdentifier"
		);

		$jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
		$headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
		return $headerScriptInstances;
	}

	public function getHeaderCss(Vtiger_Request $request) {
		$headerCssInstances = parent::getHeaderCss($request);

		$cssFileNames = array(
			'~layouts/'.Vtiger_Viewer::getDefaultLayoutName().'/lib/modern/css/entityidentifier.css'
		);
		$cssInstances = $this->checkAndConvertCssStyles($cssFileNames);
		$headerCssInstances = array_merge($headerCssInstances, $cssInstances);

		return $headerCssInstances;
	}

}
