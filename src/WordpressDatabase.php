<?php declare(strict_types=1);

namespace Base3Wordpress;

use Base3\Database\Api\IDatabase;

/**
 * WordPress database adapter for BASE3.
 *
 * Uses WordPress' global $wpdb connection.
 *
 * Contract notes:
 * - connect() is lazy and safe to call repeatedly.
 * - Consumers call connect() proactively (BASE3 contract), so internal methods do not enforce it.
 * - escape() returns a safe string fragment WITHOUT surrounding quotes (BASE3 contract).
 */
final class WordpressDatabase implements IDatabase {

	private bool $connected = false;
	private mixed $wpdb = null;

	public function connect(): void {
		if ($this->connected) {
			return;
		}

		// WordPress provides a global wpdb instance.
		if (isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb'])) {
			$this->wpdb = $GLOBALS['wpdb'];

			// Ensure the connection exists (lazy connect in WordPress).
			if (method_exists($this->wpdb, 'db_connect')) {
				// db_connect() returns bool in WP; ignore result, we expose status via connected().
				$this->wpdb->db_connect();
			}

			$this->connected = true;
			return;
		}

		$this->wpdb = null;
		$this->connected = false;
	}

	public function connected(): bool {
		return $this->connected && is_object($this->wpdb);
	}

	public function disconnect(): void {
		// WordPress manages its own DB lifecycle; we only reset our adapter state.
		$this->connected = false;
		$this->wpdb = null;
	}

	public function nonQuery(string $query): void {
		$this->wpdb->query($query);
	}

	public function scalarQuery(string $query): mixed {
		return $this->wpdb->get_var($query);
	}

	public function singleQuery(string $query): ?array {
		$row = $this->wpdb->get_row($query, ARRAY_A);
		return is_array($row) ? $row : null;
	}

	public function &listQuery(string $query): array {
		$list = $this->wpdb->get_col($query);
		if (!is_array($list)) {
			$empty = [];
			return $empty;
		}
		return $list;
	}

	public function &multiQuery(string $query): array {
		$rows = $this->wpdb->get_results($query, ARRAY_A);
		if (!is_array($rows)) {
			$empty = [];
			return $empty;
		}
		return $rows;
	}

	public function affectedRows(): int {
		return isset($this->wpdb->rows_affected) ? (int) $this->wpdb->rows_affected : 0;
	}

	public function insertId(): int|string {
		return isset($this->wpdb->insert_id) ? (int) $this->wpdb->insert_id : 0;
	}

	public function escape(string $str): string {
		// BASE3 expects: escaped string WITHOUT surrounding quotes.
		if (is_object($this->wpdb) && method_exists($this->wpdb, '_real_escape')) {
			return (string) $this->wpdb->_real_escape($str);
		}

		// Fallback: safe-ish escape if wpdb isn't available (shouldn't happen in normal WP requests).
		return addslashes($str);
	}

	public function isError(): bool {
		if (!is_object($this->wpdb)) {
			return false;
		}
		$err = $this->wpdb->last_error ?? '';
		return is_string($err) && $err !== '';
	}

	public function errorNumber(): int {
		// wpdb does not expose a stable numeric errno in a public API.
		// Keep BASE3 semantics: 0 means "no error" or "unknown".
		return 0;
	}

	public function errorMessage(): string {
		if (!is_object($this->wpdb)) {
			return '';
		}
		$err = $this->wpdb->last_error ?? '';
		return is_string($err) ? $err : '';
	}
}
