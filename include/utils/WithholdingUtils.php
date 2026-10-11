<?php
/**
 * Tax withheld at source (TDS and its equivalents) on payments.
 *
 * Sections (code, description, rate) are kept in Ledgers > Withholding Sections. A payment can carry a section and
 * the amount withheld (worked out as rate x the taxable part of the payment, tax excluded, unless typed in):
 *   - paying a vendor: the payable is cleared in full, the bank pays the net, the withheld part is owed to the
 *     government (ledger TDS Payable) until it is remitted with a bank payment to that ledger;
 *   - receiving from a customer who withheld: the receivable is cleared in full, the bank receives the net, and the
 *     withheld part is a claim on the government (ledger TDS Receivable) to set against the certificate.
 * The register lists the withholdings of a period by section and party.
 */
class Vtiger_Withholding_Utils {

	public static function enabled() {
		global $adb;
		static $on = null;
		if ($on === null) {
			$result = $adb->pquery("SHOW TABLES LIKE 'vtiger_tds_sections'");
			$on = $adb->num_rows($result) > 0 && in_array('tds_amount', $adb->getColumnNames('vtiger_payments'));
		}
		return $on;
	}

	public static function sections($activeOnly = false) {
		global $adb;
		$result = $adb->pquery('SELECT * FROM vtiger_tds_sections' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY code');
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$row['description'] = decode_html($row['description']);
			$rows[$row['code']] = $row;
		}
		return $rows;
	}

	public static function saveSection($id, $code, $description, $rate, $active) {
		global $adb;
		$code = trim($code);
		if ($code === '' || mb_strlen($code) > 20) {
			throw new Exception('Enter a section code of up to 20 characters.');
		}
		if ($rate < 0 || $rate > 100) {
			throw new Exception('The rate must be between 0 and 100 percent.');
		}
		$clash = $adb->pquery('SELECT section_id FROM vtiger_tds_sections WHERE code = ? AND section_id <> ?', array($code, (int)$id));
		if ($adb->num_rows($clash)) {
			throw new Exception('A section with this code already exists.');
		}
		if ($id) {
			$adb->pquery('UPDATE vtiger_tds_sections SET code = ?, description = ?, rate = ?, active = ? WHERE section_id = ?', array($code, trim($description), $rate, $active ? 1 : 0, $id));
		} else {
			$adb->pquery('INSERT INTO vtiger_tds_sections (code, description, rate, active) VALUES (?, ?, ?, ?)', array($code, trim($description), $rate, $active ? 1 : 0));
		}
	}

	/** The part of a document's total that is not tax (pre-tax / total), 1 when unknown. */
	private static function taxableShare($module, $id) {
		global $adb;
		$tables = array('Invoice' => array('vtiger_invoice', 'invoiceid'), 'PurchaseOrder' => array('vtiger_purchaseorder', 'purchaseorderid'), 'Quotes' => array('vtiger_quotes', 'quoteid'));
		if (!isset($tables[$module])) {
			return 1.0;
		}
		$result = $adb->pquery("SELECT total, pre_tax_total FROM {$tables[$module][0]} WHERE {$tables[$module][1]} = ?", array($id));
		if (!$adb->num_rows($result)) {
			return 1.0;
		}
		$total = (float)$adb->query_result($result, 0, 'total');
		$pre = (float)$adb->query_result($result, 0, 'pre_tax_total');
		return ($total > 0.004 && $pre > 0.004 && $pre <= $total) ? $pre / $total : 1.0;
	}

	/** Amount to withhold on a payment of $amount against a document, for a section code. */
	public static function compute($documentModule, $documentId, $amount, $code) {
		$sections = self::sections(true);
		if (!isset($sections[$code])) {
			throw new Exception('The withholding section ' . $code . ' does not exist or is not active.');
		}
		return round($amount * self::taxableShare($documentModule, $documentId) * (float)$sections[$code]['rate'] / 100, 2);
	}

	/**
	 * Register of the withholdings of a period: array(rows, totals). Rows per direction, section and party:
	 * array(direction, section, party, payments, paid, withheld).
	 */
	public static function register($from, $to) {
		global $adb;
		$result = $adb->pquery("SELECT p.direction, p.tds_section AS section, COALESCE(a.accountname, v.vendorname, '') AS party, COUNT(*) AS payments,
				SUM(p.amount) AS amount, SUM(p.tds_amount) AS withheld
			FROM vtiger_payments p INNER JOIN vtiger_crmentity c ON c.crmid = p.paymentsid AND c.deleted = 0
			LEFT JOIN vtiger_account a ON a.accountid = p.account_id LEFT JOIN vtiger_vendor v ON v.vendorid = p.vendor_id
			WHERE p.status = 'Completed' AND p.tds_amount > 0 AND p.payment_date >= ? AND p.payment_date <= ?
			GROUP BY p.direction, p.tds_section, party ORDER BY p.direction DESC, p.tds_section, party", array($from, $to));
		$rows = array();
		$totals = array('Paid' => 0.0, 'Received' => 0.0);
		while ($row = $adb->fetch_array($result)) {
			$row['party'] = decode_html($row['party']);
			$rows[] = $row;
			$totals[$row['direction']] = ($totals[$row['direction']] ?? 0) + (float)$row['withheld'];
		}
		return array($rows, $totals);
	}
}
