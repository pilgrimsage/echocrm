<?php
/**
 * The form for the custom dashboard widget. With no step it returns the form (empty, or filled in
 * when `widgetid` is given to change an existing widget); step=options answers JSON with the
 * filters and the numeric / groupable fields of the chosen module.
 */
class Vtiger_CustomWidgetWizard_View extends Vtiger_Index_View {

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
		if ($request->get('step') === 'options') {
			$module = (string)$request->get('selectedModule');
			$response = new Vtiger_Response();
			if (!Vtiger_Module_Model::getInstance($module) || !vtlib_isModuleActive($module)) {
				$response->setResult(array('filters' => array(), 'numeric' => array(), 'groupable' => array()));
			} else {
				$response->setResult(array('filters' => Vtiger_CustomWidget_Model::filterOptions($module)) + Vtiger_CustomWidget_Model::fieldOptions($module));
			}
			$response->emit();
			return;
		}

		$currentUser = Users_Record_Model::getCurrentUserModel();
		$viewer = $this->getViewer($request);
		$modules = Vtiger_Module_Model::getSearchableModules();
		unset($modules['ModComments']);
		$translated = array();
		foreach ($modules as $name => $model) {
			$translated[$name] = vtranslate($name, $name);
		}
		asort($translated);

		$settings = array();
		$widgetId = (int)$request->get('widgetid');
		if ($widgetId) {
			$widget = Vtiger_Widget_Model::getInstanceWithWidgetId($widgetId, $currentUser->getId());
			$settings = Vtiger_CustomWidget_Model::settingsOf($widget);
		}
		$viewer->assign('MODULE_NAME', $request->getModule());
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('MODULES', $translated);
		// every key is present, so the template needs no checks
		$settings += array('title' => '', 'module' => '', 'filterid' => 0, 'type' => 'number', 'metric' => 'count', 'field' => '', 'groupby' => '', 'limit' => 8);
		$viewer->assign('SETTINGS', $settings);
		$viewer->assign('WIDGET_ID', $widgetId);
		$viewer->view('dashboards/CustomWidgetWizard.tpl', $request->getModule());
	}
}
