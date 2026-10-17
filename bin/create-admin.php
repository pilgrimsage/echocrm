<?php
/**
 * Creates the administrator login of a fresh install (or resets the password of an existing one).
 * The installer data (db/schema.sql) carries no users, so this is the step that makes login possible.
 *
 * Usage (from the project root):
 *   ADMIN_PASSWORD='...' php bin/create-admin.php --user=admin --email=you@example.com [--first=First] [--last=Last] [--timezone=Asia/Kolkata]
 * Without ADMIN_PASSWORD the password is asked for. An existing user with that name only gets the new
 * password (and email, if given): nothing else about it changes.
 */
if (PHP_SAPI !== 'cli') {
	exit("Run this from the command line.\n");
}
chdir(dirname(__DIR__));

$options = getopt('', array('user::', 'email::', 'first::', 'last::', 'timezone::'));
$userName = $options['user'] ?? 'admin';
$email = $options['email'] ?? '';
$first = $options['first'] ?? '';
$last = $options['last'] ?? 'Administrator';
$timezone = $options['timezone'] ?? '';

$password = getenv('ADMIN_PASSWORD');
if ($password === false || $password === '') {
	fwrite(STDERR, "Password for $userName: ");
	system('stty -echo 2>/dev/null');
	$password = trim((string)fgets(STDIN));
	system('stty echo 2>/dev/null');
	fwrite(STDERR, "\n");
}
if (strlen($password) < 8) {
	fwrite(STDERR, "Use at least 8 characters for the password.\n");
	exit(1);
}

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'include/utils/utils.php';
vimport('includes.runtime.EntryPoint');
require_once 'modules/Users/Users.php';
require_once 'modules/Users/CreateUserPrivilegeFile.php';

global $adb, $current_user, $default_timezone;

$existing = $adb->pquery('SELECT id FROM vtiger_users WHERE user_name = ?', array($userName));
if ($adb->num_rows($existing)) {
	$id = (int)$adb->query_result($existing, 0, 'id');
	$adb->pquery('UPDATE vtiger_users SET user_password = ?, confirm_password = ?, crypt_type = ? WHERE id = ?',
		array(password_hash($password, PASSWORD_DEFAULT), password_hash($password, PASSWORD_DEFAULT), 'PHASH', $id));
	if ($email !== '') {
		$adb->pquery('UPDATE vtiger_users SET email1 = ? WHERE id = ?', array($email, $id));
	}
	echo "Updated the password of '$userName' (id $id).\n";
	exit(0);
}

// the CEO role is the stock administrator's role
$role = $adb->pquery("SELECT roleid FROM vtiger_role WHERE rolename = 'CEO'");
$roleId = $adb->num_rows($role) ? $adb->query_result($role, 0, 'roleid') : 'H2';

$user = new Users();
$user->mode = '';
$user->column_fields = array(
	'user_name' => $userName, 'user_password' => $password, 'confirm_password' => $password,
	'first_name' => $first, 'last_name' => $last, 'email1' => $email, 'roleid' => $roleId,
	'status' => 'Active', 'is_admin' => 'on', 'currency_id' => 1,
	'time_zone' => $timezone !== '' ? $timezone : ($default_timezone ?: 'UTC'),
	'date_format' => 'dd-mm-yyyy', 'hour_format' => '24', 'start_hour' => '09:00', 'end_hour' => '18:00',
	'activity_view' => 'This Week', 'lead_view' => 'Today', 'calendarsharedtype' => 'public',
	'defaulteventstatus' => '', 'defaultactivitytype' => '', 'language' => 'en_us', 'theme' => 'softed',
	'internal_mailer' => 1, 'reminder_interval' => '1 Minute',
);
// a fresh database has no user yet to act as: the save runs as a stand-in administrator (id 1)
$standIn = new Users();
$standIn->id = 1;
$standIn->is_admin = 'on';
$standIn->column_fields['is_admin'] = 'on';
$current_user = $standIn;
$user->save('Users');
$id = (int)$user->id;
if (!$id) {
	fwrite(STDERR, "The user could not be created.\n");
	exit(1);
}
$current_user = Users::getActiveAdminUser();

// what the Users screen adds besides the row: the default dashboard tab
$record = Users_Record_Model::getInstanceById($id, 'Users');
$record->addDashboardTabs();
createUserPrivilegesfile($id);

echo "Created administrator '$userName' (id $id). Log in with the password you gave.\n";
