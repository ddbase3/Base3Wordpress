<?php declare(strict_types=1);

namespace Base3Wordpress\Service;

use Base3\Core\Request;

final class WordpressRequest extends Request {
	/** Create a new instance initialized from superglobals (subclass-safe). */
	public static function fromGlobals(): self {
		$self = new self();
		$self->initFromGlobals();
		return $self;
	}

	/** Set a GET parameter for this request instance. */
	public function setGetParam(string $key, mixed $value): void {
		if (is_array($this->get)) {
			$this->get[$key] = $value;
			return;
		}

		// ArrayAccess path
		$this->get[$key] = $value;
	}

	/** Remove a GET parameter for this request instance. */
	public function unsetGetParam(string $key): void {
		if (is_array($this->get)) {
			unset($this->get[$key]);
			return;
		}

		// ArrayAccess path
		if (isset($this->get[$key])) {
			unset($this->get[$key]);
		}
	}
}
