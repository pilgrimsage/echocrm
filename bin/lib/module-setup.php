<?php
/**
 * Shared by the module registration scripts in bin/: creates an entity module with vtlib from a
 * short specification (tables, blocks, fields, entity identifier, default list view, numbering,
 * menu entry, web service). Every step checks what already exists, so scripts can be re-run.
 *
 * Spec keys: name, label, parent (tab group), table (base table, default vtiger_<lowername>),
 *   menu_sequence, prefix (numbering), blocks (labels), fields (see below), entity (field name),
 *   list_columns (field names of the default "All" list).
 * Field keys: name, label, block, uitype, type (typeofdata), col, ctype (column type),
 *   dt (display type, default 1), qc (quick create, default 3), sm (summary field, default 0),
 *   table (other table), related (modules for uitype 10), picklist (values), dv (default value).
 * Returns array('module' => Vtiger_Module, 'fields' => name => Vtiger_Field).
 */
function registerEntityModule(array $spec) {
	global $adb;
	$say = function ($message) { echo $message . "\n"; };
	$name = $spec['name'];

	$module = Vtiger_Module::getInstance($name);
	if (!$module) {
		$module = new Vtiger_Module();
		$module->name = $name;
		$module->label = $spec['label'];
		$module->parent = $spec['parent'];
		$module->isentitytype = true;
		$module->save();
		$say("Created module $name (tabid {$module->id}).");
	}
	$module->initTables();
	$table = isset($spec['table']) ? $spec['table'] : 'vtiger_' . strtolower($name);

	$blocks = array();
	foreach ($spec['blocks'] as $index => $label) {
		$block = Vtiger_Block::getInstance($label, $module);
		if (!$block) {
			$block = new Vtiger_Block();
			$block->label = $label;
			$block->sequence = $index + 1;
			$module->addBlock($block);
		}
		$blocks[$label] = $block;
	}

	$instances = array();
	foreach ($spec['fields'] as $f) {
		$field = Vtiger_Field::getInstance($f['name'], $module);
		if (!$field) {
			$field = new Vtiger_Field();
			$field->name = $f['name'];
			$field->label = $f['label'];
			$field->uitype = $f['uitype'];
			$field->typeofdata = $f['type'];
			$field->column = $f['col'];
			$field->table = isset($f['table']) ? $f['table'] : $table;
			$field->columntype = isset($f['ctype']) ? $f['ctype'] : 'VARCHAR(100)';
			$field->displaytype = isset($f['dt']) ? $f['dt'] : 1;
			$field->quickcreate = isset($f['qc']) ? $f['qc'] : 3;
			$field->summaryfield = isset($f['sm']) ? $f['sm'] : 0;
			if (isset($f['dv'])) {
				$field->defaultvalue = $f['dv'];
			}
			$blocks[$f['block']]->addField($field);
			if (!empty($f['related'])) {
				$field->setRelatedModules($f['related']);
			}
			if (!empty($f['picklist'])) {
				$field->setPicklistValues($f['picklist']);
			}
			$say("  field {$f['name']} created");
		}
		$instances[$f['name']] = $field;
	}
	$module->setEntityIdentifier($instances[$spec['entity']]);

	if (!Vtiger_Filter::getInstance('All', $module)) {
		$filter = new Vtiger_Filter();
		$filter->name = 'All';
		$filter->isdefault = true;
		$module->addFilter($filter);
		foreach ($spec['list_columns'] as $position => $fieldName) {
			$filter->addField($instances[$fieldName], $position);
		}
	}
	$module->setDefaultSharing('Public_ReadWriteDelete');
	$module->enableTools(array('Import', 'Export'));

	$numbering = $adb->pquery('SELECT 1 FROM vtiger_modentity_num WHERE semodule = ?', array($name));
	if (!$adb->num_rows($numbering)) {
		$adb->pquery('INSERT INTO vtiger_modentity_num VALUES (?,?,?,?,?,?)', array($adb->getUniqueId('vtiger_modentity_num'), $name, $spec['prefix'], 1, 1, 1));
	}
	$ws = $adb->pquery('SELECT 1 FROM vtiger_ws_entity WHERE name = ?', array($name));
	if (!$adb->num_rows($ws)) {
		$module->initWebservice();
	}

	// vtlib adds the module to the app of its parent group as well; keep the Inventory entry only
	$adb->pquery("DELETE FROM vtiger_app2tab WHERE tabid = ? AND appname != 'INVENTORY'", array($module->id));
	$menu = $adb->pquery("SELECT 1 FROM vtiger_app2tab WHERE tabid = ? AND appname = 'INVENTORY'", array($module->id));
	if (!$adb->num_rows($menu)) {
		$adb->pquery("INSERT INTO vtiger_app2tab (tabid, appname, sequence, visible) VALUES (?, 'INVENTORY', ?, 1)", array($module->id, $spec['menu_sequence']));
	}
	return array('module' => $module, 'fields' => $instances);
}

// Lists and details of a module with currency fields (uitype 71/72) look up the record's
// currency_id and conversion_rate in the module's own table; a plain table needs both columns.
function addCurrencyColumns($table) {
	global $adb;
	$columns = $adb->getColumnNames($table);
	if (!in_array('currency_id', $columns)) {
		$adb->query("ALTER TABLE $table ADD COLUMN currency_id INT(19) DEFAULT 1");
	}
	if (!in_array('conversion_rate', $columns)) {
		$adb->query("ALTER TABLE $table ADD COLUMN conversion_rate DECIMAL(10,3) DEFAULT 1.000");
	}
}

/** Adds an index when it is not there yet (the lists and lookups below depend on them at volume). */
function addIndex($table, $name, $columns) {
	global $adb;
	$result = $adb->pquery("SHOW INDEX FROM $table WHERE Key_name = ?", array($name));
	if (!$adb->num_rows($result)) {
		$adb->query("ALTER TABLE $table ADD INDEX $name ($columns)");
	}
}

