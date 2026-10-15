<?php
/** The user-built widget (see Vtiger_CustomWidget_Model): shows one number or a grouped chart. */
class Vtiger_CustomWidget_Dashboard extends Vtiger_IndexAjax_View {
	public function process(Vtiger_Request $request) {
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$viewer = $this->getViewer($request);
		$moduleName = $request->getModule();
		$widget = Vtiger_Widget_Model::getInstanceWithWidgetId($request->get('widgetid'), $currentUser->getId());

		$settings = null;
		$result = array('error' => vtranslate('NO_DATA'));
		try {
			// the stored JSON is checked again on every load: it can be edited, or arrive from the browser
			$settings = Vtiger_CustomWidget_Model::normalize(Vtiger_CustomWidget_Model::settingsOf($widget));
			$result = Vtiger_CustomWidget_Model::compute($settings);
			$widget->set('title', $settings['title'] ?: $widget->get('linklabel'));
		} catch (Exception $e) {
			$result = array('error' => $e->getMessage());
		}

		$viewer->assign('WIDGET', $widget);
		$viewer->assign('MODULE_NAME', $moduleName);
		$viewer->assign('SETTINGS', $settings);
		$viewer->assign('RESULT', $result);
		$viewer->assign('LIST_URL', $settings ? 'index.php?module=' . $settings['module'] . '&view=List&viewname=' . (int)$settings['filterid'] : '');
		$viewer->assign('SETTINGS_JSON', $settings ? Vtiger_Util_Helper::toSafeHTML(Zend_JSON::encode($settings)) : '');
		if ($request->get('content')) {
			$viewer->view('dashboards/CustomWidgetContents.tpl', $moduleName);
		} else {
			$viewer->view('dashboards/CustomWidget.tpl', $moduleName);
		}
	}
}
