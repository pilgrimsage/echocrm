<?php
/**
 * Stock valuation (moving weighted average cost) and cost of goods sold.
 *
 * Every change in stock is a move in vtiger_stock_moves (signed quantity, unit cost, value, and the
 * running balance of that product after it). Moves are created from the documents:
 *   purchase order received      IN  at the line's cost                 (fixed cost)
 *   invoice posted               OUT at the average cost of that day    (average cost)
 *   credit note, sales return    IN  at the cost the invoice sold at    (fixed cost)
 *   debit note, purchase return  OUT at the cost the purchase came in   (fixed cost)
 *   stock adjustment / opening   IN at the given cost, OUT at average cost
 * Fixed-cost moves keep their cost; average-cost moves are re-priced whenever an earlier move
 * changes, and the journal entries built from them (cost of goods sold, inventory) follow.
 * Only products with "Track stock" ticked take part; services never do.
 *
 * Not covered: FIFO / specific batch costing, several warehouses, landed cost (freight, duty),
 * serial numbers and batch expiry, manufacturing (bill of materials, work in progress).
 */
class Vtiger_Stock_Utils {

	/** Document statuses in which a document moves stock. */
	private static $moveStatuses = array(
		'Invoice' => array('Approved', 'Sent', 'Credit Invoice', 'Paid'),
		'PurchaseOrder' => array('Received Shipment'),
		'SalesOrder' => array('Approved', 'Sent'),
	);

	private static $trackedCache = array();

	public static function enabled() {
		global $adb;
		static $enabled = null;
		if ($enabled === null) {
			$enabled = in_array('track_stock', $adb->getColumnNames('vtiger_products'));
		}
		return $enabled;
	}

	/** Whether a line item's product takes part in stock (a product with "Track stock" ticked; never a service). */
	public static function isTracked($productId) {
		global $adb;
		if (!self::enabled()) {
			return false;
		}
		if (!isset(self::$trackedCache[$productId])) {
			$result = $adb->pquery('SELECT track_stock FROM vtiger_products WHERE productid = ?', array($productId));
			self::$trackedCache[$productId] = $adb->num_rows($result) && (int)$adb->query_result($result, 0, 0) === 1;
		}
		return self::$trackedCache[$productId];
	}

	// ---- reading a document ---------------------------------------------------------------------------

	/**
	 * The stock lines of a document: array(array(product, line, qty, value)), value being the line's net
	 * after line discount and its share of any document discount (group tax mode), so they add up like the entry's net.
	 */
	public static function documentLines($module, $id) {
		global $adb;
		$tables = array('Invoice' => 'vtiger_invoice', 'PurchaseOrder' => 'vtiger_purchaseorder', 'SalesOrder' => 'vtiger_salesorder');
		$ids = array('Invoice' => 'invoiceid', 'PurchaseOrder' => 'purchaseorderid', 'SalesOrder' => 'salesorderid');
		if (!isset($tables[$module])) {
			return array();
		}
		$doc = $adb->pquery("SELECT taxtype, subtotal, discount_percent, discount_amount FROM {$tables[$module]} WHERE {$ids[$module]} = ?", array($id));
		$factor = 1.0;
		if ($adb->num_rows($doc)) {
			$d = $adb->fetch_array($doc);
			$subtotal = (float)$d['subtotal'];
			$discount = (float)$d['discount_percent'] > 0 ? $subtotal * (float)$d['discount_percent'] / 100 : (float)$d['discount_amount'];
			if ($d['taxtype'] == 'group' && $subtotal > 0 && $discount > 0) {
				$factor = 1 - $discount / $subtotal;
			}
		}
		$result = $adb->pquery('SELECT productid, sequence_no, quantity, listprice, discount_percent, discount_amount FROM vtiger_inventoryproductrel WHERE id = ? ORDER BY sequence_no, lineitem_id', array($id));
		$lines = array();
		while ($row = $adb->fetch_array($result)) {
			if (!self::isTracked($row['productid']) || (float)$row['quantity'] <= 0) {
				continue;
			}
			$gross = (float)$row['quantity'] * (float)$row['listprice'];
			$disc = !empty($row['discount_amount']) ? (float)$row['discount_amount'] : $gross * (float)$row['discount_percent'] / 100;
			$lines[] = array('product' => (int)$row['productid'], 'line' => (int)$row['sequence_no'], 'qty' => (float)$row['quantity'], 'value' => round(max(0, $gross - $disc) * $factor, 2));
		}
		return $lines;
	}

	/** Sum of the stock lines' value of a purchase order or credit/debit note: the part of the entry that belongs in inventory. */
	public static function stockValue($module, $id) {
		$sum = 0.0;
		foreach (self::documentLines($module, $id) as $line) {
			$sum += $line['value'];
		}
		return round($sum, 2);
	}

	// ---- moves ----------------------------------------------------------------------------------------

	private static function movesOf($module, $id) {
		global $adb;
		$result = $adb->pquery('SELECT * FROM vtiger_stock_moves WHERE source_module = ? AND source_id = ? ORDER BY move_id', array($module, $id));
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$rows[] = $row;
		}
		return $rows;
	}

	/** Cost per unit the moves of a document had for a product (average over its lines), or null. */
	private static function unitCostOf($module, $id, $product) {
		global $adb;
		$result = $adb->pquery('SELECT SUM(ABS(qty)) AS q, SUM(ABS(value)) AS v FROM vtiger_stock_moves WHERE source_module = ? AND source_id = ? AND product_id = ?', array($module, $id, $product));
		$q = (float)$adb->query_result($result, 0, 'q');
		return $q > 0 ? (float)$adb->query_result($result, 0, 'v') / $q : null;
	}

	/** Last known average cost of a product on or before a date (0 when it never had stock). */
	public static function averageCostOn($product, $date) {
		global $adb;
		$result = $adb->pquery('SELECT avg_cost FROM vtiger_stock_moves WHERE product_id = ? AND move_date <= ? ORDER BY move_date DESC, move_id DESC LIMIT 1', array($product, $date));
		return $adb->num_rows($result) ? (float)$adb->query_result($result, 0, 0) : 0.0;
	}

	/**
	 * The moves a document should have right now: array of array(product, line, type, qty (signed), unit_cost or null for average,
	 * date, mode). Empty when the document is not in a status that moves stock, is deleted, or (notes) is not a physical return.
	 */
	private static function desiredMoves($module, $id) {
		global $adb;
		include_once 'include/utils/LedgerUtils.php';
		$date = Vtiger_Ledger_Utils::documentDate($module, $id);
		$moves = array();
		if ($module == 'Invoice') {
			$r = $adb->pquery('SELECT i.invoicestatus AS status, c.deleted FROM vtiger_invoice i INNER JOIN vtiger_crmentity c ON c.crmid = i.invoiceid WHERE i.invoiceid = ?', array($id));
			if (!$adb->num_rows($r) || $adb->query_result($r, 0, 'deleted') || !in_array($adb->query_result($r, 0, 'status'), self::$moveStatuses['Invoice'], true)) {
				return array();
			}
			foreach (self::documentLines('Invoice', $id) as $l) {
				$moves[] = array('product' => $l['product'], 'line' => $l['line'], 'type' => 'Sale', 'qty' => -$l['qty'], 'cost' => null, 'date' => $date, 'mode' => 'avg');
			}
		} elseif ($module == 'PurchaseOrder') {
			$r = $adb->pquery('SELECT p.postatus AS status, c.deleted FROM vtiger_purchaseorder p INNER JOIN vtiger_crmentity c ON c.crmid = p.purchaseorderid WHERE p.purchaseorderid = ?', array($id));
			if (!$adb->num_rows($r) || $adb->query_result($r, 0, 'deleted') || !in_array($adb->query_result($r, 0, 'status'), self::$moveStatuses['PurchaseOrder'], true)) {
				return array();
			}
			foreach (self::documentLines('PurchaseOrder', $id) as $l) {
				$moves[] = array('product' => $l['product'], 'line' => $l['line'], 'type' => 'Purchase', 'qty' => $l['qty'], 'cost' => $l['value'] / $l['qty'], 'date' => null, 'mode' => 'fixed');
			}
		} elseif ($module == 'SalesOrder') {
			$r = $adb->pquery('SELECT n.note_type, n.sostatus AS status, n.reason, n.invoiceid, n.purchaseorderid, c.deleted FROM vtiger_salesorder n INNER JOIN vtiger_crmentity c ON c.crmid = n.salesorderid WHERE n.salesorderid = ?', array($id));
			if (!$adb->num_rows($r)) {
				return array();
			}
			$n = $adb->fetch_array($r);
			// only a return of goods moves stock: a credit note for "Sales Return", a debit note for "Purchase Return"
			$physical = ($n['note_type'] == 'Debit Note' && $n['reason'] == 'Purchase Return') || ($n['note_type'] != 'Debit Note' && $n['reason'] == 'Sales Return');
			if ($n['deleted'] || !$physical || !in_array($n['status'], self::$moveStatuses['SalesOrder'], true)) {
				return array();
			}
			foreach (self::documentLines('SalesOrder', $id) as $l) {
				if ($n['note_type'] == 'Debit Note') {
					$cost = $n['purchaseorderid'] ? self::unitCostOf('PurchaseOrder', $n['purchaseorderid'], $l['product']) : null;
					$moves[] = array('product' => $l['product'], 'line' => $l['line'], 'type' => 'Purchase Return', 'qty' => -$l['qty'], 'cost' => $cost ?? $l['value'] / $l['qty'], 'date' => $date, 'mode' => 'fixed');
				} else {
					$cost = $n['invoiceid'] ? self::unitCostOf('Invoice', $n['invoiceid'], $l['product']) : null;
					$moves[] = array('product' => $l['product'], 'line' => $l['line'], 'type' => 'Sale Return', 'qty' => $l['qty'], 'cost' => $cost ?? self::averageCostOn($l['product'], $date), 'date' => $date, 'mode' => 'fixed');
				}
			}
		}
		return $moves;
	}

	/**
	 * Brings the stock moves of a document up to date: creates, replaces or removes them, re-prices the
	 * affected products from the earliest changed date, and re-posts the journal entries that depend on
	 * those moves (cost of goods sold of other documents whose cost changed, and this document's own).
	 */
	public static function syncDocument($module, $id) {
		global $adb;
		if (!self::enabled() || !in_array($module, array('Invoice', 'PurchaseOrder', 'SalesOrder'))) {
			return;
		}
		include_once 'include/utils/LedgerUtils.php';
		$existing = self::movesOf($module, $id);
		$desired = self::desiredMoves($module, $id);
		$byKey = array();
		foreach ($existing as $m) {
			$byKey[$m['product_id'] . ':' . $m['source_line']] = $m;
		}
		$from = array(); // product => earliest date to re-price from
		$touch = function ($product, $date) use (&$from) {
			if (!isset($from[$product]) || $date < $from[$product]) {
				$from[$product] = $date;
			}
		};
		$kept = array();
		foreach ($desired as $d) {
			$key = $d['product'] . ':' . $d['line'];
			$old = $byKey[$key] ?? null;
			$date = $d['date'] ?: ($old ? $old['move_date'] : date('Y-m-d')); // a purchase is dated when it was received, and stays so
			if ($old && abs((float)$old['qty'] - $d['qty']) < 0.0005 && $old['move_date'] == $date && $old['move_type'] == $d['type']
					&& ($d['mode'] == 'avg' || abs((float)$old['unit_cost'] - $d['cost']) < 0.000001)) {
				$kept[$key] = true;
				continue;
			}
			if ($old) {
				$touch($old['product_id'], min($old['move_date'], $date));
				$adb->pquery('DELETE FROM vtiger_stock_moves WHERE move_id = ?', array($old['move_id']));
				$kept[$key] = true;
			}
			if (Vtiger_Ledger_Utils::isLocked($date)) {
				throw new Exception(Vtiger_Ledger_Utils::lockProblem($date));
			}
			$cost = $d['mode'] == 'fixed' ? $d['cost'] : 0;
			$adb->pquery('INSERT INTO vtiger_stock_moves (product_id, move_date, move_type, source_module, source_id, source_line, qty, unit_cost, value, cost_mode, created_time)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', array($d['product'], $date, $d['type'], $module, $id, $d['line'], $d['qty'], $cost, round($d['qty'] * $cost, 2), $d['mode']));
			$touch($d['product'], $date);
		}
		foreach ($byKey as $key => $old) {
			if (!isset($kept[$key])) {
				$touch($old['product_id'], $old['move_date']);
				$adb->pquery('DELETE FROM vtiger_stock_moves WHERE move_id = ?', array($old['move_id']));
			}
		}
		$changed = array();
		foreach ($from as $product => $date) {
			foreach (self::recompute($product, $date) as $source) {
				$changed[$source] = true;
			}
		}
		self::postSource($module, $id);
		foreach (array_keys($changed) as $source) {
			list($m, $i) = explode(':', $source);
			if (!($m == $module && $i == $id)) {
				self::postSource($m, $i);
			}
		}
	}

	/** Journal entries that come from a document's stock moves: cost of goods sold / returns, and (notes) the inventory credit. */
	private static function postSource($module, $id) {
		include_once 'include/utils/LedgerUtils.php';
		if ($module == 'Adjustment') {
			Vtiger_Ledger_Utils::syncStockAdjustment($id);
		} else {
			Vtiger_Ledger_Utils::syncCogs($module, $id);
			if ($module == 'SalesOrder') {
				// a debit note's entry credits inventory at the moves' value
				Vtiger_Ledger_Utils::syncDocument('SalesOrder', $id);
			}
		}
	}

	/**
	 * Recalculates the running balance and the average-cost moves of a product from a date on, and
	 * keeps vtiger's own "quantity in stock" equal to the balance. Returns the sources ("Module:id") whose
	 * moves got a different cost.
	 */
	public static function recompute($product, $fromDate = null) {
		global $adb;
		$q = 0.0;
		$v = 0.0;
		$avg = 0.0;
		if ($fromDate) {
			$prev = $adb->pquery('SELECT balance_qty, balance_value, avg_cost FROM vtiger_stock_moves WHERE product_id = ? AND move_date < ? ORDER BY move_date DESC, move_id DESC LIMIT 1', array($product, $fromDate));
			if ($adb->num_rows($prev)) {
				$q = (float)$adb->query_result($prev, 0, 'balance_qty');
				$v = (float)$adb->query_result($prev, 0, 'balance_value');
				$avg = (float)$adb->query_result($prev, 0, 'avg_cost');
			}
		}
		$sql = 'SELECT * FROM vtiger_stock_moves WHERE product_id = ?' . ($fromDate ? ' AND move_date >= ?' : '') . ' ORDER BY move_date, move_id';
		$moves = $adb->pquery($sql, $fromDate ? array($product, $fromDate) : array($product));
		$changed = array();
		while ($m = $adb->fetch_array($moves)) {
			$qty = (float)$m['qty'];
			if ($m['cost_mode'] == 'avg') {
				$unit = $q > 0.0005 ? $v / $q : $avg;
				$value = round($qty * $unit, 2);
				if (abs($unit - (float)$m['unit_cost']) > 0.00001 || abs($value - (float)$m['value']) > 0.004) {
					if ($m['source_module'] != 'Opening') {
						$changed[$m['source_module'] . ':' . $m['source_id']] = true;
					}
				}
			} else {
				$unit = (float)$m['unit_cost'];
				$value = round($qty * $unit, 2);
			}
			$q += $qty;
			$v = round($v + $value, 2);
			$avg = abs($q) > 0.0005 ? $v / $q : ($qty > 0 ? $unit : $avg);
			$adb->pquery('UPDATE vtiger_stock_moves SET unit_cost = ?, value = ?, balance_qty = ?, balance_value = ?, avg_cost = ? WHERE move_id = ?',
				array(round($unit, 6), $value, round($q, 3), $v, round($avg, 6), $m['move_id']));
		}
		// vtiger's own stock figure follows the stock ledger (its default workflow that adjusted it is switched off by the setup script)
		$adb->pquery('UPDATE vtiger_products SET qtyinstock = ? WHERE productid = ?', array(round($q, 3), $product));
		return array_keys($changed);
	}

	// ---- adjustments and opening stock ------------------------------------------------------------------

	/**
	 * Records a stock adjustment: a dated set of lines (product, quantity change, unit cost for additions) with
	 * a reason, posted against a ledger (stock write-offs, opening balances...). $type: Opening Stock,
	 * Stock Count Correction, Write-off, Damage, Found.
	 */
	public static function createAdjustment($date, $type, $narration, array $lines, $counterLedger = null) {
		global $adb, $current_user;
		include_once 'include/utils/LedgerUtils.php';
		$problem = Vtiger_Ledger_Utils::lockProblem($date);
		if ($problem !== null) {
			throw new Exception($problem);
		}
		$clean = array();
		foreach ($lines as $l) {
			$qty = (float)($l['qty'] ?? 0);
			if (in_array($type, array('Write-off', 'Damage'), true)) {
				$qty = -abs($qty); // these only ever take stock out
			} elseif ($type == 'Found' || $type == 'Opening Stock') {
				$qty = abs($qty);
			}
			if (empty($l['product']) || abs($qty) < 0.0005) {
				continue;
			}
			if (!self::isTracked($l['product'])) {
				throw new Exception('A product on the adjustment does not take part in stock (tick "Track stock" on it first).');
			}
			if ($qty > 0 && (!isset($l['cost']) || (float)$l['cost'] < 0)) {
				throw new Exception('Enter the cost per unit for the quantity added.');
			}
			$clean[] = array('product' => (int)$l['product'], 'qty' => $qty, 'cost' => (float)($l['cost'] ?? 0));
		}
		if (!$clean) {
			throw new Exception('Add at least one product with a quantity.');
		}
		if (!$counterLedger) {
			$counterLedger = $type == 'Opening Stock' ? Vtiger_Ledger_Utils::ledgerId('Opening Balance Equity', 'Equity') : Vtiger_Ledger_Utils::ledgerId('Stock Adjustments and Write-offs', 'Expenses');
		}
		$adb->pquery('INSERT INTO vtiger_stock_adjustments (adjustment_date, adjustment_type, narration, counter_ledger, created_by, created_time) VALUES (?, ?, ?, ?, ?, NOW())',
			array($date, $type, mb_substr($narration, 0, 200), $counterLedger, $current_user->id));
		$adjustmentId = $adb->getLastInsertID();
		$from = array();
		foreach ($clean as $i => $l) {
			$in = $l['qty'] > 0;
			$adb->pquery('INSERT INTO vtiger_stock_moves (product_id, move_date, move_type, source_module, source_id, source_line, qty, unit_cost, value, cost_mode, created_time)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', array($l['product'], $date, $type == 'Opening Stock' ? 'Opening' : 'Adjustment', 'Adjustment', $adjustmentId, $i + 1, $l['qty'], $in ? $l['cost'] : 0, $in ? round($l['qty'] * $l['cost'], 2) : 0, $in ? 'fixed' : 'avg'));
			$from[$l['product']] = isset($from[$l['product']]) ? min($from[$l['product']], $date) : $date;
		}
		$changed = array();
		foreach ($from as $product => $d) {
			foreach (self::recompute($product, $d) as $source) {
				$changed[$source] = true;
			}
		}
		self::postSource('Adjustment', $adjustmentId);
		foreach (array_keys($changed) as $source) {
			list($m, $i) = explode(':', $source);
			if (!($m == 'Adjustment' && $i == $adjustmentId)) {
				self::postSource($m, $i);
			}
		}
		return $adjustmentId;
	}

	// ---- reports ----------------------------------------------------------------------------------------

	/** Stock on hand and its value at the end of a day, per product with any stock history: rows of id, name, code, qty, avg_cost, value. */
	public static function valuation($asAt) {
		global $adb;
		$result = $adb->pquery("SELECT p.productid AS id, p.productname AS name, p.productcode AS code, x.balance_qty AS qty, x.avg_cost AS avg_cost, x.balance_value AS value
			FROM (SELECT m.product_id, m.balance_qty, m.avg_cost, m.balance_value,
					ROW_NUMBER() OVER (PARTITION BY m.product_id ORDER BY m.move_date DESC, m.move_id DESC) AS rn
				FROM vtiger_stock_moves m WHERE m.move_date <= ?) x
			INNER JOIN vtiger_products p ON p.productid = x.product_id
			INNER JOIN vtiger_crmentity c ON c.crmid = p.productid AND c.deleted = 0
			WHERE x.rn = 1 ORDER BY p.productname", array($asAt));
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$row['name'] = decode_html($row['name']);
			$rows[] = $row;
		}
		return $rows;
	}

	/** Total value (positive) of the moves of one document. */
	public static function movesValue($module, $id) {
		global $adb;
		if (!self::enabled()) {
			return 0.0;
		}
		$result = $adb->pquery('SELECT COALESCE(SUM(ABS(value)), 0) AS v FROM vtiger_stock_moves WHERE source_module = ? AND source_id = ?', array($module, $id));
		return round((float)$adb->query_result($result, 0, 'v'), 2);
	}

	/** The stock ledger of one product in a period: opening, moves with running balance, closing. */
	public static function ledger($product, $from, $to) {
		global $adb;
		$open = $adb->pquery('SELECT balance_qty, balance_value FROM vtiger_stock_moves WHERE product_id = ? AND move_date < ? ORDER BY move_date DESC, move_id DESC LIMIT 1', array($product, $from));
		$opening = array('qty' => $adb->num_rows($open) ? (float)$adb->query_result($open, 0, 0) : 0.0, 'value' => $adb->num_rows($open) ? (float)$adb->query_result($open, 0, 1) : 0.0);
		$result = $adb->pquery('SELECT * FROM vtiger_stock_moves WHERE product_id = ? AND move_date >= ? AND move_date <= ? ORDER BY move_date, move_id', array($product, $from, $to));
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$rows[] = $row;
		}
		$last = $rows ? end($rows) : null;
		return array('opening' => $opening, 'rows' => $rows,
			'closing' => array('qty' => $last ? (float)$last['balance_qty'] : $opening['qty'], 'value' => $last ? (float)$last['balance_value'] : $opening['value']));
	}

	/** Problems with the stock books: array(array(title, hint, rows => array(array(label, url)))). */
	public static function checks() {
		global $adb;
		$checks = array();
		$rowsOf = function ($result, $labelFn) use ($adb) {
			$rows = array();
			$count = 0;
			while ($row = $adb->fetch_array($result)) {
				$count++;
				if (count($rows) < 25) {
					$rows[] = array('label' => $labelFn($row), 'url' => 'index.php?module=Products&view=Detail&record=' . $row['id']);
				}
			}
			return array($count, $rows);
		};
		list($n, $rows) = $rowsOf($adb->pquery("SELECT p.productid AS id, p.productname AS name, x.balance_qty AS qty FROM (
				SELECT m.product_id, m.balance_qty, ROW_NUMBER() OVER (PARTITION BY m.product_id ORDER BY m.move_date DESC, m.move_id DESC) AS rn FROM vtiger_stock_moves m) x
			INNER JOIN vtiger_products p ON p.productid = x.product_id WHERE x.rn = 1 AND x.balance_qty < -0.0005 ORDER BY x.balance_qty LIMIT 500"),
			function ($r) { return decode_html($r['name']) . ' (' . $r['qty'] . ')'; });
		$checks[] = array('title' => 'Products with negative stock', 'hint' => 'More was sold or returned than was received. Receive the purchase, or correct the stock with an adjustment. Cost of goods sold for these sales uses the last known cost.', 'count' => $n, 'rows' => $rows);

		list($n, $rows) = $rowsOf($adb->pquery("SELECT p.productid AS id, p.productname AS name, p.qtyinstock AS qty FROM vtiger_products p
			INNER JOIN vtiger_crmentity c ON c.crmid = p.productid AND c.deleted = 0
			WHERE p.track_stock = 1 AND p.qtyinstock > 0 AND NOT EXISTS (SELECT 1 FROM vtiger_stock_moves m WHERE m.product_id = p.productid) LIMIT 500"),
			function ($r) { return decode_html($r['name']) . ' (' . $r['qty'] . ' in the old stock field)'; });
		$checks[] = array('title' => 'Stock quantities with no valuation', 'hint' => 'The product has a quantity in stock but no stock history, so it is not valued. Enter it as Opening Stock with its cost.', 'count' => $n, 'rows' => $rows);

		list($n, $rows) = $rowsOf($adb->pquery("SELECT p.productid AS id, p.productname AS name FROM vtiger_stock_moves m INNER JOIN vtiger_products p ON p.productid = m.product_id
			WHERE m.move_type IN ('Sale', 'Purchase Return') AND m.unit_cost = 0 AND m.qty < 0 GROUP BY p.productid, p.productname LIMIT 500"),
			function ($r) { return decode_html($r['name']); });
		$checks[] = array('title' => 'Sales at zero cost', 'hint' => 'Goods were sold before any stock with a cost was recorded; cost of goods sold is understated until opening stock or the purchase is entered (the sales are re-priced automatically).', 'count' => $n, 'rows' => $rows);

		include_once 'include/utils/LedgerUtils.php';
		$ledgerBalance = 0.0;
		$sums = Vtiger_Ledger_Utils::sumsByLedger(null, date('Y-m-d'), Vtiger_Ledger_Utils::ledgerId('Inventory', 'Assets'));
		foreach ($sums as $s) {
			$ledgerBalance += $s[0] - $s[1];
		}
		$stock = 0.0;
		foreach (self::valuation(date('Y-m-d')) as $row) {
			$stock += (float)$row['value'];
		}
		$diff = round($ledgerBalance - $stock, 2);
		$checks[] = array('title' => 'Inventory ledger agrees with the stock valuation', 'hint' => $diff == 0 ? 'The Inventory ledger balance equals the value of stock on hand.' : sprintf('Inventory ledger %s, stock valuation %s, difference %s. Run "Rebuild postings" (bin/create-journal.php) or check manual entries made to the Inventory ledger.', number_format($ledgerBalance, 2), number_format($stock, 2), number_format($diff, 2)), 'count' => $diff == 0 ? 0 : 1, 'rows' => array());
		return $checks;
	}
}
