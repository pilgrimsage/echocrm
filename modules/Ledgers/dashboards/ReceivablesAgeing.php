<?php
/** Accounting dashboard widget: what customers owe, by how late it is. The numbers come from Vtiger_Dashboard_Widgets. */
include_once 'include/utils/DashboardWidgets.php';

class Ledgers_ReceivablesAgeing_Dashboard extends Vtiger_IndexAjax_View {
	public function process(Vtiger_Request $request) {
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$viewer = $this->getViewer($request);
		$moduleName = $request->getModule();
		$widget = Vtiger_Widget_Model::getInstanceWithWidgetId($request->get('widgetid'), $currentUser->getId());
		$viewer->assign('WIDGET', $widget);
		$viewer->assign('MODULE_NAME', $moduleName);
		$viewer->assign('DATA', Vtiger_Dashboard_Widgets::ageing(false));
		$viewer->assign('VENDOR', false);
		$viewer->assign('LIST_URL', 'index.php?module=Ledgers&view=Ageing');
		if ($request->get('content')) {
			$viewer->view('dashboards/AgeingContents.tpl', $moduleName);
		} else {
			$viewer->view('dashboards/Ageing.tpl', $moduleName);
		}
	}
}
