<?php
/**
 * Minimal ADOdb-recordset-compatible wrapper around a buffered PDOStatement
 * result set. Exists only so PearDatabase.php's internal code and the small
 * number of external call sites that poke the returned recordset directly
 * (FetchRow/EOF/Move/MoveNext/FieldCount/FetchField/RecordCount/GetRowAssoc)
 * keep working unchanged after the ADOdb -> PDO migration. See the
 * "No shims" exception note in CLAUDE.md before extending this further.
 */
#[\AllowDynamicProperties]
class PDORecordSet implements Iterator {
	/** @var array Buffered rows (associative arrays, lower-cased keys as ADOdb returned them via change_key_case) */
	public $rows = array();
	public $fields = array();
	public $fieldMeta = array();
	public $EOF = true;
	private $pos = 0;

	function __construct($rows = array(), $fieldMeta = array()) {
		$this->rows = $rows;
		$this->fieldMeta = $fieldMeta;
		$this->pos = 0;
		$this->EOF = (count($this->rows) === 0);
		$this->fields = $this->EOF ? array() : $this->rows[0];
	}

	function FetchRow() {
		if ($this->pos >= count($this->rows)) {
			$this->EOF = true;
			return false;
		}
		$row = $this->rows[$this->pos];
		$this->fields = $row;
		$this->pos++;
		$this->EOF = ($this->pos >= count($this->rows));
		return $row;
	}

	function GetRowAssoc($upper = false) {
		if ($this->pos >= count($this->rows)) {
			return false;
		}
		$row = $this->rows[$this->pos];
		if ($upper) {
			$row = array_change_key_case($row, CASE_UPPER);
		}
		return $row;
	}

	function MoveNext() {
		$this->pos++;
		$this->EOF = ($this->pos >= count($this->rows));
		if (!$this->EOF) {
			$this->fields = $this->rows[$this->pos];
		}
		return !$this->EOF;
	}

	function Move($row) {
		$this->pos = $row;
		$this->EOF = ($this->pos >= count($this->rows) || $this->pos < 0);
		if (!$this->EOF) {
			$this->fields = $this->rows[$this->pos];
		}
		return !$this->EOF;
	}

	function RecordCount() {
		return count($this->rows);
	}

	function FieldCount() {
		return count($this->fieldMeta);
	}

	function FetchField($i) {
		if (isset($this->fieldMeta[$i])) {
			return (object) $this->fieldMeta[$i];
		}
		return false;
	}

	// Iterator interface: ADOdb recordsets are directly foreach-able
	// ("foreach ($adb->query($sql) as $row)"), and some app code relies on
	// that instead of calling FetchRow() in a loop. Shares the same cursor
	// ($this->pos) as FetchRow()/Move()/MoveNext() - don't mix iteration
	// styles on the same recordset instance.
	function current(): mixed { return $this->rows[$this->pos] ?? false; }
	function key(): mixed { return $this->pos; }
	function next(): void { $this->pos++; }
	function rewind(): void { $this->pos = 0; }
	function valid(): bool { return $this->pos < count($this->rows); }
}
