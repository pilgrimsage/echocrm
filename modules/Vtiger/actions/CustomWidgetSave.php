<?php
/** Checks the settings of a custom widget and, for an existing one of the current user's, saves them. */
class Vtiger_CustomWidgetSave_Action extends Vtiger_Action_Controller {

	public function requiresPermission(Vtiger_Request $request) {
		$permissions = parent::requiresPermission($request);
		if ($request->get('module') != 'Dashboard') {
			$request->set('custom_module', 'Dashboard');
			$permissions[] = array('module_parameter' => 'custom_module', 'action' => 'DetailView');
		} else {
			$permissions[] = array('module_parameter' => 'module', 'action' => 'DetailView');
		}
		return $permissions;
	}

	public function process(Vtiger_Request $request) {
		global $adb;
		$response = new Vtiger_Response();
		$userId = Users_Record_Model::getCurrentUserModel()->getId();
		try {
			// the form's settings travel in `widget`: the request's own `module` is the route
			$settings = $request->get('widget');
			$data = Vtiger_CustomWidget_Model::normalize(is_array($settings) ? $settings : array());
			$widgetId = (int)$request->get('widgetid');
			if (!$widgetId) {
				// a new widget: only check the settings, the widget row is made when it is first shown
				$response->setResult(array('success' => true, 'data' => $data));
				$response->emit();
				return;
			}
			$owned = $adb->pquery('SELECT 1 FROM vtiger_module_dashboard_widgets WHERE id = ? AND userid = ?', array($widgetId, $userId));
			if (!$adb->num_rows($owned)) {
				throw new Exception(vtranslate('LBL_PERMISSION_DENIED'));
			}
			$adb->pquery('UPDATE vtiger_module_dashboard_widgets SET filterid = ?, title = ?, data = ? WHERE id = ? AND userid = ?',
				array($data['filterid'], $data['title'], Zend_Json::encode($data), $widgetId, $userId));
			$response->setResult(array('success' => true, 'data' => $data));
		} catch (Exception $e) {
			$response->setError($e->getCode() ?: 1, $e->getMessage());
		}
		$response->emit();
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}
}
