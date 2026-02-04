<?php declare(strict_types=1);

namespace Base3Wordpress;

use Base3\Api\ISystemService;

/**
 * WordPress system service for embedded BASE3.
 *
 * Host system: WordPress
 * Embedded system: BASE3
 */
final class WordpressSystemService implements ISystemService {

	public function getHostSystemName(): string {
		return 'WordPress';
	}

	public function getHostSystemVersion(): string {
		// WordPress provides the version via get_bloginfo('version').
		if (function_exists('get_bloginfo')) {
			$v = (string) get_bloginfo('version');
			return trim($v);
		}
		return '';
	}

	public function getEmbeddedSystemName(): string {
		return 'BASE3';
	}

	public function getEmbeddedSystemVersion(): string {
		return $this->getBase3Version();
	}

	/**
	 * Reads the BASE3 version from the VERSION file in DIR_ROOT.
	 *
	 * Rules:
	 * - If DIR_ROOT is not defined, return "".
	 * - If the VERSION file is missing or unreadable, return "".
	 * - The file is expected to contain the version as a single line (e.g. "4.0.1").
	 * - Content is trimmed; empty result returns "".
	 * - No exceptions are thrown and no warnings should be emitted.
	 */
	protected function getBase3Version(): string {
		if (!defined('DIR_ROOT')) {
			return '';
		}

		$path = rtrim((string) DIR_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'VERSION';
		if (!is_file($path) || !is_readable($path)) {
			return '';
		}

		$content = @file_get_contents($path);
		if ($content === false) {
			return '';
		}

		$version = trim($content);
		if ($version === '') {
			return '';
		}

		if (str_contains($version, "\n") || str_contains($version, "\r")) {
			$version = trim(strtok($version, "\r\n"));
			if ($version === '') {
				return '';
			}
		}

		return $version;
	}
}
