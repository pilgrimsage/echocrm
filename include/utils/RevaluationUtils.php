<?php
/**
 * Period-end revaluation of foreign-currency receivables and payables.
 *
 * Documents in a foreign currency are booked at the rate they carry. At a period end the open balance of each is
 * worth something different at the day's rate. For a chosen date and one rate per currency, this works out the
 * unrealised difference per customer / vendor (open amount as at the date: total less payments completed by then,
 * at the closing rate against the rate booked) and posts one entry dated that day: the receivable or payable is
 * adjusted and the difference goes to Exchange Gain / Loss. The next day the same entry is reversed, so payments
 * keep clearing the balance at the booked rate and the realised difference is posted by the payment as usual.
 * Running it again for the same date replaces its entries. Credit and debit notes are not taken into account.
 */
class Vtiger_Revaluation_Utils {

	/** Foreign-currency documents still open as at a date, grouped by currency: array(currency id => array(code, items)). */
	public static function openItems($asAt) {
		global $adb;
		include_once 'include/utils/LedgerUtils.php';
		$types = array(
			'Invoice' => array('vtiger_invoice', 'invoiceid', 'invoice_no', 'invoicestatus', array('Approved', 'Sent', 'Credit Invoice', 'Paid'), 'accountid', 'NULL', 'receivable'),
			'PurchaseOrder' => array('vtiger_purchaseorder', 'purchaseorderid', 'purchaseorder_no', 'postatus', array('Approved', 'Delivered', 'Received Shipment'), 'NULL', 'vendorid', 'payable'),
		);
		$groups = array();
		foreach ($types as $module => $t) {
			list($table, $idColumn, $noColumn, $statusColumn, $statuses, $account, $vendor, $side) = $t;
			$marks = implode(',', array_fill(0, count($statuses), '?'));
			$result = $adb->pquery("SELECT d.$idColumn AS id, d.$noColumn AS no, d.total, d.conversion_rate AS rate, d.currency_id, $account AS account, $vendor AS vendor, ci.currency_code
				FROM $table d INNER JOIN vtiger_crmentity c ON c.crmid = d.$idColumn AND c.deleted = 0 LEFT JOIN vtiger_currency_info ci ON ci.id = d.currency_id
				WHERE d.$statusColumn IN ($marks) AND ABS(d.conversion_rate - 1) > 0.0000001 AND d.conversion_rate > 0", $statuses);
			while ($d = $adb->fetch_array($result)) {
				if (Vtiger_Ledger_Utils::documentDate($module, $d['id']) > $asAt) {
					continue;
				}
				$paid = $adb->pquery("SELECT COALESCE(SUM(p.amount), 0) FROM vtiger_payments p INNER JOIN vtiger_crmentity pc ON pc.crmid = p.paymentsid AND pc.deleted = 0
					WHERE p.related_to = ? AND p.status = 'Completed' AND p.payment_date <= ?", array($d['id'], $asAt));
				$open = round((float)$d['total'] - (float)$adb->query_result($paid, 0, 0), 2);
				if ($open < 0.005) {
					continue;
				}
				$groups[$d['currency_id']]['code'] = $d['currency_code'] ?: ('#' . $d['currency_id']);
				$groups[$d['currency_id']]['items'][] = array('module' => $module, 'id' => $d['id'], 'no' => decode_html($d['no']), 'side' => $side, 'open' => $open,
					'rate' => (float)$d['rate'], 'account' => $d['account'], 'vendor' => $d['vendor']);
			}
		}
		return $groups;
	}

	/** The rate stored with each currency (units per 1 base unit), the default for the closing rate. */
	public static function defaultRates() {
		global $adb;
		$rates = array();
		$result = $adb->pquery('SELECT id, conversion_rate FROM vtiger_currency_info WHERE deleted = 0');
		while ($row = $adb->fetch_array($result)) {
			$rates[$row['id']] = (float)$row['conversion_rate'];
		}
		return $rates;
	}

	/**
	 * What the revaluation would post. $rates: currency id => closing rate. Returns array(rows, lines, net) where
	 * rows are per document (book value, value at the closing rate, gain) and lines are the journal lines.
	 */
	public static function preview($asAt, array $rates) {
		$rows = array();
		$perParty = array();
		$net = 0.0;
		foreach (self::openItems($asAt) as $currencyId => $group) {
			$closing = (float)($rates[$currencyId] ?? 0);
			if ($closing <= 0) {
				throw new Exception('Enter the closing rate of ' . $group['code'] . '.');
			}
			foreach ($group['items'] as $item) {
				$book = round($item['open'] / $item['rate'], 2);
				$now = round($item['open'] / $closing, 2);
				$gain = round($item['side'] == 'receivable' ? $now - $book : $book - $now, 2);
				$rows[] = $item + array('currency' => $group['code'], 'closing' => $closing, 'book' => $book, 'now' => $now, 'gain' => $gain);
				if (abs($gain) < 0.005) {
					continue;
				}
				$cost = Vtiger_Ledger_Utils::documentCostCentre($item['module'], $item['id']);
				$key = $item['side'] . '|' . $item['account'] . '|' . $item['vendor'] . '|' . (int)$cost;
				$perParty[$key] = ($perParty[$key] ?? array('side' => $item['side'], 'account' => $item['account'], 'vendor' => $item['vendor'], 'cost' => $cost, 'gain' => 0.0));
				$perParty[$key]['gain'] += $gain;
			}
		}
		$exchange = Vtiger_Ledger_Utils::ledgerId('Exchange Gain / Loss', 'Expenses');
		$lines = array();
		foreach ($perParty as $p) {
			$gain = round($p['gain'], 2);
			if (abs($gain) < 0.005) {
				continue;
			}
			$ledger = $p['side'] == 'receivable' ? Vtiger_Ledger_Utils::ledgerId('Accounts Receivable', 'Assets') : Vtiger_Ledger_Utils::ledgerId('Accounts Payable', 'Liabilities');
			// a gain increases a receivable and decreases a payable: either way the party ledger is debited and the exchange ledger credited
			$lines[] = array('ledger' => $ledger, 'debit' => $gain > 0 ? $gain : 0, 'credit' => $gain < 0 ? -$gain : 0, 'account' => $p['account'], 'vendor' => $p['vendor'], 'cost' => $p['cost']);
			$lines[] = array('ledger' => $exchange, 'debit' => $gain < 0 ? -$gain : 0, 'credit' => $gain > 0 ? $gain : 0, 'account' => null, 'vendor' => null, 'cost' => $p['cost']);
			$net += $gain;
		}
		return array($rows, $lines, round($net, 2));
	}

	private static function sourceId($asAt) {
		return (int)str_replace('-', '', $asAt);
	}

	/** Posts (or re-posts) the revaluation of a date and its reversal on the next day. Returns the net gain. */
	public static function post($asAt, array $rates) {
		global $adb, $current_user;
		if (!$asAt || !strtotime($asAt) || $asAt > date('Y-m-d')) {
			throw new Exception('Choose a date that is not in the future.');
		}
		list($rows, $lines, $net) = self::preview($asAt, $rates);
		$id = self::sourceId($asAt);
		$next = date('Y-m-d', strtotime($asAt . ' +1 day'));
		Vtiger_Ledger_Utils::syncPartyEntry('Revaluation', $id, 'reval', $asAt, 'Revaluation of foreign currency balances at ' . $asAt, $lines);
		$reversed = array();
		foreach ($lines as $l) {
			$reversed[] = array('ledger' => $l['ledger'], 'debit' => $l['credit'], 'credit' => $l['debit'], 'account' => $l['account'], 'vendor' => $l['vendor'], 'cost' => $l['cost']);
		}
		Vtiger_Ledger_Utils::syncPartyEntry('Revaluation', $id, 'reval_rev', $next, 'Reversal of revaluation at ' . $asAt, $reversed);
		$adb->pquery('DELETE FROM vtiger_revaluations WHERE as_at = ?', array($asAt));
		$adb->pquery('INSERT INTO vtiger_revaluations (as_at, rates, net, documents, created_by, created_time) VALUES (?, ?, ?, ?, ?, NOW())',
			array($asAt, json_encode($rates), $net, count($rows), $current_user ? $current_user->id : 1));
		return $net;
	}

	/** Removes a revaluation and its reversal. */
	public static function undo($asAt) {
		global $adb;
		$id = self::sourceId($asAt);
		Vtiger_Ledger_Utils::syncPartyEntry('Revaluation', $id, 'reval_rev', date('Y-m-d', strtotime($asAt . ' +1 day')), '', array());
		Vtiger_Ledger_Utils::syncPartyEntry('Revaluation', $id, 'reval', $asAt, '', array());
		$adb->pquery('DELETE FROM vtiger_revaluations WHERE as_at = ?', array($asAt));
	}

	public static function history() {
		global $adb;
		$result = $adb->pquery('SELECT * FROM vtiger_revaluations ORDER BY as_at DESC LIMIT 20');
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$rows[] = $row;
		}
		return $rows;
	}
}
