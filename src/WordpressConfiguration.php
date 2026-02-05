<?php declare(strict_types=1);

namespace Base3Wordpress;

use Base3\Configuration\AbstractConfiguration;
use Base3\Database\Api\IDatabase;

/**
 * WordPress DB-backed configuration for embedded BASE3.
 *
 * Important:
 * - This implementation has a mandatory default configuration.
 * - On first run it creates/seeds the table and ensures defaults exist in DB.
 */
final class WordpressConfiguration extends AbstractConfiguration {

	public function __construct(private readonly IDatabase $database) {}

	// ---------------------------------------------------------------------
	// AbstractConfiguration
	// ---------------------------------------------------------------------

	protected function load(): array {
		return $this->loadConfiguration();
	}

	protected function saveData(array $data): bool {
		$this->database->connect();
		if (!$this->database->connected()) {
			return false;
		}

		if (!$this->tableExists()) {
			$this->createAndSeedTable();
		}

		foreach ($data as $group => $entries) {
			if (!is_array($entries)) {
				continue;
			}
			foreach ($entries as $name => $value) {
				$this->insertConfigValue((string) $group, (string) $name, $value);
			}
		}

		return true;
	}

	public function reload(): void {
		$this->cnf = null;
		$this->dirty = false;
		$this->ensureLoaded();
	}

	public function persistValue(string $group, string $key, $value): bool {
		$this->ensureLoaded();
		$this->setValue($group, $key, $value);

		$this->database->connect();
		if (!$this->database->connected()) {
			return false;
		}

		if (!$this->tableExists()) {
			$this->createAndSeedTable();
		}

		$this->insertConfigValue($group, $key, $value);

		$this->dirty = false;
		return true;
	}

	// ---------------------------------------------------------------------
	// Default configuration (mandatory for this impl)
	// ---------------------------------------------------------------------

	private function getDefaultConfiguration(): array {
		$url = '';

		// Prefer WordPress site URL if available.
		if (function_exists('site_url')) {
			$url = rtrim((string) site_url('/'), '/') . '/';
		}

		return [
			'base' => [
				'url' => $url,
				// In WordPress we do not have a separate endpoint file like base3.php.
				// BASE3 routing is embedded and executed via normal WP requests.
				'endpoint' => '',
				'intern' => ''
			],
			'manager' => [
				'stdscope' => 'web',
				'layout' => 'simple'
			]
		];
	}

	// ---------------------------------------------------------------------
	// DB load/ensure defaults
	// ---------------------------------------------------------------------

	private function loadConfiguration(): array {
		$this->database->connect();
		if (!$this->database->connected()) {
			return $this->getDefaultConfiguration();
		}

		if (!$this->tableExists()) {
			$this->createAndSeedTable();
			return $this->getDefaultConfiguration();
		}

		$config = $this->fetchConfigurationFromDatabase();
		$defaults = $this->getDefaultConfiguration();

		foreach ($defaults as $group => $entries) {
			if (!is_array($entries)) {
				continue;
			}

			foreach ($entries as $name => $value) {
				if (!isset($config[$group]) || !array_key_exists($name, $config[$group])) {
					$this->insertConfigValue((string) $group, (string) $name, $value);
					if (!isset($config[$group]) || !is_array($config[$group])) {
						$config[$group] = [];
					}
					$config[$group][$name] = $value;
				}
			}
		}

		return $config;
	}

	private function tableExists(): bool {
		$query = "SHOW TABLES LIKE 'base3_configuration'";
		$result = $this->database->listQuery($query);
		return !empty($result);
	}

	private function createAndSeedTable(): void {
		$this->database->nonQuery("
			CREATE TABLE IF NOT EXISTS `base3_configuration` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`group` varchar(100) NOT NULL,
				`name` varchar(100) NOT NULL,
				`value` text NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `group` (`group`, `name`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
		");

		$defaults = $this->getDefaultConfiguration();
		foreach ($defaults as $group => $entries) {
			if (!is_array($entries)) {
				continue;
			}
			foreach ($entries as $name => $value) {
				$this->insertConfigValue((string) $group, (string) $name, $value);
			}
		}
	}

	private function insertConfigValue(string $group, string $name, $value): void {
		$g = $this->database->escape($group);
		$n = $this->database->escape($name);

		if (is_array($value) || is_object($value)) {
			$value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		if ($value === null) {
			$value = '';
		}

		$v = $this->database->escape((string) $value);

		$this->database->nonQuery("
			INSERT INTO `base3_configuration` (`group`, `name`, `value`)
			VALUES ('$g', '$n', '$v')
			ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
		");
	}

	private function fetchConfigurationFromDatabase(): array {
		$query = "SELECT `group`, `name`, `value` FROM `base3_configuration`";
		$rows = $this->database->multiQuery($query);

		$config = [];
		foreach ($rows as $row) {
			$group = $row['group'] ?? '';
			$name = $row['name'] ?? '';
			$value = $row['value'] ?? '';

			if (!is_string($group) || $group === '' || !is_string($name) || $name === '') {
				continue;
			}

			if (is_string($value)) {
				$trim = trim($value);
				if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
					$decoded = json_decode($trim, true);
					if (json_last_error() === JSON_ERROR_NONE) {
						$value = $decoded;
					}
				}
			}

			if (!isset($config[$group]) || !is_array($config[$group])) {
				$config[$group] = [];
			}
			$config[$group][$name] = $value;
		}

		return $config;
	}
}
