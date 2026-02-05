<?php declare(strict_types=1);

namespace Base3Wordpress\Service;

use Base3\Accesscontrol\Api\IAccesscontrol;

/**
 * WordPress access control adapter.
 *
 * Maps BASE3 user context to WordPress' current user.
 */
final class WordpressAccesscontrol implements IAccesscontrol {
	private bool $authenticated = false;
	private mixed $userid = null;

	public function authenticate(): void {
		if ($this->authenticated) {
			return;
		}
		$this->authenticated = true;

		// Trigger WordPress to load the current user from cookies/session if available.
		if (function_exists('wp_get_current_user')) {
			wp_get_current_user();
		}

		if (function_exists('get_current_user_id')) {
			$this->userid = get_current_user_id();
		} else {
			$this->userid = null;
		}
	}

	public function getUserId(): mixed {
		if (!$this->authenticated) {
			$this->authenticate();
		}
		return $this->userid;
	}
}
