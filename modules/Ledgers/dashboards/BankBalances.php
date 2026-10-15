<?php
/** Accounting dashboard widget: bank and cash balances. The numbers come from Vtiger_Dashboard_Widgets. */
include_once 'include/utils/DashboardWidgets.php';

class Ledgers_BankBalances_Dashboard extends Vtiger_IndexAjax_View {
	public function process(Vtiger_Request $request) {
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$viewer = $this->getViewer($request);
		$moduleName = $request->getModule();
		$widget = Vtiger_Widget_Model::getInstanceWithWidgetId($request->get('widgetid'), $currentUser->getId());
		$viewer->assign('WIDGET', $widget);
		$viewer->assign('MODULE_NAME', $moduleName);
		$viewer->assign('DATA', Vtiger_Dashboard_Widgets::bankBalances());
		$viewer->assign('LIST_URL', 'index.php?module=BankAccounts&view=List');
		if ($request->get('content')) {
			$viewer->view('dashboards/BankBalancesContents.tpl', $moduleName);
		} else {
			$viewer->view('dashboards/BankBalances.tpl', $moduleName);
		}
	}
}
