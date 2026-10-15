<?php
/** Accounting dashboard widget: income, expenses and result this month and this year. The numbers come from Vtiger_Dashboard_Widgets. */
include_once 'include/utils/DashboardWidgets.php';

class Ledgers_ProfitAndLoss_Dashboard extends Vtiger_IndexAjax_View {
	public function process(Vtiger_Request $request) {
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$viewer = $this->getViewer($request);
		$moduleName = $request->getModule();
		$widget = Vtiger_Widget_Model::getInstanceWithWidgetId($request->get('widgetid'), $currentUser->getId());
		$viewer->assign('WIDGET', $widget);
		$viewer->assign('MODULE_NAME', $moduleName);
		$viewer->assign('PERIODS', Vtiger_Dashboard_Widgets::profitAndLoss());
		$viewer->assign('LIST_URL', 'index.php?module=Ledgers&view=ProfitLoss');
		if ($request->get('content')) {
			$viewer->view('dashboards/ProfitAndLossContents.tpl', $moduleName);
		} else {
			$viewer->view('dashboards/ProfitAndLoss.tpl', $moduleName);
		}
	}
}
