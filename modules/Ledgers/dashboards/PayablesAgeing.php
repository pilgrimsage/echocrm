<?php
/** Accounting dashboard widget: what is owed to vendors, by how late it is. The numbers come from Vtiger_Dashboard_Widgets. */
include_once 'include/utils/DashboardWidgets.php';

class Ledgers_PayablesAgeing_Dashboard extends Vtiger_IndexAjax_View {
	public function process(Vtiger_Request $request) {
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$viewer = $this->getViewer($request);
		$moduleName = $request->getModule();
		$widget = Vtiger_Widget_Model::getInstanceWithWidgetId($request->get('widgetid'), $currentUser->getId());
		$viewer->assign('WIDGET', $widget);
		$viewer->assign('MODULE_NAME', $moduleName);
		$viewer->assign('DATA', Vtiger_Dashboard_Widgets::ageing(true));
		$viewer->assign('VENDOR', true);
		$viewer->assign('LIST_URL', 'index.php?module=Ledgers&view=Ageing&type=vendor');
		if ($request->get('content')) {
			$viewer->view('dashboards/AgeingContents.tpl', $moduleName);
		} else {
			$viewer->view('dashboards/Ageing.tpl', $moduleName);
		}
	}
}
