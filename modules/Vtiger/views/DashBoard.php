<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Vtiger_Dashboard_View extends Vtiger_Index_View {

	protected static $selectable_dashboards;

	public function requiresPermission(\Vtiger_Request $request) {
		$permissions = parent::requiresPermission($request);
		if($request->get('module') != 'Dashboard'){
			$request->set('custom_module', 'Dashboard');
			$permissions[] = array('module_parameter' => 'custom_module', 'action' => 'DetailView');
		}else{
			$permissions[] = array('module_parameter' => 'module', 'action' => 'DetailView');
		}
		return $permissions;
	}
	
	function preProcess(Vtiger_Request $request, $display=true) {
		parent::preProcess($request, false);
		$viewer = $this->getViewer($request);
		$moduleName = $request->getModule();

		$dashBoardModel = Vtiger_DashBoard_Model::getInstance($moduleName);
		//check profile permissions for Dashboards
		$moduleModel = Vtiger_Module_Model::getInstance('Dashboard');
		$userPrivilegesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		$permission = $userPrivilegesModel->hasModulePermission($moduleModel->getId());
		if($permission) {
		// TODO : Need to optimize the widget which are retrieving twice
		$dashboardTabs = $dashBoardModel->getActiveTabs();
		if ($request->get("tabid")) {
			$tabid = $request->get("tabid");
		} else {
			// If no tab, then select first tab of the user
			$tabid = $dashboardTabs[0]["id"];
		}
		$dashBoardModel->set("tabid", $tabid);
		$widgets = $dashBoardModel->getSelectableDashboard();
		self::$selectable_dashboards = $widgets;
		} else {
			$widgets = array();
		}
		$viewer->assign('MODULE_PERMISSION', $permission);
		$viewer->assign('HERO', $moduleName === 'Home' ? $this->getHero() : null);
		$viewer->assign('WIDGETS', $widgets);
		$viewer->assign('MODULE_NAME', $moduleName);
		if($display) {
			$this->preProcessDisplay($request);
		}
	}

	/**
	 * The greeting banner on the Home dashboard: a salutation and up to three counts of the
	 * records this user may see (leads, opportunities, tickets, invoices), each linking to its list.
	 */
	protected function getHero() {
		$user = Users_Record_Model::getCurrentUserModel();
		$hour = (int)date('G');
		$hello = $hour < 12 ? 'LBL_HERO_MORNING' : ($hour < 17 ? 'LBL_HERO_AFTERNOON' : 'LBL_HERO_EVENING');
		$name = trim((string)$user->get('first_name')) !== '' ? $user->get('first_name') : $user->get('user_name');
		$chips = array();
		foreach (array('Leads' => 'LBL_HERO_LEADS', 'Potentials' => 'LBL_HERO_OPPORTUNITIES', 'HelpDesk' => 'LBL_HERO_TICKETS', 'Invoice' => 'LBL_HERO_INVOICES') as $module => $label) {
			if (count($chips) >= 3) {
				break;
			}
			try {
				$moduleModel = Vtiger_Module_Model::getInstance($module);
				if (!$moduleModel || !vtlib_isModuleActive($module) || !Users_Privileges_Model::getCurrentUserPrivilegesModel()->hasModulePermission($moduleModel->getId())) {
					continue;
				}
				$filter = CustomView_Record_Model::getAllFilterByModule($module);
				if (!$filter) {
					continue;
				}
				$result = Vtiger_CustomWidget_Model::compute(array('module' => $module, 'filterid' => (int)$filter->getId(), 'type' => 'number', 'metric' => 'count', 'field' => '', 'groupby' => ''));
				if (isset($result['display'])) {
					$chips[] = array('label' => $label, 'value' => $result['display'], 'url' => 'index.php?module=' . $module . '&view=List&viewname=' . (int)$filter->getId());
				}
			} catch (Exception $e) {
				continue;
			}
		}
		return array('hello' => $hello, 'name' => $name, 'chips' => $chips);
	}

	function preProcessTplName(Vtiger_Request $request) {
		return 'dashboards/DashBoardPreProcess.tpl';
	}

	function process(Vtiger_Request $request) {
		$viewer = $this->getViewer($request);
		$moduleName = $request->getModule();

		$dashBoardModel = Vtiger_DashBoard_Model::getInstance($moduleName);

		//check profile permissions for Dashboards
		$moduleModel = Vtiger_Module_Model::getInstance('Dashboard');
		$userPrivilegesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		$permission = $userPrivilegesModel->hasModulePermission($moduleModel->getId());
		if($permission) {
			// TODO : Need to optimize the widget which are retrieving twice
		   $dashboardTabs = $dashBoardModel->getActiveTabs();
		   if($request->get("tabid")){
			   $tabid = $request->get("tabid");
		   } else {
			   // If no tab, then select first tab of the user
			   $tabid = $dashboardTabs[0]["id"];
		   }
		   $dashBoardModel->set("tabid",$tabid);
			$widgets = $dashBoardModel->getDashboards($moduleName);
		} else {
			return;
		}

		$viewer->assign('MODULE_NAME', $moduleName);
		$viewer->assign('WIDGETS', $widgets);
		$viewer->assign('DASHBOARD_TABS', $dashboardTabs);
		$viewer->assign('DASHBOARD_TABS_LIMIT', $dashBoardModel->dashboardTabLimit);
		$viewer->assign('SELECTED_TAB',$tabid);
        if (self::$selectable_dashboards) {
			$viewer->assign('SELECTABLE_WIDGETS', self::$selectable_dashboards);
		}
		$viewer->assign('CURRENT_USER', Users_Record_Model::getCurrentUserModel());
		$viewer->assign('TABID',$tabid);
		$viewer->view('dashboards/DashBoardContents.tpl', $moduleName);
	}

	public function postProcess(Vtiger_Request $request) {
		parent::postProcess($request);
	}

	/**
	 * Function to get the list of Script models to be included
	 * @param Vtiger_Request $request
	 * @return <Array> - List of Vtiger_JsScript_Model instances
	 */
	public function getHeaderScripts(Vtiger_Request $request) {
		$headerScriptInstances = parent::getHeaderScripts($request);
		$moduleName = $request->getModule();

		$jsFileNames = array(
			'~layouts/'.Vtiger_Viewer::getDefaultLayoutName().'/lib/gridstack/js/gridstack-all.js',
			'~/layouts/'.Vtiger_Viewer::getDefaultLayoutName().'/lib/chartjs/chart.umd.js',
			'modules.Vtiger.resources.vtchart',
			'modules.Vtiger.resources.DashBoard',
			'modules.'.$moduleName.'.resources.DashBoard',
			'modules.Vtiger.resources.dashboards.Widget',
			'~/layouts/'.Vtiger_Viewer::getDefaultLayoutName().'/modules/Vtiger/resources/Detail.js',
			'~/layouts/'.Vtiger_Viewer::getDefaultLayoutName().'/modules/Reports/resources/Detail.js',
			'~/layouts/'.Vtiger_Viewer::getDefaultLayoutName().'/modules/Reports/resources/ChartDetail.js',
			"modules.Emails.resources.MassEdit",
			"modules.Vtiger.resources.CkEditor",
		);

		$jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
		$headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
		return $headerScriptInstances;
	}

	/**
	 * Function to get the list of Css models to be included
	 * @param Vtiger_Request $request
	 * @return <Array> - List of Vtiger_CssScript_Model instances
	 */
	public function getHeaderCss(Vtiger_Request $request) {
		$parentHeaderCssScriptInstances = parent::getHeaderCss($request);

		$headerCss = array(
			'~layouts/'.Vtiger_Viewer::getDefaultLayoutName().'/lib/gridstack/css/gridstack.min.css',
			'~layouts/'.Vtiger_Viewer::getDefaultLayoutName().'/lib/modern/css/dashboard.css',
		);
		$cssScripts = $this->checkAndConvertCssStyles($headerCss);
		$headerCssScriptInstances = array_merge($parentHeaderCssScriptInstances , $cssScripts);
		return $headerCssScriptInstances;
	}
}