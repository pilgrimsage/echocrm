<?php
/**
 * A dashboard widget a user builds without code: pick a module and one of its saved filters, then
 * show the number of records, a total / average / smallest / largest of a numeric field, or the same
 * numbers grouped by a field as bars or a ring. The settings are stored as JSON in the widget's row
 * (vtiger_module_dashboard_widgets.data); the records come from the saved filter through the
 * normal query generator, so the user only ever sees records they may see.
 *
 * Data keys: module, filterid, type (number | bar | donut), metric (count | sum | avg | min | max),
 * field (numeric field for every metric but count), groupby (field, for bar and donut), limit, title.
 */
class Vtiger_CustomWidget_Model extends Vtiger_Base_Model {

	const TYPES = array('number', 'bar', 'donut');
	const METRICS = array('count', 'sum', 'avg', 'min', 'max');
	// numeric field types a metric can use / field types the records can be grouped by
	const NUMERIC_UITYPES = array(7, 9, 71, 72);
	const CURRENCY_UITYPES = array(71, 72);
	const GROUP_UITYPES = array(15, 16, 53, 52, 56, 5, 6, 23, 70);
	const OWNER_UITYPES = array(53, 52);
	const DATE_UITYPES = array(5, 6, 23, 70);

	/** Clean settings from a form or a stored widget; throws on anything that cannot work. */
	public static function normalize(array $input) {
		$module = (string)($input['module'] ?? '');
		$moduleModel = $module !== '' ? Vtiger_Module_Model::getInstance($module) : null;
		if (!$moduleModel || !$moduleModel->isEntityModule() || !vtlib_isModuleActive($module)) {
			throw new Exception(vtranslate('LBL_CUSTOM_WIDGET_CHOOSE_MODULE', 'Vtiger'));
		}
		$data = array(
			'module' => $module,
			'filterid' => (int)($input['filterid'] ?? 0),
			'type' => in_array($input['type'] ?? '', self::TYPES, true) ? $input['type'] : 'number',
			'metric' => in_array($input['metric'] ?? '', self::METRICS, true) ? $input['metric'] : 'count',
			'field' => (string)($input['field'] ?? ''),
			'groupby' => (string)($input['groupby'] ?? ''),
			'limit' => max(2, min(20, (int)($input['limit'] ?? 8))),
			'title' => trim(strip_tags((string)($input['title'] ?? ''))),
		);
		if ($data['filterid'] <= 0) {
			throw new Exception(vtranslate('LBL_CUSTOM_WIDGET_CHOOSE_FILTER', 'Vtiger'));
		}
		if ($data['metric'] !== 'count') {
			$field = $moduleModel->getField($data['field']);
			if (!$field || !in_array((int)$field->get('uitype'), self::NUMERIC_UITYPES, true)) {
				throw new Exception(vtranslate('LBL_CUSTOM_WIDGET_CHOOSE_FIELD', 'Vtiger'));
			}
		} else {
			$data['field'] = '';
		}
		if ($data['type'] === 'number') {
			$data['groupby'] = '';
		} else {
			$group = $moduleModel->getField($data['groupby']);
			if (!$group || !in_array((int)$group->get('uitype'), self::GROUP_UITYPES, true)) {
				throw new Exception(vtranslate('LBL_CUSTOM_WIDGET_CHOOSE_GROUP', 'Vtiger'));
			}
		}
		if ($data['title'] === '') {
			$data['title'] = ucfirst($data['metric']) . ' ' . vtranslate($module, $module);
		}
		return $data;
	}

	/** The settings of a stored widget (the data column holds JSON). */
	public static function settingsOf($widgetModel) {
		$data = $widgetModel->get('data');
		// a row may hold the object itself or, from an older save, the object as a JSON string
		for ($i = 0; $i < 2 && is_string($data); $i++) {
			$data = Zend_Json::decode(decode_html($data));
		}
		if (!is_array($data) || empty($data['module'])) {
			throw new Exception('Invalid data');
		}
		return $data;
	}

	/** Fields of a module the widget can offer, for the form: array('numeric' => ..., 'groupable' => ...). */
	public static function fieldOptions($module) {
		$moduleModel = Vtiger_Module_Model::getInstance($module);
		$numeric = $groupable = array();
		if ($moduleModel) {
			foreach ($moduleModel->getFields() as $name => $field) {
				if (!$field->isViewEnabled() || !$field->isActiveField()) {
					continue;
				}
				$uitype = (int)$field->get('uitype');
				$entry = array('name' => $name, 'label' => vtranslate($field->get('label'), $module));
				if (in_array($uitype, self::NUMERIC_UITYPES, true)) {
					$numeric[] = $entry;
				}
				if (in_array($uitype, self::GROUP_UITYPES, true)) {
					$groupable[] = $entry;
				}
			}
		}
		return array('numeric' => $numeric, 'groupable' => $groupable);
	}

	/** The saved filters of a module, grouped (Mine / Shared / All) for the form. */
	public static function filterOptions($module) {
		$groups = array();
		foreach (CustomView_Record_Model::getAllByGroup($module, false) as $group => $filters) {
			$items = array();
			foreach ($filters as $filter) {
				$items[] = array('id' => (int)$filter->getId(), 'name' => decode_html($filter->get('viewname')));
			}
			if ($items) {
				$groups[] = array('group' => vtranslate($group, 'CustomView'), 'filters' => $items);
			}
		}
		return $groups;
	}

	/**
	 * Run the widget. Returns array('kind' => 'number', 'value' => ..., 'records' => n, 'currency' => bool)
	 * or array('kind' => 'groups', 'rows' => array(label, value, share), 'total' => ..., 'currency' => bool),
	 * or array('error' => text) when the user may not see the module any more.
	 */
	public static function compute(array $settings) {
		global $adb;
		$module = $settings['module'];
		$user = Users_Record_Model::getCurrentUserModel();
		$privileges = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		if (!vtlib_isModuleActive($module) || !$privileges->hasModulePermission(getTabid($module))) {
			return array('error' => vtranslate('LBL_PERMISSION_DENIED'));
		}
		$generator = new EnhancedQueryGenerator($module, $user);
		$generator->initForCustomViewById($settings['filterid']);
		$fieldNames = array('id');
		if ($settings['metric'] !== 'count') {
			$fieldNames[] = $settings['field'];
		}
		if ($settings['type'] !== 'number') {
			$fieldNames[] = $settings['groupby'];
		}
		$generator->setFields($fieldNames);
		$moduleFields = $generator->getModuleFields();

		$query = $generator->getQuery();
		if ($module == 'Calendar') {
			$query = str_replace(' WHERE ', " WHERE vtiger_crmentity.setype = 'Calendar' AND ", $query);
		}
		$from = substr($query, stripos($query, ' FROM '));

		$currency = false;
		if ($settings['metric'] === 'count') {
			$aggregate = 'COUNT(*)';
		} else {
			$webserviceField = $moduleFields[$settings['field']];
			$table = $webserviceField->getTableName();
			$expression = $table . '.' . $webserviceField->getColumnName();
			$uitype = (int)$webserviceField->getUIType();
			$currency = in_array($uitype, self::CURRENCY_UITYPES, true);
			// documents carry the rate they were booked at: add them up in the base currency
			if ($currency && in_array('conversion_rate', $adb->getColumnNames($table))) {
				$expression = "($expression / IF($table.conversion_rate > 0, $table.conversion_rate, 1))";
			}
			$aggregate = strtoupper($settings['metric']) . '(' . $expression . ')';
		}

		if ($settings['type'] === 'number') {
			$result = $adb->pquery("SELECT $aggregate AS v, COUNT(*) AS n $from", array());
			$row = $adb->fetch_array($result);
			$value = (float)($row['v'] ?? 0);
			$records = (int)($row['n'] ?? 0);
			$caption = vtranslate('LBL_CUSTOM_WIDGET_METRIC_' . $settings['metric'], 'Vtiger');
			if ($settings['field'] !== '') {
				$caption .= ' ' . vtranslate($webserviceField->getFieldLabelKey(), $module);
			}
			return array('kind' => 'number', 'value' => $value, 'display' => self::format($value, $currency, $settings['metric'] === 'count'), 'caption' => $caption . ' · ' . $records . ' ' . vtranslate('LBL_CUSTOM_WIDGET_RECORDS', 'Vtiger'));
		}

		$groupField = $moduleFields[$settings['groupby']];
		$groupUitype = (int)$groupField->getUIType();
		$groupExpression = $groupField->getTableName() . '.' . $groupField->getColumnName();
		if (in_array($groupUitype, self::DATE_UITYPES, true)) {
			$groupExpression = "DATE_FORMAT($groupExpression, '%Y-%m')";
		}
		$order = in_array($groupUitype, self::DATE_UITYPES, true) ? 'g DESC' : 'v DESC';
		$result = $adb->pquery("SELECT $groupExpression AS g, $aggregate AS v $from GROUP BY g ORDER BY $order LIMIT 200", array());
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$rows[] = array('label' => self::groupLabel($row['g'], $groupUitype, $module), 'value' => (float)$row['v']);
		}
		if (in_array($groupUitype, self::DATE_UITYPES, true)) {
			// the latest months, shown oldest first
			$rows = array_reverse(array_slice($rows, 0, $settings['limit']));
		} elseif (count($rows) > $settings['limit']) {
			$rest = array_splice($rows, $settings['limit'] - 1);
			$other = 0;
			foreach ($rest as $r) {
				$other += $r['value'];
			}
			$rows[] = array('label' => vtranslate('LBL_CUSTOM_WIDGET_OTHER', 'Vtiger'), 'value' => $other);
		}
		$total = 0;
		$max = 0;
		foreach ($rows as $r) {
			$total += max(0, $r['value']);
			$max = max($max, $r['value']);
		}
		$palette = array('#00D3B8', '#4A555F', '#F2A65A', '#E4572E', '#06A793', '#7CE8D8', '#9DB4C0', '#B5C99A', '#D4A5A5', '#0B0F14');
		$offset = 25; // a ring starts at twelve o'clock
		foreach ($rows as $i => $r) {
			$share = $total > 0 ? max(0, $r['value']) / $total : 0;
			$rows[$i]['display'] = self::format($r['value'], $currency, $settings['metric'] === 'count');
			$rows[$i]['bar'] = $max > 0 ? max(2, round(max(0, $r['value']) / $max * 100)) : 0;
			$rows[$i]['color'] = $palette[$i % count($palette)];
			$rows[$i]['arc'] = round($share * 100, 3);
			$rows[$i]['gap'] = round(100 - $share * 100, 3);
			$rows[$i]['offset'] = round($offset, 3);
			$offset -= $share * 100;
		}
		return array('kind' => 'groups', 'rows' => $rows, 'total' => $total, 'currency' => $currency);
	}

	protected static function groupLabel($value, $uitype, $module) {
		if (in_array($uitype, self::OWNER_UITYPES, true)) {
			return $value ? decode_html(getOwnerName($value)) : vtranslate('LBL_CUSTOM_WIDGET_NONE', 'Vtiger');
		}
		if ($uitype === 56) {
			return vtranslate($value ? 'LBL_YES' : 'LBL_NO', 'Vtiger');
		}
		if (in_array($uitype, self::DATE_UITYPES, true)) {
			return $value ? date('M Y', strtotime($value . '-01')) : vtranslate('LBL_CUSTOM_WIDGET_NONE', 'Vtiger');
		}
		return ($value === null || $value === '') ? vtranslate('LBL_CUSTOM_WIDGET_NONE', 'Vtiger') : vtranslate(decode_html($value), $module);
	}

	/** A number in the user's format, with the currency symbol for money. */
	public static function format($value, $currency, $whole = false) {
		if ($whole) {
			// counts have no decimals; thousands are grouped with the user's separator
			$user = Users_Record_Model::getCurrentUserModel();
			return number_format((int)round($value), 0, '', html_entity_decode((string)$user->get('currency_grouping_separator')) ?: ',');
		}
		if ($value < 0) {
			return '-' . self::format(abs($value), $currency, $whole);
		}
		$user = Users_Record_Model::getCurrentUserModel();
		$formatted = CurrencyField::convertToUserFormat($value, $user, true);
		if ($currency) {
			$base = Vtiger_Util_Helper::getBaseCurrency();
			$formatted = CurrencyField::appendCurrencySymbol($formatted, $base['currency_symbol'] ?? '');
		}
		return $formatted;
	}
}
