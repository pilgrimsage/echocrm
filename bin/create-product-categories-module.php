<?php
/**
 * Registers the Product Categories module and turns the Products "Product Category" picklist into
 * a reference to it: module, tables, fields, default list view, numbering (PC1, PC2, ...), menu
 * entry (Inventory), the Products related list on a category, and a one-time conversion of the
 * existing picklist values (and the category text on each product) into category records.
 *
 * The PHP files live in modules/ProductCategories and languages/en_us/ProductCategories.php.
 * Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-product-categories-module.php
 * Afterwards: clear test/templates_c/v7/*, and run bin/dump-db.sh if fresh clones should get it.
 */
if (PHP_SAPI !== 'cli') {
	exit("Run this from the command line.\n");
}
chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/utils/utils.php';
vimport('includes.runtime.EntryPoint');
include_once 'vtlib/Vtiger/Module.php';
include_once 'modules/ModComments/ModComments.php';

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

function say($message) {
	echo $message . "\n";
}

$moduleName = 'ProductCategories';

// 1. Module and tables
$module = Vtiger_Module::getInstance($moduleName);
if (!$module) {
	$module = new Vtiger_Module();
	$module->name = $moduleName;
	$module->label = 'Product Categories';
	$module->parent = 'Inventory';
	$module->isentitytype = true;
	$module->save();
	say("Created module $moduleName (tabid {$module->id}).");
}
$module->initTables();

// 2. Blocks
$blocks = array();
foreach (array('LBL_PRODUCTCATEGORIES_INFORMATION', 'LBL_DESCRIPTION_INFORMATION') as $index => $label) {
	$block = Vtiger_Block::getInstance($label, $module);
	if (!$block) {
		$block = new Vtiger_Block();
		$block->label = $label;
		$block->sequence = $index + 1;
		$module->addBlock($block);
	}
	$blocks[$label] = $block;
}

// 3. Fields
$I = 'LBL_PRODUCTCATEGORIES_INFORMATION';
$fields = array(
	array('name' => 'category_name', 'label' => 'Category Name', 'block' => $I, 'uitype' => 2, 'type' => 'V~M', 'col' => 'category_name', 'ctype' => 'VARCHAR(200)', 'dt' => 1, 'qc' => 0, 'sm' => 1),
	array('name' => 'category_no', 'label' => 'Category No', 'block' => $I, 'uitype' => 4, 'type' => 'V~O', 'col' => 'category_no', 'ctype' => 'VARCHAR(100)', 'dt' => 1, 'qc' => 3, 'sm' => 1),
	array('name' => 'parent_category', 'label' => 'Parent Category', 'block' => $I, 'uitype' => 10, 'type' => 'I~O', 'col' => 'parent_category', 'ctype' => 'INT(19)', 'dt' => 1, 'qc' => 1, 'sm' => 1, 'related' => array('ProductCategories')),
	array('name' => 'assigned_user_id', 'label' => 'Assigned To', 'block' => $I, 'uitype' => 53, 'type' => 'V~M', 'col' => 'smownerid', 'table' => 'vtiger_crmentity', 'dt' => 1, 'qc' => 0, 'sm' => 0),
	array('name' => 'createdtime', 'label' => 'Created Time', 'block' => $I, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'createdtime', 'table' => 'vtiger_crmentity', 'dt' => 2, 'qc' => 3, 'sm' => 0),
	array('name' => 'modifiedtime', 'label' => 'Modified Time', 'block' => $I, 'uitype' => 70, 'type' => 'DT~O', 'col' => 'modifiedtime', 'table' => 'vtiger_crmentity', 'dt' => 2, 'qc' => 3, 'sm' => 0),
	array('name' => 'modifiedby', 'label' => 'Last Modified By', 'block' => $I, 'uitype' => 52, 'type' => 'V~O', 'col' => 'modifiedby', 'table' => 'vtiger_crmentity', 'dt' => 3, 'qc' => 3, 'sm' => 0),
	array('name' => 'description', 'label' => 'Description', 'block' => 'LBL_DESCRIPTION_INFORMATION', 'uitype' => 19, 'type' => 'V~O', 'col' => 'description', 'table' => 'vtiger_crmentity', 'dt' => 1, 'qc' => 3, 'sm' => 0),
);
$fieldInstances = array();
foreach ($fields as $spec) {
	$field = Vtiger_Field::getInstance($spec['name'], $module);
	if (!$field) {
		$field = new Vtiger_Field();
		$field->name = $spec['name'];
		$field->label = $spec['label'];
		$field->uitype = $spec['uitype'];
		$field->typeofdata = $spec['type'];
		$field->column = $spec['col'];
		$field->table = isset($spec['table']) ? $spec['table'] : 'vtiger_productcategories';
		$field->columntype = isset($spec['ctype']) ? $spec['ctype'] : 'VARCHAR(100)';
		$field->displaytype = $spec['dt'];
		$field->quickcreate = $spec['qc'];
		$field->summaryfield = $spec['sm'];
		$blocks[$spec['block']]->addField($field);
		if (!empty($spec['related'])) {
			$field->setRelatedModules($spec['related']);
		}
		say("  field {$spec['name']} created");
	}
	$fieldInstances[$spec['name']] = $field;
}
$module->setEntityIdentifier($fieldInstances['category_name']);

// 4. Default list view, sharing, tools, numbering, web service
if (!Vtiger_Filter::getInstance('All', $module)) {
	$filter = new Vtiger_Filter();
	$filter->name = 'All';
	$filter->isdefault = true;
	$module->addFilter($filter);
	foreach (array('category_no', 'category_name', 'parent_category', 'assigned_user_id') as $position => $name) {
		$filter->addField($fieldInstances[$name], $position);
	}
}
$module->setDefaultSharing('Public_ReadWriteDelete');
$module->enableTools(array('Import', 'Export'));
$numbering = $adb->pquery('SELECT 1 FROM vtiger_modentity_num WHERE semodule = ?', array($moduleName));
if (!$adb->num_rows($numbering)) {
	$adb->pquery('INSERT INTO vtiger_modentity_num VALUES (?,?,?,?,?,?)', array($adb->getUniqueId('vtiger_modentity_num'), $moduleName, 'PC', 1, 1, 1));
}
$ws = $adb->pquery('SELECT 1 FROM vtiger_ws_entity WHERE name = ?', array($moduleName));
if (!$adb->num_rows($ws)) {
	$module->initWebservice();
}

// 5. Menu: next to Products in the Inventory app; remove the automatic extra entries
$adb->pquery("DELETE FROM vtiger_app2tab WHERE tabid = ? AND appname != 'INVENTORY'", array($module->id));
$menu = $adb->pquery("SELECT 1 FROM vtiger_app2tab WHERE tabid = ? AND appname = 'INVENTORY'", array($module->id));
if (!$adb->num_rows($menu)) {
	$adb->pquery("INSERT INTO vtiger_app2tab (tabid, appname, sequence, visible) VALUES (?, 'INVENTORY', 3, 1)", array($module->id));
}

// 6. Products point to a category; the category lists its products
$products = Vtiger_Module::getInstance('Products');
$categoryField = Vtiger_Field::getInstance('productcategory', $products);
if ($categoryField && $categoryField->uitype != 10) {
	// existing picklist values (and what products carry) become category records first
	$names = array();
	$result = $adb->pquery('SELECT productcategory FROM vtiger_productcategory ORDER BY sortorderid');
	while ($row = $adb->fetch_array($result)) {
		$names[] = decode_html($row['productcategory']);
	}
	$result = $adb->pquery("SELECT DISTINCT productcategory FROM vtiger_products WHERE productcategory IS NOT NULL AND productcategory != ''");
	while ($row = $adb->fetch_array($result)) {
		$names[] = decode_html($row['productcategory']);
	}
	$ids = array();
	foreach (array_unique($names) as $name) {
		if ($name === '') {
			continue;
		}
		$existing = $adb->pquery('SELECT productcategoriesid FROM vtiger_productcategories WHERE category_name = ?', array($name));
		if ($adb->num_rows($existing)) {
			$ids[$name] = $adb->query_result($existing, 0, 0);
			continue;
		}
		$focus = CRMEntity::getInstance($moduleName);
		$focus->column_fields['category_name'] = $name;
		$focus->column_fields['assigned_user_id'] = $current_user->id;
		$focus->save($moduleName);
		$ids[$name] = $focus->id;
		say("  category '$name' created (id {$focus->id})");
	}
	foreach ($ids as $name => $id) {
		$adb->pquery('UPDATE vtiger_products SET productcategory = ? WHERE productcategory = ?', array($id, $name));
	}
	$adb->pquery('UPDATE vtiger_field SET uitype = 10 WHERE fieldid = ?', array($categoryField->id));
	$categoryField->setRelatedModules(array($moduleName));
	say('Products.productcategory is now a reference to Product Categories.');
}
$exists = $adb->pquery('SELECT 1 FROM vtiger_relatedlists WHERE tabid = ? AND related_tabid = ?', array($module->id, $products->id));
if (!$adb->num_rows($exists)) {
	$module->setRelatedList($products, 'Products', array('ADD'), 'get_dependents_list');
	say('  related list Products added to Product Categories');
}
ModComments::addWidgetTo($moduleName);

create_tab_data_file();
create_parenttab_data_file();
say('Done. Clear test/templates_c/v7/* and reload.');
