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

/**
 * Who owes what and for how long: open invoices (customers) or purchase orders (vendors) grouped by
 * party and by how far past due they are, from the balance each document keeps (total less payments
 * and returns).
 */
class Ledgers_Ageing_View extends Ledgers_ReportBase_View {

	public function process(Vtiger_Request $request) {
		global $adb;
		$vendor = $request->get('type') == 'vendor';
		$today = $this->dateParam($request, 'to', date('Y-m-d'));
		if ($vendor) {
			$from = "vtiger_purchaseorder d INNER JOIN vtiger_crmentity c ON c.crmid = d.purchaseorderid AND c.deleted = 0 INNER JOIN vtiger_vendor p ON p.vendorid = d.vendorid";
			$party = 'p.vendorid';
			$name = 'p.vendorname';
			$due = "COALESCE(NULLIF(d.duedate, '0000-00-00'), DATE(c.createdtime))";
			$where = "d.postatus IN ('Approved', 'Delivered', 'Received Shipment') AND d.balance > 0.004";
		} else {
			$from = "vtiger_invoice d INNER JOIN vtiger_crmentity c ON c.crmid = d.invoiceid AND c.deleted = 0 INNER JOIN vtiger_account p ON p.accountid = d.accountid";
			$party = 'p.accountid';
			$name = 'p.accountname';
			$due = "COALESCE(NULLIF(d.duedate, '0000-00-00'), d.invoicedate)";
			$where = "d.invoicestatus IN ('Approved', 'Sent', 'Credit Invoice') AND d.balance > 0.004";
		}
		$age = "DATEDIFF(?, $due)";
		$buckets = "SUM(CASE WHEN $age <= 0 THEN d.balance ELSE 0 END) AS current_due,
			SUM(CASE WHEN $age BETWEEN 1 AND 30 THEN d.balance ELSE 0 END) AS d30,
			SUM(CASE WHEN $age BETWEEN 31 AND 60 THEN d.balance ELSE 0 END) AS d60,
			SUM(CASE WHEN $age BETWEEN 61 AND 90 THEN d.balance ELSE 0 END) AS d90,
			SUM(CASE WHEN $age > 90 THEN d.balance ELSE 0 END) AS d90plus,
			SUM(d.balance) AS total, COUNT(*) AS documents";
		$params = array($today, $today, $today, $today, $today);
		$result = $adb->pquery("SELECT $party AS party, $name AS name, $buckets FROM $from WHERE $where GROUP BY $party, $name ORDER BY total DESC LIMIT 200", $params);
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$row['name'] = decode_html($row['name']);
			$rows[] = $row;
		}
		$totals = $adb->pquery("SELECT $buckets FROM $from WHERE $where", $params);
		$grand = $adb->num_rows($totals) ? $adb->fetch_array($totals) : array();

		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $request->getModule());
		$viewer->assign('TYPE', $vendor ? 'vendor' : 'customer');
		$viewer->assign('TO', $today);
		$viewer->assign('ROWS', $rows);
		$viewer->assign('GRAND', $grand);
		$viewer->view('Ageing.tpl', $request->getModule());
	}
}
