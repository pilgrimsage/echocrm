<?php
/**
 * The numbers behind the accounting dashboard widgets (modules/Ledgers/dashboards). Each function
 * returns plain arrays with money already formatted in the user's format for the template.
 */
include_once 'include/utils/LedgerUtils.php';
include_once 'include/utils/FinancialYears.php';

class Vtiger_Dashboard_Widgets {

	public static function money($value) {
		$value = (float)$value;
		return ($value < 0 ? '-' : '') . Vtiger_CustomWidget_Model::format(abs($value), true);
	}

	/**
	 * What customers owe (or what is owed to vendors), by how late it is, in the base currency.
	 * Same rules as Ledgers > Ageing: approved documents with a balance, due date or document date.
	 */
	public static function ageing($vendor) {
		global $adb;
		if ($vendor) {
			$from = 'vtiger_purchaseorder d INNER JOIN vtiger_crmentity c ON c.crmid = d.purchaseorderid AND c.deleted = 0';
			$due = "COALESCE(NULLIF(d.duedate, '0000-00-00'), DATE(c.createdtime))";
			$where = "d.postatus IN ('Approved', 'Delivered', 'Received Shipment') AND d.balance > 0.004";
		} else {
			$from = 'vtiger_invoice d INNER JOIN vtiger_crmentity c ON c.crmid = d.invoiceid AND c.deleted = 0';
			$due = "COALESCE(NULLIF(d.duedate, '0000-00-00'), d.invoicedate)";
			$where = "d.invoicestatus IN ('Approved', 'Sent', 'Credit Invoice') AND d.balance > 0.004";
		}
		$today = date('Y-m-d');
		$age = "DATEDIFF(?, $due)";
		$bal = '(d.balance / IF(d.conversion_rate > 0, d.conversion_rate, 1))';
		$result = $adb->pquery("SELECT
				SUM(CASE WHEN $age <= 0 THEN $bal ELSE 0 END) AS b0,
				SUM(CASE WHEN $age BETWEEN 1 AND 30 THEN $bal ELSE 0 END) AS b1,
				SUM(CASE WHEN $age BETWEEN 31 AND 60 THEN $bal ELSE 0 END) AS b2,
				SUM(CASE WHEN $age BETWEEN 61 AND 90 THEN $bal ELSE 0 END) AS b3,
				SUM(CASE WHEN $age > 90 THEN $bal ELSE 0 END) AS b4,
				SUM($bal) AS total, COUNT(*) AS documents
			FROM $from WHERE $where", array($today, $today, $today, $today, $today));
		$row = $adb->fetch_array($result);
		$labels = array('Not yet due', '1-30 days', '31-60 days', '61-90 days', 'Over 90 days');
		$colors = array('#00D3B8', '#7CE8D8', '#F2A65A', '#E4572E', '#8A2D12');
		$total = (float)($row['total'] ?? 0);
		$buckets = array();
		foreach ($labels as $i => $label) {
			$amount = (float)($row['b' . $i] ?? 0);
			$buckets[] = array('label' => $label, 'amount' => $amount, 'display' => self::money($amount), 'color' => $colors[$i],
				'share' => $total > 0 ? round($amount / $total * 100, 2) : 0);
		}
		$overdue = $total - (float)($row['b0'] ?? 0);
		return array('total' => $total, 'total_display' => self::money($total), 'overdue_display' => self::money($overdue),
			'documents' => (int)($row['documents'] ?? 0), 'buckets' => $buckets);
	}

	/** Active bank and cash accounts with their current balance. */
	public static function bankBalances() {
		global $adb;
		$result = $adb->pquery("SELECT b.bankaccountsid AS id, b.account_name AS name, b.account_type AS type, b.current_balance AS balance
			FROM vtiger_bankaccounts b INNER JOIN vtiger_crmentity c ON c.crmid = b.bankaccountsid AND c.deleted = 0
			WHERE b.status = 'Active' ORDER BY b.current_balance DESC", array());
		$rows = array();
		$total = 0.0;
		while ($row = $adb->fetch_array($result)) {
			$balance = (float)$row['balance'];
			$total += $balance;
			$rows[] = array('id' => $row['id'], 'name' => decode_html($row['name']), 'type' => decode_html($row['type']),
				'display' => self::money($balance), 'negative' => $balance < 0);
		}
		return array('rows' => $rows, 'total_display' => self::money($total), 'negative' => $total < 0);
	}

	/** Income, expenses and result for this month and for the financial year to date. */
	public static function profitAndLoss() {
		$today = date('Y-m-d');
		$year = Vtiger_Financial_Years::yearOf($today);
		$periods = array(
			array('label' => date('F Y'), 'from' => date('Y-m-01')),
			array('label' => $year['label'], 'from' => $year['start']),
		);
		foreach ($periods as $i => $period) {
			$income = $expenses = 0.0;
			foreach (Vtiger_Ledger_Utils::ledgerTotals($today, $period['from'], null, false) as $row) {
				$amount = Vtiger_Ledger_Utils::normalBalance($row);
				if ($row['grp'] == 'Income') {
					$income += $amount;
				} elseif ($row['grp'] == 'Expenses') {
					$expenses += $amount;
				}
			}
			$periods[$i] += array('income' => self::money($income), 'expenses' => self::money($expenses),
				'result' => self::money($income - $expenses), 'negative' => ($income - $expenses) < 0);
		}
		return $periods;
	}
}
