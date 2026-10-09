<?php
/**
 * GST return data (India), computed from the posted documents of a month:
 *   GSTR-1  B2B, B2C large, B2C small, credit notes (registered / unregistered), HSN summary, document summary
 *   GSTR-3B outward tax, input tax credit, utilisation and net payable
 *   purchase register (the input tax behind 3B)
 *   checks  problems that would hold up a filing (missing HSN, bad GSTIN, wrong tax type for the place of supply...)
 *
 * Source: invoice / credit note / purchase order lines (taxable value after line and document
 * discount, tax percentage per tax head), the customer's or vendor's GSTIN, and the place of supply
 * (shipping state, else billing state, else the GSTIN's state). Tax heads are found by their label
 * (CGST, SGST, IGST in Settings > Taxes), anything else counts as "other tax".
 *
 * Not covered: exports and SEZ supplies, reverse charge, e-commerce operators, advances received,
 * amendments of earlier periods, cess by item, ITC eligibility rules (all input tax is treated as
 * eligible) and the exact GSTN offline-utility JSON; check the CSV columns against the current
 * portal templates before uploading.
 */
include_once 'include/utils/GSTUtils.php';

class Vtiger_Gst_Returns {

	const B2CL_LIMIT = 250000;
	const GSTIN_REGEX = '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$';

	private static $invoiceStatuses = array('Approved', 'Sent', 'Credit Invoice', 'Paid');
	private static $purchaseStatuses = array('Approved', 'Delivered', 'Received Shipment');
	private static $noteStatuses = array('Approved', 'Sent');

	public static function periodBounds($period) {
		if (!preg_match('/^\d{4}-\d{2}$/', (string)$period)) {
			$period = date('Y-m', strtotime('first day of last month'));
		}
		return array($period, $period . '-01', date('Y-m-t', strtotime($period . '-01')));
	}

	/** Names of the tax columns by head: array('CGST' => array('tax4'), 'SGST' => ..., 'IGST' => ..., 'OTHER' => array(...)). */
	private static function taxColumns() {
		global $adb;
		$heads = array('CGST' => array(), 'SGST' => array(), 'IGST' => array(), 'OTHER' => array());
		$result = $adb->pquery('SELECT taxname, taxlabel FROM vtiger_inventorytaxinfo WHERE deleted = 0');
		$columns = $adb->getColumnNames('vtiger_inventoryproductrel');
		while ($row = $adb->fetch_array($result)) {
			if (!in_array($row['taxname'], $columns)) {
				continue;
			}
			$label = strtoupper(decode_html($row['taxlabel']));
			$head = 'OTHER';
			foreach (array('CGST', 'SGST', 'IGST') as $candidate) {
				if (strpos($label, $candidate) !== false) {
					$head = $candidate;
				}
			}
			$heads[$head][] = $row['taxname'];
		}
		return $heads;
	}

	private static function rateExpression(array $columns) {
		if (!$columns) {
			return '0';
		}
		return '(' . implode(' + ', array_map(function ($c) { return 'COALESCE(l.' . $c . ', 0)'; }, $columns)) . ')';
	}

	/** The company's state code (from its state, else its own GSTIN), or null. */
	public static function companyStateCode() {
		global $adb;
		$result = $adb->pquery('SELECT state, vatid FROM vtiger_organizationdetails LIMIT 1');
		if (!$adb->num_rows($result)) {
			return null;
		}
		$code = Vtiger_GST_Utils::stateCodeFromName(decode_html($adb->query_result($result, 0, 'state')));
		return $code ?: Vtiger_GST_Utils::stateCodeFromGSTIN((string)$adb->query_result($result, 0, 'vatid'));
	}

	/**
	 * One row per document and tax rate with taxable value and tax by head, for one kind of document.
	 * $kind: invoice | creditnote | debitnote | purchase.
	 */
	private static function documentRows($kind, $from, $to) {
		global $adb;
		$tax = self::taxColumns();
		$cgst = self::rateExpression($tax['CGST']);
		$sgst = self::rateExpression($tax['SGST']);
		$igst = self::rateExpression($tax['IGST']);
		$other = self::rateExpression($tax['OTHER']);

		$line = '(l.quantity * l.listprice - IF(COALESCE(l.discount_amount, 0) > 0, l.discount_amount, l.quantity * l.listprice * COALESCE(l.discount_percent, 0) / 100))';
		$groupDiscount = 'IF(COALESCE(d.discount_percent, 0) > 0, d.subtotal * d.discount_percent / 100, COALESCE(d.discount_amount, 0))';
		$factor = "IF(d.taxtype = 'group' AND d.subtotal > 0, 1 - $groupDiscount / d.subtotal, 1)";
		$taxable = "$line * $factor";

		$map = array(
			'invoice' => array('vtiger_invoice', 'invoiceid', 'invoice_no', 'd.invoicedate', 'accountid', null, 'invoicestatus', self::$invoiceStatuses, 'vtiger_invoicebillads', 'invoicebilladdressid', 'vtiger_invoiceshipads', 'invoiceshipaddressid', ''),
			'purchase' => array('vtiger_purchaseorder', 'purchaseorderid', 'purchaseorder_no', 'DATE(c.createdtime)', null, 'vendorid', 'postatus', self::$purchaseStatuses, 'vtiger_pobillads', 'pobilladdressid', 'vtiger_poshipads', 'poshipaddressid', ''),
			'creditnote' => array('vtiger_salesorder', 'salesorderid', 'salesorder_no', 'd.duedate', 'accountid', null, 'sostatus', self::$noteStatuses, 'vtiger_sobillads', 'sobilladdressid', 'vtiger_soshipads', 'soshipaddressid', " AND d.note_type = 'Credit Note'"),
			'debitnote' => array('vtiger_salesorder', 'salesorderid', 'salesorder_no', 'd.duedate', null, 'vendorid', 'sostatus', self::$noteStatuses, 'vtiger_sobillads', 'sobilladdressid', 'vtiger_soshipads', 'soshipaddressid', " AND d.note_type = 'Debit Note'"),
		);
		list($table, $idColumn, $noColumn, $dateExpr, $accountColumn, $vendorColumn, $statusColumn, $statuses, $billTable, $billId, $shipTable, $shipId, $extra) = $map[$kind];
		$party = $accountColumn ? "a.gstin AS gstin, a.accountname AS party_name, a.accountid AS party_id" : "v.gstin AS gstin, v.vendorname AS party_name, v.vendorid AS party_id";
		$partyJoin = $accountColumn ? "LEFT JOIN vtiger_account a ON a.accountid = d.$accountColumn" : "LEFT JOIN vtiger_vendor v ON v.vendorid = d.$vendorColumn";
		$statusMarks = implode(',', array_fill(0, count($statuses), '?'));

		$sql = "SELECT d.$idColumn AS id, d.$noColumn AS doc_no, $dateExpr AS doc_date, d.total AS doc_total, $party,
				COALESCE(NULLIF(s.ship_state, ''), NULLIF(b.bill_state, '')) AS state,
				hsn_unit.hsn AS hsn, hsn_unit.unit AS unit,
				($cgst + $sgst + $igst + $other) AS rate_all, $cgst AS cgst_rate, $sgst AS sgst_rate, $igst AS igst_rate, $other AS other_rate,
				SUM($taxable) AS taxable, SUM(l.quantity) AS qty
			FROM $table d INNER JOIN vtiger_crmentity c ON c.crmid = d.$idColumn AND c.deleted = 0
			INNER JOIN vtiger_inventoryproductrel l ON l.id = d.$idColumn
			LEFT JOIN $billTable b ON b.$billId = d.$idColumn LEFT JOIN $shipTable s ON s.$shipId = d.$idColumn
			$partyJoin
			LEFT JOIN (
				SELECT p.productid AS pid, p.hsn_sac_code AS hsn, p.usageunit AS unit FROM vtiger_products p
				UNION ALL SELECT sv.serviceid, sv.hsn_sac_code, sv.service_usageunit FROM vtiger_service sv
			) hsn_unit ON hsn_unit.pid = l.productid
			WHERE d.$statusColumn IN ($statusMarks) AND $dateExpr >= ? AND $dateExpr <= ?$extra
			GROUP BY d.$idColumn, d.$noColumn, doc_date, d.total, party_id, gstin, party_name, state, hsn, unit, rate_all, cgst_rate, sgst_rate, igst_rate, other_rate
			ORDER BY doc_date, d.$idColumn";
		return $adb->pquery($sql, array_merge($statuses, array($from, $to)));
	}

	private static function isRegistered($gstin) {
		return (bool)preg_match('/' . self::GSTIN_REGEX . '/', Vtiger_GST_Utils::normalizeGSTIN($gstin));
	}

	/** Place of supply as "29-Karnataka" from the shipping/billing state, else the GSTIN, else ''. */
	private static function placeOfSupply($state, $gstin) {
		return Vtiger_GST_Utils::placeOfSupplyLabel(decode_html((string)$state), (string)$gstin);
	}

	private static function emptyTotals() {
		return array('taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'other' => 0.0);
	}

	private static function addToTotals(array &$totals, array $r) {
		$totals['taxable'] += $r['taxable'];
		$totals['igst'] += $r['igst'];
		$totals['cgst'] += $r['cgst'];
		$totals['sgst'] += $r['sgst'];
		$totals['other'] += $r['other'];
	}

	/** Money and tax of a document row: array(taxable, igst, cgst, sgst, other, rate). */
	private static function amounts(array $row) {
		$t = (float)$row['taxable'];
		return array(
			'taxable' => $t, 'igst' => $t * $row['igst_rate'] / 100, 'cgst' => $t * $row['cgst_rate'] / 100,
			'sgst' => $t * $row['sgst_rate'] / 100, 'other' => $t * $row['other_rate'] / 100,
			'rate' => (float)$row['igst_rate'] + (float)$row['cgst_rate'] + (float)$row['sgst_rate'] + (float)$row['other_rate'],
		);
	}

	/** GSTR-1 data for a month. */
	public static function gstr1($period) {
		global $adb;
		list($period, $from, $to) = self::periodBounds($period);
		$data = array(
			'period' => $period, 'from' => $from, 'to' => $to,
			'b2b' => array(), 'b2cl' => array(), 'b2cs' => array(), 'cdnr' => array(), 'cdnur' => array(), 'hsn' => array(),
			'totals' => array('b2b' => self::emptyTotals(), 'b2cl' => self::emptyTotals(), 'b2cs' => self::emptyTotals(), 'cdnr' => self::emptyTotals(), 'cdnur' => self::emptyTotals(), 'hsn' => self::emptyTotals()),
			'documents' => array(),
		);
		$invoices = array();
		$result = self::documentRows('invoice', $from, $to);
		while ($row = $adb->fetch_array($result)) {
			$a = self::amounts($row);
			$id = $row['id'];
			$registered = self::isRegistered($row['gstin']);
			$pos = self::placeOfSupply($row['state'], $row['gstin']);
			$entry = array('id' => $id, 'gstin' => Vtiger_GST_Utils::normalizeGSTIN($row['gstin']), 'name' => decode_html($row['party_name']), 'doc_no' => decode_html($row['doc_no']),
				'date' => $row['doc_date'], 'value' => (float)$row['doc_total'], 'pos' => $pos, 'rate' => $a['rate'], 'taxable' => $a['taxable'], 'igst' => $a['igst'], 'cgst' => $a['cgst'], 'sgst' => $a['sgst'], 'other' => $a['other']);
			$class = $registered ? 'b2b' : (($a['igst'] > 0 && (float)$row['doc_total'] > self::B2CL_LIMIT) ? 'b2cl' : 'b2cs');
			if ($class == 'b2cs') {
				// small unregistered supplies are reported in one line per place of supply and rate
				$key = $pos . '|' . $a['rate'];
				if (!isset($data['b2cs'][$key])) {
					$data['b2cs'][$key] = array('pos' => $pos, 'rate' => $a['rate'], 'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'other' => 0.0);
				}
				foreach (array('taxable', 'igst', 'cgst', 'sgst', 'other') as $k) {
					$data['b2cs'][$key][$k] += $entry[$k];
				}
			} else {
				$data[$class][] = $entry;
			}
			self::addToTotals($data['totals'][$class], $entry);
			$invoices[$id] = true;

			// HSN summary: by code, unit and rate
			$hkey = ($row['hsn'] ?: '-') . '|' . $row['unit'] . '|' . $a['rate'];
			if (!isset($data['hsn'][$hkey])) {
				$data['hsn'][$hkey] = array('hsn' => $row['hsn'] ?: '', 'unit' => decode_html((string)$row['unit']), 'rate' => $a['rate'], 'qty' => 0.0, 'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'other' => 0.0);
			}
			$data['hsn'][$hkey]['qty'] += (float)$row['qty'];
			foreach (array('taxable', 'igst', 'cgst', 'sgst', 'other') as $k) {
				$data['hsn'][$hkey][$k] += $entry[$k];
			}
			self::addToTotals($data['totals']['hsn'], $entry);
		}
		ksort($data['b2cs']);
		ksort($data['hsn']);
		$data['b2cs'] = array_values($data['b2cs']);
		$data['hsn'] = array_values($data['hsn']);

		// credit notes: to registered customers (CDNR) or unregistered (CDNUR)
		$result = self::documentRows('creditnote', $from, $to);
		while ($row = $adb->fetch_array($result)) {
			$a = self::amounts($row);
			$registered = self::isRegistered($row['gstin']);
			$entry = array('id' => $row['id'], 'gstin' => Vtiger_GST_Utils::normalizeGSTIN($row['gstin']), 'name' => decode_html($row['party_name']), 'doc_no' => decode_html($row['doc_no']),
				'date' => $row['doc_date'], 'value' => (float)$row['doc_total'], 'pos' => self::placeOfSupply($row['state'], $row['gstin']), 'rate' => $a['rate'], 'taxable' => $a['taxable'],
				'igst' => $a['igst'], 'cgst' => $a['cgst'], 'sgst' => $a['sgst'], 'other' => $a['other'], 'note_type' => 'C');
			$class = $registered ? 'cdnr' : 'cdnur';
			$data[$class][] = $entry;
			self::addToTotals($data['totals'][$class], $entry);
		}

		// document summary: first and last number, count, cancelled
		$status = $adb->pquery("SELECT d.invoice_no, d.invoicestatus FROM vtiger_invoice d INNER JOIN vtiger_crmentity c ON c.crmid = d.invoiceid AND c.deleted = 0
			WHERE d.invoicedate >= ? AND d.invoicedate <= ? ORDER BY d.invoiceid", array($from, $to));
		$numbers = array();
		$cancelled = 0;
		while ($row = $adb->fetch_array($status)) {
			$numbers[] = decode_html($row['invoice_no']);
			if ($row['invoicestatus'] == 'Cancel') {
				$cancelled++;
			}
		}
		$data['documents'][] = array('type' => 'Invoices for outward supply', 'from' => $numbers ? reset($numbers) : '', 'to' => $numbers ? end($numbers) : '', 'total' => count($numbers), 'cancelled' => $cancelled, 'net' => count($numbers) - $cancelled);
		$notes = $adb->pquery("SELECT d.salesorder_no FROM vtiger_salesorder d INNER JOIN vtiger_crmentity c ON c.crmid = d.salesorderid AND c.deleted = 0
			WHERE d.note_type = 'Credit Note' AND d.sostatus IN ('Approved', 'Sent') AND d.duedate >= ? AND d.duedate <= ? ORDER BY d.salesorderid", array($from, $to));
		$noteNumbers = array();
		while ($row = $adb->fetch_array($notes)) {
			$noteNumbers[] = decode_html($row['salesorder_no']);
		}
		$data['documents'][] = array('type' => 'Credit notes', 'from' => $noteNumbers ? reset($noteNumbers) : '', 'to' => $noteNumbers ? end($noteNumbers) : '', 'total' => count($noteNumbers), 'cancelled' => 0, 'net' => count($noteNumbers));
		return $data;
	}

	/** Purchase register: purchase orders of the month with vendor GSTIN and input tax, and the debit notes that reverse it. */
	public static function purchases($period) {
		global $adb;
		list($period, $from, $to) = self::periodBounds($period);
		$out = array('period' => $period, 'purchases' => array(), 'debit_notes' => array(), 'totals' => self::emptyTotals(), 'reversals' => self::emptyTotals());
		foreach (array('purchase' => 'purchases', 'debitnote' => 'debit_notes') as $kind => $key) {
			$result = self::documentRows($kind, $from, $to);
			$byDocument = array();
			while ($row = $adb->fetch_array($result)) {
				$a = self::amounts($row);
				$id = $row['id'];
				if (!isset($byDocument[$id])) {
					$byDocument[$id] = array('id' => $id, 'gstin' => Vtiger_GST_Utils::normalizeGSTIN($row['gstin']), 'name' => decode_html($row['party_name']), 'doc_no' => decode_html($row['doc_no']),
						'date' => $row['doc_date'], 'value' => (float)$row['doc_total'], 'pos' => self::placeOfSupply($row['state'], $row['gstin']), 'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'other' => 0.0);
				}
				foreach (array('taxable', 'igst', 'cgst', 'sgst', 'other') as $k) {
					$byDocument[$id][$k] += $a[$k];
				}
			}
			foreach ($byDocument as $entry) {
				$out[$key][] = $entry;
				self::addToTotals($out[$kind == 'purchase' ? 'totals' : 'reversals'], $entry);
			}
		}
		return $out;
	}

	/**
	 * GSTR-3B figures. Output tax from invoices less credit notes; input tax credit from purchases less
	 * debit notes (all treated as eligible); then the standard order of set-off: IGST credit against
	 * IGST, then CGST, then SGST; CGST credit against CGST then IGST; SGST credit against SGST then IGST.
	 */
	public static function gstr3b($period) {
		$g1 = self::gstr1($period);
		$p = self::purchases($period);
		$heads = array('taxable', 'igst', 'cgst', 'sgst', 'other');

		$outward = array();
		$nil = 0.0;
		foreach ($heads as $h) {
			$outward[$h] = $g1['totals']['b2b'][$h] + $g1['totals']['b2cl'][$h] + $g1['totals']['b2cs'][$h] - $g1['totals']['cdnr'][$h] - $g1['totals']['cdnur'][$h];
		}
		// supplies at 0% are shown separately as nil-rated / exempt / non-GST
		foreach (array('b2b', 'b2cl') as $class) {
			foreach ($g1[$class] as $row) {
				if ($row['rate'] == 0) {
					$nil += $row['taxable'];
				}
			}
		}
		foreach ($g1['b2cs'] as $row) {
			if ($row['rate'] == 0) {
				$nil += $row['taxable'];
			}
		}
		$taxableSupplies = $outward['taxable'] - $nil;

		$itc = array();
		foreach (array('igst', 'cgst', 'sgst', 'other') as $h) {
			$itc[$h] = array('available' => $p['totals'][$h], 'reversed' => $p['reversals'][$h], 'net' => $p['totals'][$h] - $p['reversals'][$h]);
		}
		// set-off
		$credit = array('igst' => max(0, $itc['igst']['net']), 'cgst' => max(0, $itc['cgst']['net']), 'sgst' => max(0, $itc['sgst']['net']));
		$due = array('igst' => max(0, $outward['igst']), 'cgst' => max(0, $outward['cgst']), 'sgst' => max(0, $outward['sgst']));
		$paidByCredit = array('igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0);
		$use = function ($from, $to, &$credit, &$due, &$paid) {
			$amount = min($credit[$from], $due[$to]);
			$credit[$from] -= $amount;
			$due[$to] -= $amount;
			$paid[$to] += $amount;
		};
		$use('igst', 'igst', $credit, $due, $paidByCredit);
		$use('igst', 'cgst', $credit, $due, $paidByCredit);
		$use('igst', 'sgst', $credit, $due, $paidByCredit);
		$use('cgst', 'cgst', $credit, $due, $paidByCredit);
		$use('cgst', 'igst', $credit, $due, $paidByCredit);
		$use('sgst', 'sgst', $credit, $due, $paidByCredit);
		$use('sgst', 'igst', $credit, $due, $paidByCredit);

		return array(
			'period' => $g1['period'], 'outward' => $outward, 'taxable_supplies' => $taxableSupplies, 'nil' => $nil,
			'itc' => $itc, 'cash' => $due, 'credit_used' => $paidByCredit, 'credit_left' => $credit,
			'cash_total' => array_sum($due),
		);
	}

	/** Problems in the month's data that would hold up a filing: array(array(title, hint, rows => array(array(label, url)))). */
	public static function checks($period) {
		global $adb;
		list($period, $from, $to) = self::periodBounds($period);
		$statuses = "'" . implode("','", self::$invoiceStatuses) . "'";
		$checks = array();
		$add = function ($title, $hint, $result, $labelColumn, $module, $idColumn = 'id', $extra = null) use (&$checks, $adb) {
			$rows = array();
			$count = 0;
			while ($row = $adb->fetch_array($result)) {
				$count++;
				if (count($rows) < 25) {
					$rows[] = array('label' => decode_html($row[$labelColumn]) . ($extra && !empty($row[$extra]) ? ' - ' . decode_html($row[$extra]) : ''),
						'url' => 'index.php?module=' . $module . '&view=Detail&record=' . $row[$idColumn]);
				}
			}
			$checks[] = array('title' => $title, 'hint' => $hint, 'count' => $count, 'rows' => $rows);
		};

		$add('Invoice lines without an HSN / SAC code', 'Add the code on the product or service; returns require it for every line.',
			$adb->pquery("SELECT DISTINCT d.invoiceid AS id, d.invoice_no AS label FROM vtiger_invoice d INNER JOIN vtiger_crmentity c ON c.crmid = d.invoiceid AND c.deleted = 0
				INNER JOIN vtiger_inventoryproductrel l ON l.id = d.invoiceid
				LEFT JOIN vtiger_products p ON p.productid = l.productid LEFT JOIN vtiger_service s ON s.serviceid = l.productid
				WHERE d.invoicestatus IN ($statuses) AND d.invoicedate >= ? AND d.invoicedate <= ? AND COALESCE(NULLIF(p.hsn_sac_code, ''), NULLIF(s.hsn_sac_code, '')) IS NULL LIMIT 500", array($from, $to)),
			'label', 'Invoice');

		$add('Customers with a GSTIN that is not valid', 'The number is filled in but does not pass the format / check-character test, so the invoice would be reported as unregistered.',
			$adb->pquery("SELECT DISTINCT a.accountid AS id, a.accountname AS label, a.gstin AS extra FROM vtiger_invoice d INNER JOIN vtiger_crmentity c ON c.crmid = d.invoiceid AND c.deleted = 0
				INNER JOIN vtiger_account a ON a.accountid = d.accountid
				WHERE d.invoicestatus IN ($statuses) AND d.invoicedate >= ? AND d.invoicedate <= ? AND a.gstin IS NOT NULL AND a.gstin != '' AND a.gstin NOT REGEXP '" . self::GSTIN_REGEX . "' LIMIT 500", array($from, $to)),
			'label', 'Accounts', 'id', 'extra');

		$add('Invoices with no place of supply', 'No shipping or billing state and no valid GSTIN: the supply cannot be placed in a state.',
			$adb->pquery("SELECT d.invoiceid AS id, d.invoice_no AS label FROM vtiger_invoice d INNER JOIN vtiger_crmentity c ON c.crmid = d.invoiceid AND c.deleted = 0
				LEFT JOIN vtiger_invoicebillads b ON b.invoicebilladdressid = d.invoiceid LEFT JOIN vtiger_invoiceshipads s ON s.invoiceshipaddressid = d.invoiceid
				LEFT JOIN vtiger_account a ON a.accountid = d.accountid
				WHERE d.invoicestatus IN ($statuses) AND d.invoicedate >= ? AND d.invoicedate <= ? AND COALESCE(NULLIF(s.ship_state, ''), NULLIF(b.bill_state, '')) IS NULL
				AND (a.gstin IS NULL OR a.gstin NOT REGEXP '" . self::GSTIN_REGEX . "') LIMIT 500", array($from, $to)),
			'label', 'Invoice');

		$duplicates = $adb->pquery("SELECT MIN(d.invoiceid) AS id, d.invoice_no AS label FROM vtiger_invoice d INNER JOIN vtiger_crmentity c ON c.crmid = d.invoiceid AND c.deleted = 0
			WHERE d.invoicedate >= ? AND d.invoicedate <= ? GROUP BY d.invoice_no HAVING COUNT(*) > 1", array($from, $to));
		$add('Duplicate invoice numbers', 'Every invoice number must be unique in the return.', $duplicates, 'label', 'Invoice');

		// intra-state supply must carry CGST + SGST, inter-state IGST
		$company = self::companyStateCode();
		if ($company) {
			$mismatch = array();
			$result = self::documentRows('invoice', $from, $to);
			while ($row = $adb->fetch_array($result)) {
				$code = Vtiger_GST_Utils::stateCodeFromName(decode_html((string)$row['state'])) ?: Vtiger_GST_Utils::stateCodeFromGSTIN((string)$row['gstin']);
				if (!$code || (float)$row['rate_all'] == 0) {
					continue;
				}
				$intra = ($code == $company);
				if (($intra && (float)$row['igst_rate'] > 0) || (!$intra && ((float)$row['cgst_rate'] > 0 || (float)$row['sgst_rate'] > 0))) {
					$mismatch[$row['id']] = decode_html($row['doc_no']);
				}
			}
			$rows = array();
			foreach (array_slice($mismatch, 0, 25, true) as $id => $no) {
				$rows[] = array('label' => $no, 'url' => 'index.php?module=Invoice&view=Detail&record=' . $id);
			}
			$checks[] = array('title' => 'Tax type does not match the place of supply', 'hint' => 'Supply within the company\'s state should carry CGST + SGST, other states IGST. Check the tax region on the invoice.', 'count' => count($mismatch), 'rows' => $rows);
		} else {
			$checks[] = array('title' => 'Company state is not set', 'hint' => 'Fill in the state (or a valid GSTIN) in Company Details so intra- and inter-state supplies can be checked.', 'count' => 1, 'rows' => array());
		}

		$add('Vendors on purchases without a GSTIN', 'Input tax credit needs the supplier\'s GSTIN.',
			$adb->pquery("SELECT DISTINCT v.vendorid AS id, v.vendorname AS label FROM vtiger_purchaseorder d INNER JOIN vtiger_crmentity c ON c.crmid = d.purchaseorderid AND c.deleted = 0
				INNER JOIN vtiger_vendor v ON v.vendorid = d.vendorid
				WHERE d.postatus IN ('Approved', 'Delivered', 'Received Shipment') AND DATE(c.createdtime) >= ? AND DATE(c.createdtime) <= ? AND (v.gstin IS NULL OR v.gstin = '') LIMIT 500", array($from, $to)),
			'label', 'Vendors');
		return $checks;
	}
}
