<?php
/**
 * Audit trail of the books: every journal entry created, deleted or reversed is logged with who did it and when.
 * A system entry that follows its document (invoice edited, payment changed) shows as a deletion of the old entry
 * and the creation of the new one, so the history of what the books said is never lost. Logging is switched on by
 * bin/create-integrity.php (the table); until then it silently does nothing.
 */
class Vtiger_Audit_Utils {

	private static $enabled = null;

	public static function enabled() {
		global $adb;
		if (self::$enabled === null) {
			$result = $adb->pquery("SHOW TABLES LIKE 'vtiger_journal_audit'");
			self::$enabled = $adb->num_rows($result) > 0;
		}
		return self::$enabled;
	}

	public static function log($action, $entryId, $date, $module, $id, $key, $type, $total, $narration) {
		global $adb, $current_user;
		if (!self::enabled()) {
			return;
		}
		$adb->pquery('INSERT INTO vtiger_journal_audit (at, user_id, action, entry_id, entry_date, source_module, source_id, source_key, entry_type, total, narration)
			VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			array($current_user && !empty($current_user->id) ? $current_user->id : null, $action, $entryId, $date, $module, $id, $key, $type, $total, mb_substr((string)$narration, 0, 250)));
	}

	/** Log rows, newest first, for the given filters (from, to dates of the change; user; action; source module; entry id). Returns array(rows, total). */
	public static function search(array $f, $limit, $offset) {
		global $adb;
		if (!self::enabled()) {
			return array(array(), 0);
		}
		$where = array('1 = 1');
		$params = array();
		if (!empty($f['from'])) {
			$where[] = 'a.at >= ?';
			$params[] = $f['from'] . ' 00:00:00';
		}
		if (!empty($f['to'])) {
			$where[] = 'a.at <= ?';
			$params[] = $f['to'] . ' 23:59:59';
		}
		if (!empty($f['user'])) {
			$where[] = 'a.user_id = ?';
			$params[] = (int)$f['user'];
		}
		if (!empty($f['action'])) {
			$where[] = 'a.action = ?';
			$params[] = $f['action'];
		}
		if (!empty($f['source'])) {
			$where[] = 'a.source_module = ?';
			$params[] = $f['source'];
		}
		if (!empty($f['entry'])) {
			$where[] = 'a.entry_id = ?';
			$params[] = (int)$f['entry'];
		}
		$sql = ' FROM vtiger_journal_audit a LEFT JOIN vtiger_users u ON u.id = a.user_id WHERE ' . implode(' AND ', $where);
		$count = (int)$adb->query_result($adb->pquery('SELECT COUNT(*) AS n' . $sql, $params), 0, 'n');
		$result = $adb->pquery('SELECT a.*, u.user_name' . $sql . ' ORDER BY a.audit_id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset, $params);
		$rows = array();
		while ($row = $adb->fetch_array($result)) {
			$row['narration'] = decode_html($row['narration']);
			$rows[] = $row;
		}
		return array($rows, $count);
	}

	public static function users() {
		global $adb;
		$users = array();
		if (self::enabled()) {
			$result = $adb->pquery('SELECT DISTINCT u.id, u.user_name FROM vtiger_journal_audit a INNER JOIN vtiger_users u ON u.id = a.user_id ORDER BY u.user_name');
			while ($row = $adb->fetch_array($result)) {
				$users[$row['id']] = $row['user_name'];
			}
		}
		return $users;
	}
}
