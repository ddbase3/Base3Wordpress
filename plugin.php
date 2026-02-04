<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Embedded BASE3 bootstrap for WordPress.
 *
 * Current scope:
 * - Define directory constants (WordPress-specific)
 * - Register BASE3 autoloader (without global DEBUG echo spam)
 * - Route BASE3 requests via "?name=...&out=..." while handing unknown names back to WordPress
 */
final class Base3Wordpress {
	private static bool $booted = false;

	public static function boot(): void {
		if (self::$booted) {
			return;
		}
		self::$booted = true;

		self::defineConstants();
		self::ensureRuntimeDirs();
		self::registerAutoloader();
		self::registerWordpressEntryPoint();
	}

	private static function defineConstants(): void {
		$base3Dir = realpath(__DIR__ . '/..');
		if ($base3Dir === false) {
			return;
		}
		$base3Dir .= DIRECTORY_SEPARATOR;

		$frameworkDir = realpath($base3Dir . 'Base3Framework');
		if ($frameworkDir === false) {
			return;
		}
		$frameworkDir .= DIRECTORY_SEPARATOR;

		if (!defined('DIR_BASE3')) define('DIR_BASE3', $base3Dir);
		if (!defined('DIR_FRAMEWORK')) define('DIR_FRAMEWORK', $frameworkDir);

		if (!defined('DIR_ROOT')) define('DIR_ROOT', DIR_FRAMEWORK);
		if (!defined('DIR_CNF')) define('DIR_CNF', DIR_ROOT . 'cnf' . DIRECTORY_SEPARATOR);
		if (!defined('DIR_SRC')) define('DIR_SRC', DIR_ROOT . 'src' . DIRECTORY_SEPARATOR);
		if (!defined('DIR_TEST')) define('DIR_TEST', DIR_ROOT . 'test' . DIRECTORY_SEPARATOR);
		if (!defined('DIR_TPL')) define('DIR_TPL', DIR_ROOT . 'tpl' . DIRECTORY_SEPARATOR);
		if (!defined('DIR_USERFILES')) define('DIR_USERFILES', DIR_ROOT . 'userfiles' . DIRECTORY_SEPARATOR);

		// Base3 plugins live under wp-content/plugins/base3/<PluginName>/...
		if (!defined('DIR_PLUGIN')) define('DIR_PLUGIN', DIR_BASE3);

		// Runtime dirs used by ClassMap / caches
		if (!defined('DIR_TMP')) define('DIR_TMP', DIR_BASE3 . 'tmp' . DIRECTORY_SEPARATOR);
		if (!defined('DIR_LOCAL')) define('DIR_LOCAL', DIR_TMP);
	}

	private static function ensureRuntimeDirs(): void {
		if (!is_dir(DIR_TMP)) {
			@mkdir(DIR_TMP, 0775, true);
		}
	}

	private static function registerAutoloader(): void {
		$composerAutoload = DIR_ROOT . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
		if (file_exists($composerAutoload)) {
			require_once $composerAutoload;
		}

		$autoloaderFile = DIR_SRC . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';
		if (!file_exists($autoloaderFile)) {
			return;
		}

		// Prevent global DEBUG echo spam in WordPress runtime.
		if (getenv('DEBUG') === '1' || getenv('DEBUG') === '2') {
			putenv('DEBUG=0');
		}

		require_once $autoloaderFile;

		if (class_exists(\Base3\Core\Autoloader::class)) {
			\Base3\Core\Autoloader::register();
		}
	}

	private static function registerWordpressEntryPoint(): void {
		add_action('template_redirect', function() {
			$name = isset($_GET['name']) ? (string) $_GET['name'] : '';
			if ($name === '') {
				return;
			}

			// Ensure BASE3 runtime dirs exist for classmap/cache generation.
			self::ensureRuntimeDirs();

			// Handover rule: if BASE3 has no output for this name, WordPress continues normally.
			if (!class_exists(\Base3Wordpress\WordpressBootstrap::class)) {
				return;
			}

			if (!\Base3Wordpress\WordpressBootstrap::hasOutput($name)) {
				return;
			}

			// Run BASE3 routed output and finish the request here.
			$out = \Base3Wordpress\WordpressBootstrap::run();

			// If "out=json" is requested, set a minimal content-type.
			// The BASE3 output implementation may still override/emit headers itself.
			$reqOut = isset($_GET['out']) ? (string) $_GET['out'] : 'html';
			if ($reqOut === 'json') {
				header('Content-Type: application/json; charset=utf-8');
			}

			echo $out;
			exit;
		}, 1);
	}
}

add_action('plugins_loaded', function() {
	Base3Wordpress::boot();
}, 1);
