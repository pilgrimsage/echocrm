<?php
require_once 'include/database/PDORecordSet.php';

/**
 * Minimal ADOdb-connection-compatible wrapper around a native PDO (MySQL)
 * connection. Backs PearDatabase.php's $this->database after the ADOdb -> PDO
 * migration. Only implements the methods PearDatabase.php (and the handful
 * of external call sites touching $adb->database directly) actually use.
 * See the "No shims" exception note in CLAUDE.md before extending this.
 */
#[\AllowDynamicProperties]
class PDOConnectionCompat {
	public $pdo = null;
	public $clientFlags = 0;
	public $optionFlags = array();
	public $debug = false;
	public $_connectionID = null; // kept only for truthiness checks elsewhere; not a real resource
	private $logSQL = false;
	private $lastStmt = null;
	private $lastError = '';
	private $lastErrorNo = 0;

	function PConnect($host, $user, $password, $dbname) {
		return $this->connect($host, $user, $password, $dbname);
	}

	function connect($host, $user, $password, $dbname) {
		$port = null;
		$sock = null;
		if (strpos($host, ':') !== false) {
			list($host, $port) = explode(':', $host, 2);
		}
		$dsn = 'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4';
		if ($port) {
			$dsn .= ';port=' . $port;
		}
		try {
			$this->pdo = new PDO($dsn, $user, $password, array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
				PDO::ATTR_EMULATE_PREPARES => true,
				PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
				PDO::ATTR_STRINGIFY_FETCHES => true,
			));
			$this->_connectionID = true;
			return true;
		} catch (PDOException $e) {
			$this->lastError = $e->getMessage();
			$this->_connectionID = false;
			return false;
		}
	}

	function disconnect() {
		$this->pdo = null;
		$this->_connectionID = null;
	}

	function LogSQL($enable) { $this->logSQL = $enable; }
	function setOption($name, $value) { /* no-op: PDO attributes are set at connect time */ }
	function SetFetchMode($mode) { /* no-op: rows are always returned as associative arrays */ }

	function StartTrans() { if ($this->pdo) $this->pdo->beginTransaction(); }
	function CompleteTrans() { if ($this->pdo && $this->pdo->inTransaction()) $this->pdo->commit(); }
	function HasFailedTrans() { return false; }

	function Quote($string) { return $this->pdo->quote((string) $string); }
	function qstr($string) { return $this->pdo->quote((string) $string); }

	function Insert_ID() { return $this->pdo->lastInsertId(); }
	function Affected_Rows() { return $this->lastStmt ? $this->lastStmt->rowCount() : 0; }
	function ErrorNo() { return $this->lastErrorNo; }
	function ErrorMsg() { return $this->lastError; }

	/**
	 * Runs $sql (optionally as a prepared statement with positional "?"
	 * params) and returns a PDORecordSet for row-producing statements, or a
	 * truthy empty PDORecordSet for successful writes (INSERT/UPDATE/DELETE/
	 * DDL), matching ADOdb's Execute() contract where callers only check
	 * "if (!$result)" for failure. Returns false on failure.
	 */
	/** Alias kept for a pre-existing call site (get_db_charset()) that called ->query() on the ADOdb connection - not a real ADOdb method, but harmless to support. */
	function query($sql, $params = false) {
		return $this->Execute($sql, $params);
	}

	function Execute($sql, $params = false) {
		$this->lastError = '';
		$this->lastErrorNo = 0;
		try {
			if ($params !== false && $params !== array()) {
				$stmt = $this->pdo->prepare($sql);
				if ($stmt === false) {
					$this->_captureError($this->pdo->errorInfo());
					return false;
				}
				$ok = $stmt->execute(array_values($params));
			} else {
				$stmt = $this->pdo->query($sql);
				$ok = ($stmt !== false);
			}
		} catch (PDOException $e) {
			$this->lastError = $e->getMessage();
			$this->lastErrorNo = (int) $e->getCode();
			return false;
		}

		if (!$ok || $stmt === false) {
			$errInfo = $params !== false ? $stmt->errorInfo() : $this->pdo->errorInfo();
			$this->_captureError($errInfo);
			return false;
		}

		$this->lastStmt = $stmt;

		if ($stmt->columnCount() > 0) {
			// ADOdb's default fetch mode returns both associative AND numeric
			// keys on the same row (e.g. $row['Leads_status'] and $row[0] both
			// work). Some app code (Reports PDF/Excel export) relies on the
			// positional access, so match that here rather than FETCH_ASSOC.
			$rows = $stmt->fetchAll(PDO::FETCH_BOTH);
			$fieldMeta = array();
			for ($i = 0; $i < $stmt->columnCount(); $i++) {
				$meta = $stmt->getColumnMeta($i);
				$fieldMeta[] = array(
					'name' => $meta['name'] ?? '',
					'type' => isset($meta['native_type']) ? strtoupper($meta['native_type']) : '',
				);
			}
			return new PDORecordSet($rows, $fieldMeta);
		}

		return new PDORecordSet(array(), array());
	}

	private function _captureError($errInfo) {
		$this->lastError = isset($errInfo[2]) ? $errInfo[2] : '';
		$this->lastErrorNo = isset($errInfo[1]) ? (int) $errInfo[1] : 0;
	}

	function GetOne($sql) {
		$rs = $this->Execute($sql);
		if (!$rs || $rs->RecordCount() === 0) {
			return false;
		}
		$row = $rs->rows[0];
		return reset($row);
	}

	function SelectLimit($sql, $count = -1, $offset = -1) {
		if ($count >= 0) {
			$sql = rtrim($sql, "; \t\n\r");
			if ($offset > 0) {
				$sql .= ' LIMIT ' . (int) $offset . ',' . (int) $count;
			} else {
				$sql .= ' LIMIT ' . (int) $count;
			}
		}
		return $this->Execute($sql);
	}

	function UpdateBlob($table, $column, $val, $where, $blobtype = 'BLOB') {
		return $this->Execute("UPDATE $table SET $column=? WHERE $where", array($val)) != false;
	}

	function UpdateBlobFile($table, $column, $path, $where, $blobtype = 'BLOB') {
		$fd = fopen($path, 'rb');
		if ($fd === false) return false;
		$val = fread($fd, filesize($path));
		fclose($fd);
		return $this->UpdateBlob($table, $column, $val, $where, $blobtype);
	}

	/**
	 * Emulates ADOdb's MySQL GenID(): a persistent single-row counter table
	 * named $seqname, incremented atomically via LAST_INSERT_ID(id+1) so the
	 * new value is retrievable through Insert_ID(). Vtiger already has these
	 * *_seq tables on disk from the ADOdb era; this keeps incrementing the
	 * same tables so existing sequence state is preserved.
	 */
	function GenID($seqname = 'adodbseq', $startID = 1) {
		$rs = @$this->Execute("update `$seqname` set id=LAST_INSERT_ID(id+1)");
		if (!$rs) {
			$this->Execute("create table if not exists `$seqname` (id int not null)");
			$cnt = $this->GetOne("select count(*) from `$seqname`");
			if (!$cnt) {
				$this->Execute("insert into `$seqname` values (" . ((int) $startID - 1) . ")");
			}
			$rs = $this->Execute("update `$seqname` set id=LAST_INSERT_ID(id+1)");
		}
		if ($rs) {
			$genID = (int) $this->pdo->lastInsertId();
			if ($genID == 0) {
				$genID = (int) $this->GetOne("select LAST_INSERT_ID() from `$seqname`");
			}
			return $genID;
		}
		return 0;
	}

	function DBTimeStamp($ts, $isfld = false) {
		if (empty($ts) && $ts !== 0 && $ts !== '0') return 'null';
		if ($isfld) return $ts;
		if (is_object($ts) && method_exists($ts, 'format')) {
			return "'" . $ts->format('Y-m-d H:i:s') . "'";
		}
		if ($ts === 'null') return $ts;
		if (is_numeric($ts) && strlen((string) $ts) >= 14) {
			// YmdHis packed format, e.g. 20260924103000
			$t = strtotime($ts);
			return $t !== false ? "'" . date('Y-m-d H:i:s', $t) . "'" : "'" . $ts . "'";
		}
		if (is_numeric($ts)) {
			return "'" . date('Y-m-d H:i:s', (int) $ts) . "'";
		}
		// Already a date/time string - normalize through strtotime when possible.
		$t = strtotime($ts);
		return $t !== false ? "'" . date('Y-m-d H:i:s', $t) . "'" : "'" . $ts . "'";
	}

	function SQLDate($fmt, $col = false) {
		if (!$col) $col = 'NOW()';
		$map = array(
			'Y' => '%Y', 'y' => '%y', 'M' => '%b', 'm' => '%m',
			'D' => '%d', 'd' => '%d', 'H' => '%H', 'h' => '%I',
			'i' => '%i', 's' => '%s', 'A' => '%p', 'a' => '%p',
		);
		$mysqlFmt = '';
		for ($i = 0, $len = strlen($fmt); $i < $len; $i++) {
			$ch = $fmt[$i];
			$mysqlFmt .= $map[$ch] ?? $ch;
		}
		return "DATE_FORMAT($col,'" . $mysqlFmt . "')";
	}

	function MetaTables($type = 'TABLES') {
		$rs = $this->Execute('SHOW TABLES');
		if (!$rs) return array();
		$tables = array();
		foreach ($rs->rows as $row) {
			$tables[] = reset($row);
		}
		return $tables;
	}

	/**
	 * Returns column metadata objects (->name, ->type, ->max_length,
	 * ->not_null, ->primary_key, ->auto_increment) for $tablename, matching
	 * the subset of ADOdb's MetaColumns() this codebase actually reads.
	 */
	function MetaColumns($tablename) {
		$tablename = trim($tablename, '`');
		$rs = $this->Execute("SHOW COLUMNS FROM `$tablename`");
		if (!$rs) return array();
		$cols = array();
		foreach ($rs->rows as $row) {
			$row = array_change_key_case($row, CASE_LOWER);
			$rawType = $row['type'];
			$baseType = strtoupper(preg_replace('/\(.*/', '', $rawType));
			$maxLength = -1;
			if (preg_match('/\((\d+)/', $rawType, $m)) {
				$maxLength = (int) $m[1];
			}
			$col = new stdClass();
			$col->name = $row['field'];
			$col->type = $baseType;
			$col->max_length = $maxLength;
			$col->not_null = ($row['null'] === 'NO');
			$col->primary_key = ($row['key'] === 'PRI');
			$col->auto_increment = (stripos($row['extra'], 'auto_increment') !== false);
			$cols[$col->name] = $col;
		}
		return $cols;
	}
}
