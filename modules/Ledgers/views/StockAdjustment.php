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
include_once 'include/utils/StockUtils.php';

/** Form for opening stock and stock adjustments (count differences, write-offs, damage, found stock). */
class Ledgers_StockAdjustment_View extends Ledgers_ReportBase_View {

	public function requiresPermission(\Vtiger_Request $request) {
		return array(array('module_parameter' => 'module', 'action' => 'CreateView'));
	}

	public function process(Vtiger_Request $request) {
		global $adb;
		$products = array();
		$result = $adb->pquery('SELECT p.productid, p.productname, p.productcode, p.qtyinstock,
				(SELECT COUNT(*) FROM vtiger_stock_moves m WHERE m.product_id = p.productid) AS moves
			FROM vtiger_products p INNER JOIN vtiger_crmentity c ON c.crmid = p.productid AND c.deleted = 0 WHERE p.track_stock = 1 ORDER BY p.productname LIMIT 2000');
		while ($row = $adb->fetch_array($result)) {
			$products[$row['productid']] = array('name' => decode_html($row['productname']) . ($row['productcode'] ? ' (' . decode_html($row['productcode']) . ')' : ''), 'old_qty' => (float)$row['qtyinstock'], 'moves' => (int)$row['moves']);
		}
		$ledgers = array();
		$result = $adb->pquery('SELECT l.ledgersid, l.ledger_name, l.ledger_group FROM vtiger_ledgers l INNER JOIN vtiger_crmentity c ON c.crmid = l.ledgersid AND c.deleted = 0 ORDER BY l.ledger_group, l.ledger_name');
		while ($row = $adb->fetch_array($result)) {
			$ledgers[$row['ledgersid']] = decode_html($row['ledger_group']) . ' / ' . decode_html($row['ledger_name']);
		}
		$recent = array();
		$result = $adb->pquery('SELECT a.adjustment_id, a.adjustment_date, a.adjustment_type, a.narration, COUNT(m.move_id) AS lines, SUM(m.value) AS value
			FROM vtiger_stock_adjustments a LEFT JOIN vtiger_stock_moves m ON m.source_module = ? AND m.source_id = a.adjustment_id GROUP BY a.adjustment_id ORDER BY a.adjustment_id DESC LIMIT 10', array('Adjustment'));
		while ($row = $adb->fetch_array($result)) {
			$recent[] = $row;
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('PRODUCTS', $products);
		$viewer->assign('LEDGERS', $ledgers);
		$viewer->assign('RECENT', $recent);
		$viewer->assign('TODAY', date('Y-m-d'));
		$viewer->assign('ERROR', $request->get('error'));
		$viewer->assign('MESSAGE', $request->get('message'));
		$viewer->view('StockAdjustment.tpl', $request->getModule());
	}
}
