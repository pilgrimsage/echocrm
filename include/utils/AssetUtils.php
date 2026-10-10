<?php
/**
 * Fixed assets: asset classes, monthly depreciation and the journal postings around an asset's life.
 *
 *   acquisition   Dr asset cost ledger | Cr accumulated depreciation (opening, for assets already in use) and the funding
 *                 ledger (payable, bank, opening balances...), or no entry when the purchase was already booked elsewhere
 *   depreciation  Dr depreciation expense | Cr accumulated depreciation, one entry per month for all assets (lines per class and cost centre)
 *   disposal      Dr proceeds (the ledger they were received in), Dr accumulated depreciation | Cr asset cost,
 *                 the difference to "Gain or Loss on Disposal of Assets"
 *
 * Depreciation is posted month by month up to the month chosen in the run; a month that was posted is never
 * changed. Straight line takes (cost - salvage) / life per month, declining balance takes the book value times
 * the annual rate / 12; neither goes below the salvage value. The terms come from the asset's class unless the
 * asset overrides them.
 *
 * Not covered: depreciation by days or half-year conventions, component accounting, revaluation and impairment,
 * tax (written-down value) books next to the accounting books, assets held in foreign currency.
 */
class Vtiger_Asset_Utils {

	// ---- classes ---------------------------------------------------------------------------------------

	/** Asset classes by name: array(name => array(class_id, class_name, asset_ledger, accum_ledger, expense_ledger, dep_method, life_years, dep_rate)). */
	public static function classes() {
		global $adb;
		$result = $adb->pquery('SELECT * FROM vtiger_asset_classes ORDER BY class_name');
		$classes = array();
		while ($row = $adb->fetch_array($result)) {
			$row['class_name'] = decode_html($row['class_name']);
			$classes[$row['class_name']] = $row;
		}
		return $classes;
	}

	public static function saveClass($id, $name, $assetLedger, $accumLedger, $expenseLedger, $method, $life, $rate) {
		global $adb;
		$name = trim($name);
		if ($name === '' || !in_array($method, array('SLM', 'WDV'), true)) {
			throw new Exception('A class needs a name and a depreciation method.');
		}
		if ($method == 'SLM' && (float)$life <= 0) {
			throw new Exception('Straight-line depreciation needs the useful life in years.');
		}
		if ($method == 'WDV' && ((float)$rate <= 0 || (float)$rate > 100)) {
			throw new Exception('Declining-balance depreciation needs an annual rate between 0 and 100.');
		}
		foreach (array($assetLedger, $accumLedger, $expenseLedger) as $ledger) {
			if (!Vtiger_Ledger_Utils::ledgerGroup($ledger)) {
				throw new Exception('Choose the asset, accumulated depreciation and depreciation expense ledgers.');
			}
		}
		if ($id) {
			$adb->pquery('UPDATE vtiger_asset_classes SET class_name = ?, asset_ledger = ?, accum_ledger = ?, expense_ledger = ?, dep_method = ?, life_years = ?, dep_rate = ? WHERE class_id = ?',
				array($name, $assetLedger, $accumLedger, $expenseLedger, $method, $life ?: null, $rate ?: null, $id));
		} else {
			$adb->pquery('INSERT INTO vtiger_asset_classes (class_name, asset_ledger, accum_ledger, expense_ledger, dep_method, life_years, dep_rate) VALUES (?,?,?,?,?,?,?)',
				array($name, $assetLedger, $accumLedger, $expenseLedger, $method, $life ?: null, $rate ?: null));
		}
	}

	/** Seeds the starting classes with their ledgers (existing ones are kept). */
	public static function seedClasses() {
		$defaults = array(
			// name => array(asset ledger, method, life years, rate)
			'Buildings' => array('Buildings', 'SLM', 30, null),
			'Plant and Machinery' => array('Plant and Machinery', 'WDV', null, 15),
			'Furniture and Fixtures' => array('Furniture and Fixtures', 'SLM', 10, null),
			'Computers and IT Equipment' => array('Computers and IT Equipment', 'SLM', 3, null),
			'Office Equipment' => array('Office Equipment', 'SLM', 5, null),
			'Vehicles' => array('Vehicles', 'WDV', null, 15),
			'Software and Intangibles' => array('Software and Intangibles', 'SLM', 3, null),
			'Land (not depreciated)' => array('Land', 'SLM', 1000, null),
		);
		$existing = self::classes();
		foreach ($defaults as $name => $d) {
			if (isset($existing[$name])) {
				continue;
			}
			$assetLedger = Vtiger_Ledger_Utils::ledgerId($d[0], 'Assets');
			$accum = Vtiger_Ledger_Utils::ledgerId('Accumulated Depreciation - ' . $d[0], 'Assets');
			$expense = Vtiger_Ledger_Utils::ledgerId('Depreciation Expense', 'Expenses');
			self::saveClass(0, $name, $assetLedger, $accum, $expense, $d[1], $d[2], $d[3]);
		}
	}

	// ---- reading an asset -------------------------------------------------------------------------------

	public static function getAsset($id) {
		global $adb;
		$result = $adb->pquery('SELECT a.*, c.deleted FROM vtiger_fixedassets a INNER JOIN vtiger_crmentity c ON c.crmid = a.fixedassetsid WHERE a.fixedassetsid = ?', array($id));
		if (!$adb->num_rows($result)) {
			return null;
		}
		$asset = $adb->fetch_array($result);
		$asset['asset_name'] = decode_html($asset['asset_name']);
		return $asset;
	}

	/** Depreciation terms of an asset: its own, else its class's: array(method, life years, rate, class row or null). */
	public static function terms(array $asset) {
		$class = self::classes()[decode_html((string)$asset['asset_class'])] ?? null;
		$method = !empty($asset['dep_method']) ? $asset['dep_method'] : ($class['dep_method'] ?? 'SLM');
		// the columns hold "0.00" when blank, which is not empty() in PHP: compare as numbers
		$life = (float)($asset['life_years'] ?? 0) > 0 ? (float)$asset['life_years'] : (float)($class['life_years'] ?? 0);
		$rate = (float)($asset['dep_rate'] ?? 0) > 0 ? (float)$asset['dep_rate'] : (float)($class['dep_rate'] ?? 0);
		return array($method, $life, $rate, $class);
	}

	/** Depreciation of one month given what is left to depreciate; never takes the book value below salvage. */
	public static function monthlyAmount(array $asset, $bookBefore) {
		list($method, $life, $rate) = self::terms($asset);
		$cost = (float)$asset['cost'];
		$salvage = (float)$asset['salvage_value'];
		if ($method == 'WDV') {
			$amount = $rate > 0 ? $bookBefore * $rate / 100 / 12 : 0;
		} else {
			$amount = $life > 0 ? ($cost - $salvage) / ($life * 12) : 0;
		}
		return round(max(0, min($amount, $bookBefore - $salvage)), 2);
	}

	// ---- figures ----------------------------------------------------------------------------------------

	/** Accumulated depreciation of an asset after a period (opening + posted rows up to it). */
	public static function accumulated($assetId, $period = null) {
		global $adb;
		$asset = self::getAsset($assetId);
		$sum = $adb->pquery('SELECT COALESCE(SUM(amount), 0) AS s FROM vtiger_asset_depreciation WHERE asset_id = ?' . ($period ? ' AND period <= ?' : ''), $period ? array($assetId, $period) : array($assetId));
		return round((float)$asset['opening_accumulated'] + (float)$adb->query_result($sum, 0, 's'), 2);
	}

	/** Keeps the asset's displayed accumulated depreciation and book value in step with the postings. */
	public static function refreshFigures($assetId) {
		global $adb;
		$asset = self::getAsset($assetId);
		if (!$asset) {
			return;
		}
		$accumulated = self::accumulated($assetId);
		$adb->pquery('UPDATE vtiger_fixedassets SET accumulated_dep = ?, book_value = ? WHERE fixedassetsid = ?', array($accumulated, round((float)$asset['cost'] - $accumulated, 2), $assetId));
	}

	private static function monthEnd($period) {
		return date('Y-m-t', strtotime($period . '-01'));
	}

	private static function nextPeriod($period) {
		return date('Y-m', strtotime($period . '-01 +1 month'));
	}

	// ---- depreciation run -------------------------------------------------------------------------------

	/**
	 * Depreciation still to be posted up to a month (inclusive): array(period => array(assets, total)), without posting.
	 */
	public static function preview($upTo, $onlyAsset = null) {
		return self::run($upTo, false, $onlyAsset);
	}

	/**
	 * Posts depreciation month by month up to a month. Returns array(period => array(assets, total)) of what was
	 * (or, for a preview, would be) posted. A month in a locked period stops the run with an exception that names it;
	 * the months before it stay posted.
	 */
	public static function post($upTo) {
		return self::run($upTo, true);
	}

	private static function run($upTo, $write, $onlyAsset = null) {
		global $adb;
		if (!preg_match('/^\d{4}-\d{2}$/', $upTo)) {
			throw new Exception('Choose the month to depreciate up to.');
		}
		$assets = array();
		$result = $adb->pquery("SELECT a.* FROM vtiger_fixedassets a INNER JOIN vtiger_crmentity c ON c.crmid = a.fixedassetsid AND c.deleted = 0
			WHERE a.cost > 0 AND a.asset_class IS NOT NULL AND a.asset_class != ''" . ($onlyAsset ? ' AND a.fixedassetsid = ' . (int)$onlyAsset : ''));
		$first = null;
		while ($asset = $adb->fetch_array($result)) {
			$asset['start'] = substr($asset['depreciation_from'] ?: $asset['purchase_date'], 0, 7);
			if (!$asset['start'] || $asset['start'] > $upTo) {
				continue;
			}
			$asset['end'] = ($asset['asset_status'] == 'Disposed' && $asset['disposal_date']) ? substr($asset['disposal_date'], 0, 7) : $upTo;
			$assets[$asset['fixedassetsid']] = $asset;
			$first = $first === null ? $asset['start'] : min($first, $asset['start']);
		}
		$done = array();
		if (!$assets) {
			return $done;
		}
		// a simulated run keeps its own running totals instead of reading the rows it did not write
		$simulated = array();
		for ($period = $first; $period <= $upTo; $period = self::nextPeriod($period)) {
			$rows = array();
			foreach ($assets as $id => $asset) {
				if ($period < $asset['start'] || $period > min($asset['end'], $upTo)) {
					continue;
				}
				$have = $adb->pquery('SELECT 1 FROM vtiger_asset_depreciation WHERE asset_id = ? AND period = ?', array($id, $period));
				if ($adb->num_rows($have)) {
					continue;
				}
				$accumulated = isset($simulated[$id]) ? $simulated[$id] : self::accumulated($id);
				$book = (float)$asset['cost'] - $accumulated;
				$amount = self::monthlyAmount($asset, $book);
				if ($amount < 0.005) {
					continue;
				}
				$rows[$id] = array('amount' => $amount, 'accumulated' => round($accumulated + $amount, 2), 'book' => round($book - $amount, 2));
				$simulated[$id] = $accumulated + $amount;
			}
			if (!$rows) {
				continue;
			}
			$done[$period] = array('assets' => count($rows), 'total' => round(array_sum(array_column($rows, 'amount')), 2));
			if (!$write) {
				continue;
			}
			$problem = Vtiger_Ledger_Utils::lockProblem(self::monthEnd($period));
			if ($problem !== null) {
				throw new Exception("Depreciation for $period cannot be posted: $problem");
			}
			foreach ($rows as $id => $r) {
				$adb->pquery('INSERT INTO vtiger_asset_depreciation (asset_id, period, amount, accumulated_after, book_after) VALUES (?, ?, ?, ?, ?)',
					array($id, $period, $r['amount'], $r['accumulated'], $r['book']));
			}
			self::syncDepreciationEntry($period);
			foreach (array_keys($rows) as $id) {
				self::refreshFigures($id);
			}
		}
		return $done;
	}

	/** The journal entry of one month's depreciation: lines per class and cost centre. */
	public static function syncDepreciationEntry($period) {
		global $adb;
		$classes = self::classes();
		$byId = array();
		foreach ($classes as $c) {
			$byId[$c['class_name']] = $c;
		}
		$result = $adb->pquery('SELECT a.asset_class, a.cost_centre, SUM(d.amount) AS amount FROM vtiger_asset_depreciation d
			INNER JOIN vtiger_fixedassets a ON a.fixedassetsid = d.asset_id WHERE d.period = ? GROUP BY a.asset_class, a.cost_centre', array($period));
		$groups = array();
		while ($row = $adb->fetch_array($result)) {
			$class = $byId[decode_html($row['asset_class'])] ?? null;
			if ($class && (float)$row['amount'] > 0) {
				$groups[] = array('class' => $class, 'cost' => $row['cost_centre'] ?: null, 'amount' => round((float)$row['amount'], 2));
			}
		}
		Vtiger_Ledger_Utils::syncGroupedEntry('Depreciation', (int)str_replace('-', '', $period), 'dep', self::monthEnd($period), 'Depreciation ' . $period,
			$groups, function ($g) {
				return array(
					array('ledger' => $g['class']['expense_ledger'], 'debit' => $g['amount'], 'credit' => 0, 'cost' => $g['cost']),
					array('ledger' => $g['class']['accum_ledger'], 'debit' => 0, 'credit' => $g['amount'], 'cost' => $g['cost']),
				);
			});
	}

	// ---- acquisition and disposal -----------------------------------------------------------------------------

	/** Acquisition entry (and disposal entry) of an asset, from its record. */
	public static function syncAsset($assetId) {
		$asset = self::getAsset($assetId);
		if (!$asset) {
			return;
		}
		list(, , , $class) = self::terms($asset);
		$cost = round((float)$asset['cost'], 2);
		$live = !$asset['deleted'] && $class && $cost > 0;

		// acquisition
		$lines = array();
		$date = $asset['purchase_date'];
		if ($live && $asset['acquisition_posting'] != 'Already booked' && $date) {
			$opening = min($cost, round((float)$asset['opening_accumulated'], 2));
			$funding = $asset['funding_ledger'] ?: Vtiger_Ledger_Utils::ledgerId('Opening Balance Equity', 'Equity');
			$lines[] = array('ledger' => $class['asset_ledger'], 'debit' => $cost, 'credit' => 0);
			if ($opening > 0) {
				$lines[] = array('ledger' => $class['accum_ledger'], 'debit' => 0, 'credit' => $opening);
			}
			$lines[] = array('ledger' => $funding, 'debit' => 0, 'credit' => round($cost - $opening, 2));
		}
		Vtiger_Ledger_Utils::syncPlainEntry('FixedAsset', $assetId, 'acq', $date ?: date('Y-m-d'), 'Asset ' . $asset['asset_no'] . ' ' . $asset['asset_name'], $lines, $asset['cost_centre'] ?: null);

		// disposal
		$lines = array();
		$disposalDate = $asset['disposal_date'];
		if ($live && $asset['asset_status'] == 'Disposed' && $disposalDate) {
			$accumulated = self::accumulated($assetId);
			$book = round($cost - $accumulated, 2);
			$proceeds = round((float)$asset['sale_amount'], 2);
			$proceedsLedger = $asset['sale_ledger'] ?: Vtiger_Ledger_Utils::ledgerId('Cash in Hand', 'Assets');
			$gainLoss = Vtiger_Ledger_Utils::ledgerId('Gain or Loss on Disposal of Assets', 'Income');
			if ($proceeds > 0) {
				$lines[] = array('ledger' => $proceedsLedger, 'debit' => $proceeds, 'credit' => 0);
			}
			if ($accumulated > 0) {
				$lines[] = array('ledger' => $class['accum_ledger'], 'debit' => $accumulated, 'credit' => 0);
			}
			$lines[] = array('ledger' => $class['asset_ledger'], 'debit' => 0, 'credit' => $cost);
			$gain = round($proceeds - $book, 2);
			$lines[] = array('ledger' => $gainLoss, 'debit' => $gain < 0 ? -$gain : 0, 'credit' => $gain > 0 ? $gain : 0);
		}
		Vtiger_Ledger_Utils::syncPlainEntry('FixedAsset', $assetId, 'disposal', $disposalDate ?: date('Y-m-d'), 'Disposal of asset ' . $asset['asset_no'] . ' ' . $asset['asset_name'], $lines, $asset['cost_centre'] ?: null);
		self::refreshFigures($assetId);
	}

	// ---- reports -----------------------------------------------------------------------------------------------

	/**
	 * The asset register as at a date: one row per asset that existed then, with cost, accumulated depreciation and
	 * book value, plus totals per class and a comparison with the ledgers.
	 */
	public static function register($asAt) {
		global $adb;
		$period = substr($asAt, 0, 7);
		$result = $adb->pquery("SELECT a.*, COALESCE(d.s, 0) AS dep FROM vtiger_fixedassets a INNER JOIN vtiger_crmentity c ON c.crmid = a.fixedassetsid AND c.deleted = 0
			LEFT JOIN (SELECT asset_id, SUM(amount) AS s FROM vtiger_asset_depreciation WHERE period <= ? GROUP BY asset_id) d ON d.asset_id = a.fixedassetsid
			WHERE a.purchase_date <= ? ORDER BY a.asset_class, a.asset_name", array($period, $asAt));
		$rows = array();
		$classes = array();
		while ($a = $adb->fetch_array($result)) {
			$disposed = $a['asset_status'] == 'Disposed' && $a['disposal_date'] && $a['disposal_date'] <= $asAt;
			$cost = $disposed ? 0.0 : (float)$a['cost'];
			$accumulated = $disposed ? 0.0 : round((float)$a['opening_accumulated'] + (float)$a['dep'], 2);
			$row = array('id' => $a['fixedassetsid'], 'no' => $a['asset_no'], 'name' => $a['asset_name'], 'class' => decode_html($a['asset_class']), 'purchase_date' => $a['purchase_date'],
				'status' => $disposed ? 'Disposed' : 'In Service', 'cost' => round($cost, 2), 'accumulated' => $accumulated, 'book' => round($cost - $accumulated, 2));
			$rows[] = $row;
			$classes[$row['class']]['cost'] = ($classes[$row['class']]['cost'] ?? 0) + $row['cost'];
			$classes[$row['class']]['accumulated'] = ($classes[$row['class']]['accumulated'] ?? 0) + $row['accumulated'];
			$classes[$row['class']]['book'] = ($classes[$row['class']]['book'] ?? 0) + $row['book'];
		}
		// compare with the ledgers the classes post to
		$defs = self::classes();
		foreach ($classes as $name => &$c) {
			$c['ledger_cost'] = $c['ledger_accumulated'] = null;
			if (isset($defs[$name])) {
				$cost = Vtiger_Ledger_Utils::sumsByLedger(null, $asAt, $defs[$name]['asset_ledger']);
				$acc = Vtiger_Ledger_Utils::sumsByLedger(null, $asAt, $defs[$name]['accum_ledger']);
				$c['ledger_cost'] = round(($cost[$defs[$name]['asset_ledger']][0] ?? 0) - ($cost[$defs[$name]['asset_ledger']][1] ?? 0), 2);
				$c['ledger_accumulated'] = round(($acc[$defs[$name]['accum_ledger']][1] ?? 0) - ($acc[$defs[$name]['accum_ledger']][0] ?? 0), 2);
			}
			$c['cost'] = round($c['cost'], 2);
			$c['accumulated'] = round($c['accumulated'], 2);
			$c['book'] = round($c['book'], 2);
		}
		unset($c);
		return array('rows' => $rows, 'classes' => $classes);
	}

	/** Months of depreciation still unposted up to a month: used by the year-end checklist. */
	public static function unpostedUpTo($period) {
		return self::preview($period);
	}
}
