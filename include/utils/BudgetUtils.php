<?php
/**
 * Budgets: a plan per financial year, by income or expense ledger and optionally by cost centre / project,
 * with an amount for each of the 12 months of the year. A year can have several versions (Original,
 * Revised...); the one marked Active is the one reports and the spending control use.
 *
 * Actuals come from the books (closing entries left out). A line with a cost centre compares with what was
 * posted to that centre and everything below it; a line without one compares with the whole ledger.
 *
 * Spending control (Accounting Settings): when set to "Block", a manual journal entry or a bank transaction
 * that would take an expense ledger over its annual budget is refused. Purchase orders and other documents
 * are not blocked, only reported.
 *
 * Not covered: budgets for balance sheet items or cash flow, quarterly approval workflows, rolling forecasts,
 * budget transfers between lines.
 */
include_once 'include/utils/LedgerUtils.php';
include_once 'include/utils/FinancialYears.php';

class Vtiger_Budget_Utils {

	/** The 12 months of a financial year as 'YYYY-MM', first month first. */
	public static function months($yearStart) {
		$months = array();
		for ($i = 0; $i < 12; $i++) {
			$months[] = date('Y-m', strtotime(date('Y-m-01', strtotime($yearStart)) . " +$i months"));
		}
		return $months;
	}

	public static function budget($id) {
		global $adb;
		$result = $adb->pquery('SELECT * FROM vtiger_budgets WHERE budget_id = ?', array($id));
		return $adb->num_rows($result) ? $adb->fetch_array($result) : null;
	}

	public static function budgets() {
		global $adb;
		$result = $adb->pquery('SELECT b.*, (SELECT COUNT(*) FROM vtiger_budget_lines l WHERE l.budget_id = b.budget_id) AS lines FROM vtiger_budgets b ORDER BY b.year_start DESC, b.budget_id DESC');
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$row['budget_name'] = decode_html($row['budget_name']);
			$year = Vtiger_Financial_Years::yearOf($row['year_start']);
			$row['label'] = $year['label'];
			$rows[] = $row;
		}
		return $rows;
	}

	/** The active budget of the financial year a date falls in, or null. */
	public static function activeFor($date) {
		global $adb;
		$year = Vtiger_Financial_Years::yearOf($date);
		$result = $adb->pquery("SELECT * FROM vtiger_budgets WHERE year_start = ? AND status = 'Active' LIMIT 1", array($year['start']));
		return $adb->num_rows($result) ? $adb->fetch_array($result) : null;
	}

	public static function create($name, $yearStart, $copy = 'blank', $growth = 0, $fromBudget = 0) {
		global $adb, $current_user;
		$name = trim($name);
		if ($name === '') {
			throw new Exception('Give the budget a name.');
		}
		$year = Vtiger_Financial_Years::yearOf($yearStart);
		$adb->pquery("INSERT INTO vtiger_budgets (budget_name, year_start, status, created_by, created_time) VALUES (?, ?, 'Draft', ?, NOW())", array($name, $year['start'], $current_user->id));
		$id = $adb->getLastInsertID();
		if ($copy == 'actuals') {
			self::fillFromActuals($id, $growth);
		} elseif ($copy == 'budget' && $fromBudget) {
			self::copyLines($fromBudget, $id, $growth);
		}
		return $id;
	}

	/** Copies the lines of another budget, scaled by a growth percentage (positive or negative). */
	public static function copyLines($fromId, $toId, $growth = 0) {
		global $adb;
		$factor = 1 + (float)$growth / 100;
		$result = $adb->pquery('SELECT * FROM vtiger_budget_lines WHERE budget_id = ?', array($fromId));
		while ($line = $adb->fetch_array($result)) {
			$values = array();
			for ($m = 1; $m <= 12; $m++) {
				$values[] = round((float)$line["m$m"] * $factor, 2);
			}
			$adb->pquery('INSERT INTO vtiger_budget_lines (budget_id, ledger_id, cost_centre_id, m1, m2, m3, m4, m5, m6, m7, m8, m9, m10, m11, m12) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				array_merge(array($toId, $line['ledger_id'], $line['cost_centre_id']), $values));
		}
	}

	/** Fills a budget with last year's actual income and expenses per ledger and month, scaled by a growth percentage. */
	public static function fillFromActuals($budgetId, $growth = 0) {
		global $adb;
		$budget = self::budget($budgetId);
		$months = self::months($budget['year_start']);
		$previous = self::months(date('Y-m-d', strtotime($budget['year_start'] . ' -1 year')));
		$factor = 1 + (float)$growth / 100;
		$perLedger = array();
		foreach ($previous as $i => $month) {
			$from = $month . '-01';
			$to = date('Y-m-t', strtotime($from));
			foreach (Vtiger_Ledger_Utils::ledgerTotals($to, $from, null, false) as $row) {
				if ($row['grp'] != 'Income' && $row['grp'] != 'Expenses') {
					continue;
				}
				$amount = Vtiger_Ledger_Utils::normalBalance($row);
				if (abs($amount) >= 0.005) {
					$perLedger[$row['id']][$i + 1] = round($amount * $factor, 2);
				}
			}
		}
		foreach ($perLedger as $ledger => $byMonth) {
			$values = array();
			for ($m = 1; $m <= 12; $m++) {
				$values[] = max(0, $byMonth[$m] ?? 0);
			}
			$adb->pquery('INSERT INTO vtiger_budget_lines (budget_id, ledger_id, cost_centre_id, m1, m2, m3, m4, m5, m6, m7, m8, m9, m10, m11, m12) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				array_merge(array($budgetId, $ledger), $values));
		}
		return count($perLedger);
	}

	public static function lines($budgetId) {
		global $adb;
		$result = $adb->pquery('SELECT l.*, g.ledger_name, g.ledger_group, c.costcentre_name FROM vtiger_budget_lines l
			LEFT JOIN vtiger_ledgers g ON g.ledgersid = l.ledger_id LEFT JOIN vtiger_costcentres c ON c.costcentresid = l.cost_centre_id
			WHERE l.budget_id = ? ORDER BY g.ledger_group DESC, g.ledger_name, c.costcentre_name', array($budgetId));
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$row['ledger_name'] = decode_html($row['ledger_name']);
			$row['costcentre_name'] = decode_html((string)$row['costcentre_name']);
			$row['total'] = 0.0;
			for ($m = 1; $m <= 12; $m++) {
				$row['total'] += (float)$row["m$m"];
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/** Replaces the lines of a budget with the submitted ones: array of array(ledger, cost, m => array(1..12)). */
	public static function saveLines($budgetId, array $lines) {
		global $adb;
		$budget = self::budget($budgetId);
		if (!$budget) {
			throw new Exception('The budget does not exist.');
		}
		$clean = array();
		$seen = array();
		foreach ($lines as $line) {
			$ledger = (int)($line['ledger'] ?? 0);
			if (!$ledger) {
				continue;
			}
			$group = Vtiger_Ledger_Utils::ledgerGroup($ledger);
			if (!in_array($group, array('Income', 'Expenses'), true)) {
				throw new Exception('Budgets are for income and expense ledgers only.');
			}
			$cost = !empty($line['cost']) ? (int)$line['cost'] : null;
			$key = $ledger . ':' . (int)$cost;
			if (isset($seen[$key])) {
				throw new Exception('The same ledger and cost centre appear twice in the budget.');
			}
			$seen[$key] = true;
			$values = array();
			for ($m = 1; $m <= 12; $m++) {
				$v = (float)str_replace(',', '', (string)($line['m'][$m] ?? 0));
				if ($v < 0) {
					throw new Exception('Budget amounts cannot be negative.');
				}
				$values[] = round($v, 2);
			}
			$clean[] = array_merge(array($budgetId, $ledger, $cost), $values);
		}
		$adb->pquery('DELETE FROM vtiger_budget_lines WHERE budget_id = ?', array($budgetId));
		foreach ($clean as $row) {
			$adb->pquery('INSERT INTO vtiger_budget_lines (budget_id, ledger_id, cost_centre_id, m1, m2, m3, m4, m5, m6, m7, m8, m9, m10, m11, m12) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $row);
		}
		return count($clean);
	}

	public static function activate($budgetId) {
		global $adb;
		$budget = self::budget($budgetId);
		if (!$budget) {
			throw new Exception('The budget does not exist.');
		}
		$adb->pquery("UPDATE vtiger_budgets SET status = 'Archived' WHERE year_start = ? AND status = 'Active'", array($budget['year_start']));
		$adb->pquery("UPDATE vtiger_budgets SET status = 'Active' WHERE budget_id = ?", array($budgetId));
	}

	public static function delete($budgetId) {
		global $adb;
		$adb->pquery('DELETE FROM vtiger_budget_lines WHERE budget_id = ?', array($budgetId));
		$adb->pquery('DELETE FROM vtiger_budgets WHERE budget_id = ?', array($budgetId));
	}

	// ---- budget against actual ---------------------------------------------------------------------------------

	/** Actual normal-direction balance per ledger between two dates for a cost centre tree (or the whole ledger), closing entries left out. */
	private static function actuals($from, $to, $costCentre) {
		$sums = Vtiger_Ledger_Utils::sumsByLedger($from, $to, null, $costCentre ? Vtiger_Ledger_Utils::costCentreTree($costCentre) : null, false);
		$out = array();
		foreach ($sums as $ledger => $s) {
			$group = Vtiger_Ledger_Utils::ledgerGroup($ledger);
			$out[$ledger] = Vtiger_Ledger_Utils::isDebitGroup($group) ? $s[0] - $s[1] : $s[1] - $s[0];
		}
		return $out;
	}

	/**
	 * Budget against actual for months fromIndex..toIndex (1-12) of the budget's year, per line, with totals.
	 * $costCentre limits the report to lines of that centre. Returns array(lines, income, expenses, net).
	 */
	public static function report($budgetId, $fromIndex, $toIndex, $costCentre = null) {
		$budget = self::budget($budgetId);
		$months = self::months($budget['year_start']);
		$fromIndex = max(1, min(12, (int)$fromIndex));
		$toIndex = max($fromIndex, min(12, (int)$toIndex));
		$from = $months[$fromIndex - 1] . '-01';
		$to = date('Y-m-t', strtotime($months[$toIndex - 1] . '-01'));
		$yearFrom = $months[0] . '-01';
		$yearTo = date('Y-m-t', strtotime($months[11] . '-01'));

		$cache = array();
		$actualFor = function ($f, $t, $cost) use (&$cache) {
			$key = $f . $t . (int)$cost;
			if (!isset($cache[$key])) {
				$cache[$key] = self::actuals($f, $t, $cost);
			}
			return $cache[$key];
		};
		$lines = array();
		$totals = array('Income' => array('budget' => 0.0, 'actual' => 0.0, 'annual' => 0.0), 'Expenses' => array('budget' => 0.0, 'actual' => 0.0, 'annual' => 0.0));
		foreach (self::lines($budgetId) as $line) {
			if ($costCentre && (int)$line['cost_centre_id'] != (int)$costCentre) {
				continue;
			}
			$periodBudget = 0.0;
			for ($m = $fromIndex; $m <= $toIndex; $m++) {
				$periodBudget += (float)$line["m$m"];
			}
			$actual = $actualFor($from, $to, $line['cost_centre_id'])[$line['ledger_id']] ?? 0.0;
			$yearActual = $actualFor($yearFrom, $yearTo, $line['cost_centre_id'])[$line['ledger_id']] ?? 0.0;
			$income = $line['ledger_group'] == 'Income';
			// variance is favourable when income is above plan or expenses are below it
			$variance = $income ? $actual - $periodBudget : $periodBudget - $actual;
			$lines[] = array(
				'ledger_id' => $line['ledger_id'], 'ledger' => $line['ledger_name'], 'group' => $line['ledger_group'], 'cost' => $line['costcentre_name'], 'cost_id' => $line['cost_centre_id'],
				'budget' => round($periodBudget, 2), 'actual' => round($actual, 2), 'variance' => round($variance, 2),
				'percent' => $periodBudget > 0 ? round(100 * $actual / $periodBudget) : null,
				'annual' => round($line['total'], 2), 'annual_used' => $line['total'] > 0 ? round(100 * $yearActual / $line['total']) : null,
			);
			$totals[$line['ledger_group']]['budget'] += $periodBudget;
			$totals[$line['ledger_group']]['actual'] += $actual;
			$totals[$line['ledger_group']]['annual'] += $line['total'];
		}
		foreach ($totals as &$t) {
			foreach ($t as &$v) {
				$v = round($v, 2);
			}
		}
		unset($t, $v);
		return array('budget' => $budget, 'lines' => $lines, 'totals' => $totals, 'from' => $from, 'to' => $to, 'months' => $months,
			'net_budget' => round($totals['Income']['budget'] - $totals['Expenses']['budget'], 2), 'net_actual' => round($totals['Income']['actual'] - $totals['Expenses']['actual'], 2));
	}

	// ---- spending control ----------------------------------------------------------------------------------------

	public static function controlMode() {
		return Vtiger_Ledger_Utils::getSetting('budget_control') == 'block' ? 'block' : 'off';
	}

	/**
	 * Why spending $amount on an expense ledger (optionally for a cost centre) on a date would break the annual
	 * budget, or null: the control is off, there is no active budget or line, or it still fits.
	 */
	public static function overBudgetProblem($ledgerId, $costCentre, $date, $amount) {
		if (self::controlMode() != 'block' || $amount <= 0.004) {
			return null;
		}
		$date = Vtiger_Ledger_Utils::dbDate($date) ?: date('Y-m-d');
		if (Vtiger_Ledger_Utils::ledgerGroup($ledgerId) != 'Expenses') {
			return null;
		}
		$budget = self::activeFor($date);
		if (!$budget) {
			return null;
		}
		$lines = self::lines($budget['budget_id']);
		$line = null;
		foreach ($lines as $l) {
			if ($l['ledger_id'] == $ledgerId && $costCentre && (int)$l['cost_centre_id'] == (int)$costCentre) {
				$line = $l;
				break;
			}
		}
		if (!$line) {
			foreach ($lines as $l) {
				if ($l['ledger_id'] == $ledgerId && empty($l['cost_centre_id'])) {
					$line = $l;
					break;
				}
			}
		}
		if (!$line) {
			return null;
		}
		$year = Vtiger_Financial_Years::yearOf($date);
		$actual = self::actuals($year['start'], $year['end'], $line['cost_centre_id'])[$ledgerId] ?? 0.0;
		if ($actual + $amount - $line['total'] > 0.004) {
			return sprintf("This would take %s%s to %s against a budget of %s for %s (budget '%s').", $line['ledger_name'], $line['costcentre_name'] ? ' / ' . $line['costcentre_name'] : '',
				number_format($actual + $amount, 2), number_format($line['total'], 2), $year['label'], decode_html($budget['budget_name']));
		}
		return null;
	}
}
