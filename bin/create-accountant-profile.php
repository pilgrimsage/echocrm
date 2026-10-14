<?php
/**
 * Creates an "Accountant" profile and role: everything the Sales Profile can do, plus the accounting
 * modules (Ledgers, Bank Accounts, Bank Transactions, Payments, Cost Centres, Fixed Assets) and
 * "view all / edit all" on records, so the accountant sees every customer, invoice and payment.
 * Accounting Settings and Financial Year Close stay administrator-only in code. The role is created
 * under CEO; move it in Settings > Roles if your hierarchy differs. Safe to run more than once.
 *
 * Usage (from the project root):   php bin/create-accountant-profile.php
 * Afterwards: give users the Accountant role in Settings > Users, and run bin/dump-db.sh.
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
require_once 'modules/Users/CreateUserPrivilegeFile.php';

global $adb, $current_user;
$current_user = Users::getActiveAdminUser();

function say($message) {
	echo $message . "\n";
}

$modules = array('Ledgers', 'BankAccounts', 'BankTransactions', 'Payments', 'CostCentres', 'FixedAssets');

// profile: a copy of the Sales Profile
$existing = $adb->pquery('SELECT profileid FROM vtiger_profile WHERE profilename = ?', array('Accountant'));
if ($adb->num_rows($existing)) {
	$profileId = (int)$adb->query_result($existing, 0, 'profileid');
	say("Profile Accountant exists (id $profileId).");
} else {
	$base = $adb->pquery('SELECT profileid FROM vtiger_profile WHERE profilename = ?', array('Sales Profile'));
	if (!$adb->num_rows($base)) {
		fwrite(STDERR, "The Sales Profile is missing; nothing to copy.\n");
		exit(1);
	}
	$baseId = (int)$adb->query_result($base, 0, 'profileid');
	$profileId = (int)$adb->getUniqueId('vtiger_profile');
	$adb->pquery('INSERT INTO vtiger_profile (profileid, profilename, description, directly_related_to_role) VALUES (?,?,?,0)',
		array($profileId, 'Accountant', 'Sales access plus the accounting modules; sees and edits all records'));
	foreach (array('vtiger_profile2tab', 'vtiger_profile2standardpermissions', 'vtiger_profile2utility', 'vtiger_profile2field') as $table) {
		$columns = $adb->getColumnNames($table);
		$list = implode(',', $columns);
		$select = implode(',', array_map(function ($c) { return $c === 'profileid' ? '?' : $c; }, $columns));
		$adb->pquery("INSERT INTO $table ($list) SELECT $select FROM $table WHERE profileid = ?", array($profileId, $baseId));
	}
	say("Created profile Accountant (id $profileId) as a copy of the Sales Profile.");
}

// view all / edit all
$adb->pquery('DELETE FROM vtiger_profile2globalpermissions WHERE profileid = ?', array($profileId));
foreach (array(1, 2) as $action) {
	$adb->pquery('INSERT INTO vtiger_profile2globalpermissions (profileid, globalactionid, globalactionpermission) VALUES (?,?,0)', array($profileId, $action));
}

// the accounting modules: allowed, every action, every utility, every field readable and writable
foreach ($modules as $moduleName) {
	$tab = $adb->pquery('SELECT tabid FROM vtiger_tab WHERE name = ?', array($moduleName));
	if (!$adb->num_rows($tab)) {
		say("$moduleName is not installed, skipped.");
		continue;
	}
	$tabId = (int)$adb->query_result($tab, 0, 'tabid');
	$adb->pquery('UPDATE vtiger_profile2tab SET permissions = 0 WHERE profileid = ? AND tabid = ?', array($profileId, $tabId));
	$adb->pquery('UPDATE vtiger_profile2standardpermissions SET permissions = 0 WHERE profileid = ? AND tabid = ?', array($profileId, $tabId));
	$adb->pquery('UPDATE vtiger_profile2utility SET permission = 0 WHERE profileid = ? AND tabid = ?', array($profileId, $tabId));
	$adb->pquery('UPDATE vtiger_profile2field SET visible = 0, readonly = 0 WHERE profileid = ? AND tabid = ?', array($profileId, $tabId));
	say("$moduleName: enabled for the Accountant profile.");
}

// role under CEO
$role = $adb->pquery('SELECT roleid FROM vtiger_role WHERE rolename = ?', array('Accountant'));
if ($adb->num_rows($role)) {
	$roleId = $adb->query_result($role, 0, 'roleid');
	say("Role Accountant exists ($roleId).");
} else {
	$parent = $adb->pquery('SELECT roleid FROM vtiger_role WHERE rolename = ?', array('CEO'));
	$parentId = $adb->num_rows($parent) ? $adb->query_result($parent, 0, 'roleid') : 'H1';
	$roleModel = new Settings_Roles_Record_Model();
	$roleModel->set('rolename', 'Accountant');
	$roleModel->set('allowassignedrecordsto', 1);
	$roleModel->set('profileIds', array($profileId));
	$roleModel->setParent(Settings_Roles_Record_Model::getInstanceById($parentId));
	$roleModel->save();
	say('Created role Accountant under ' . $parentId . '.');
}

// the cached permission files of the users who already have this profile
$users = $adb->pquery('SELECT u.id FROM vtiger_users u JOIN vtiger_user2role r ON r.userid = u.id JOIN vtiger_role2profile p ON p.roleid = r.roleid WHERE p.profileid = ?', array($profileId));
while ($user = $adb->fetch_array($users)) {
	createUserPrivilegesfile($user['id']);
}
say('Done. Give users the Accountant role in Settings > Users.');
