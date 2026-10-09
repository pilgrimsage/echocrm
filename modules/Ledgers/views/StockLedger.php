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

/** The stock ledger of one product: every move with quantity, cost, value and the running balance and average cost. */
class Ledgers_StockLedger_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		global $adb;
		$productId = (int)$request->get('product');
		$query = trim((string)$request->get('q'));
		$from = $this->dateParam($request, 'from', date('Y-04-01', strtotime((int)date('n') >= 4 ? 'today' : '-1 year')));
		$to = $this->dateParam($request, 'to', date('Y-m-d'));
		$matches = array();
		if (!$productId && $query !== '') {
			$result = $adb->pquery('SELECT p.productid AS id, p.productname AS name, p.productcode AS code FROM vtiger_products p INNER JOIN vtiger_crmentity c ON c.crmid = p.productid AND c.deleted = 0
				WHERE p.productname LIKE ? OR p.productcode LIKE ? ORDER BY p.productname LIMIT 30', array($query . '%', $query . '%'));
			while ($row = $adb->fetch_array($result)) {
				$matches[] = array('id' => $row['id'], 'name' => decode_html($row['name']) . ($row['code'] ? ' (' . decode_html($row['code']) . ')' : ''));
			}
		}
		$name = '';
		$book = null;
		if ($productId) {
			$nameResult = $adb->pquery('SELECT productname FROM vtiger_products WHERE productid = ?', array($productId));
			$name = $adb->num_rows($nameResult) ? decode_html($adb->query_result($nameResult, 0, 0)) : '';
			$book = Vtiger_Stock_Utils::ledger($productId, $from, $to);
			foreach ($book['rows'] as &$row) {
				$row['display_date'] = Vtiger_Date_UIType::getDisplayDateValue($row['move_date']);
				$row['url'] = self::sourceUrl($row['source_module'], $row['source_id']) ?: ($row['source_module'] == 'Adjustment' ? 'index.php?module=Ledgers&view=StockAdjustment' : null);
			}
			unset($row);
		}
		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('PRODUCT_ID', $productId);
		$viewer->assign('PRODUCT_NAME', $name);
		$viewer->assign('Q', $query);
		$viewer->assign('MATCHES', $matches);
		$viewer->assign('FROM', $from);
		$viewer->assign('TO', $to);
		$viewer->assign('BOOK', $book);
		$viewer->view('StockLedger.tpl', $request->getModule());
	}
}
