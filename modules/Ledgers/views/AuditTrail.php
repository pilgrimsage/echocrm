<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
include_once 'modules/Ledgers/views/ReportBase.php';
include_once 'include/utils/AuditUtils.php';

/** Who created, deleted or reversed which journal entry and when. */
class Ledgers_AuditTrail_View extends Ledgers_ReportBase_View {

	const PER_PAGE = 100;

	public function process(Vtiger_Request $request) {
		$filters = array();
		foreach (array('from', 'to', 'user', 'action', 'source', 'entry') as $name) {
			$value = trim((string)$request->get($name));
			if ($value !== '') {
				$filters[$name] = $value;
			}
		}
		$page = max(1, (int)$request->get('page'));
		list($rows, $count) = Vtiger_Audit_Utils::search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('ENABLED', Vtiger_Audit_Utils::enabled());
		$viewer->assign('F', $filters + array('from' => '', 'to' => '', 'user' => '', 'action' => '', 'source' => '', 'entry' => ''));
		$viewer->assign('USERS', Vtiger_Audit_Utils::users());
		$viewer->assign('ROWS', $rows);
		$viewer->assign('COUNT', $count);
		$viewer->assign('PAGE', $page);
		$viewer->assign('PAGES', max(1, (int)ceil($count / self::PER_PAGE)));
		$viewer->assign('QUERY', http_build_query($filters));
		$viewer->view('AuditTrail.tpl', $request->getModule());
	}
}
