<?php
/**
 * Removes the separate Credit Note module (it was replaced by the Credit/Debit Note use of the
 * Sales Order module, see bin/convert-salesorder-to-notes.php). Refuses to run while any credit
 * note record exists. Safe to run more than once; take a database backup first.
 *
 * Usage (from the project root):   php bin/remove-credit-note-module.php
 * Afterwards: clear test/templates_c/v7/*, and run bin/dump-db.sh if fresh clones should match.
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

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

function say($message) {
	echo $message . "\n";
}

$module = Vtiger_Module::getInstance('CreditNote');
if (!$module) {
	say('The CreditNote module is not registered; nothing to do.');
	exit(0);
}

$records = $adb->pquery('SELECT COUNT(*) AS c FROM vtiger_crmentity WHERE setype = ? AND deleted = 0', array('CreditNote'));
if ((int)$adb->query_result($records, 0, 'c') > 0) {
	fwrite(STDERR, "Credit note records exist; move or delete them first.\n");
	exit(1);
}

$tabId = $module->id;

// related lists in both directions, and what refers to the module's fields
$adb->pquery('DELETE FROM vtiger_relatedlists WHERE tabid = ? OR related_tabid = ?', array($tabId, $tabId));
$adb->pquery('DELETE FROM vtiger_fieldmodulerel WHERE module = ? OR relmodule = ?', array('CreditNote', 'CreditNote'));
$adb->pquery('DELETE FROM vtiger_app2tab WHERE tabid = ?', array($tabId));
$adb->pquery('DELETE FROM vtiger_modentity_num WHERE semodule = ?', array('CreditNote'));
$adb->pquery('DELETE FROM vtiger_field WHERE tabid = ?', array($tabId));
say('Removed related lists, field relations, menu entry and numbering.');

// the status picklist belonged to this module alone ("reason" is shared with the Sales Order notes)
$picklist = $adb->pquery('SELECT picklistid FROM vtiger_picklist WHERE name = ?', array('creditnotestatus'));
if ($adb->num_rows($picklist)) {
	$picklistId = $adb->query_result($picklist, 0, 'picklistid');
	$adb->pquery('DELETE FROM vtiger_role2picklist WHERE picklistid = ?', array($picklistId));
	$adb->pquery('DELETE FROM vtiger_picklist WHERE picklistid = ?', array($picklistId));
}

$module->delete();
say('Unregistered the module (blocks, filters, sharing, tools, web service, profiles, links).');

foreach (array('vtiger_creditnote', 'vtiger_creditnotecf', 'vtiger_creditnotebillads', 'vtiger_creditnoteshipads', 'vtiger_creditnotestatus', 'vtiger_creditnotestatus_seq') as $table) {
	$adb->query("DROP TABLE IF EXISTS $table");
}
say('Dropped its tables.');

create_tab_data_file();
create_parenttab_data_file();
say('Done. Clear test/templates_c/v7/* and reload.');
