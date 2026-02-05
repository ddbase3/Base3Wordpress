<?php declare(strict_types=1);

namespace Base3Wordpress;

use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IOutput;
use Base3\Api\IPlugin;
use Base3\Api\IRequest;
use Base3\Api\ISystemService;
use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Configuration\Api\IConfiguration;
use Base3\Core\PluginClassMap;
use Base3\Core\ServiceLocator;
use Base3\Database\Api\IDatabase;
use Base3\Hook\HookManager;
use Base3\Hook\IHookListener;
use Base3\Hook\IHookManager;
use Base3\Logger\Api\ILogger;
use Base3\Logger\ScopedDatabaseLogger\ScopedDatabaseLogger;
use Base3\ServiceSelector\Api\IServiceSelector;
use Base3\ServiceSelector\Standard\StandardServiceSelector;

final class WordpressBootstrap {
	private static ?ServiceLocator $container = null;
	private static bool $initialized = false;

	public static function getContainer(): ServiceLocator {
		if (self::$container !== null) {
			return self::$container;
		}

		$container = new ServiceLocator();
		ServiceLocator::useInstance($container);

		$container
			->set('servicelocator', $container, IContainer::SHARED)
			->set(ISystemService::class, fn() => new WordpressSystemService(), IContainer::SHARED)

			// Use a WordPress-specific request implementation that can be parameterized safely.
			->set(IRequest::class, fn() => WordpressRequest::fromGlobals(), IContainer::SHARED)

			->set(IContainer::class, 'servicelocator', IContainer::ALIAS)

			->set(IHookManager::class, fn() => new HookManager(), IContainer::SHARED)

			->set('classmap', fn($c) => new PluginClassMap($c->get(IContainer::class)), IContainer::SHARED)
			->set(IClassMap::class, 'classmap', IContainer::ALIAS)

			// WordPress access control adapter
			->set('accesscontrol', fn() => new WordpressAccesscontrol(), IContainer::SHARED)
			->set(IAccesscontrol::class, 'accesscontrol', IContainer::ALIAS)

			// Database adapter (key service)
			->set('database', fn() => new WordpressDatabase(), IContainer::SHARED)
			->set(IDatabase::class, 'database', IContainer::ALIAS)

			// Configuration: DB-backed (depends on IDatabase)
			->set('configuration', fn($c) => new WordpressConfiguration($c->get(IDatabase::class)), IContainer::SHARED)
			->set(IConfiguration::class, 'configuration', IContainer::ALIAS)

			// Logger: use scoped database logger by default
			->set('logger', fn($c) => new ScopedDatabaseLogger($c->get(IDatabase::class)), IContainer::SHARED)
			->set(ILogger::class, 'logger', IContainer::ALIAS)

			->set(IServiceSelector::class, fn($c) => new StandardServiceSelector($c), IContainer::SHARED)
			->set('middlewares', [])
		;

		self::$container = $container;
		return self::$container;
	}

	public static function initOnce(): void {
		if (self::$initialized) {
			return;
		}
		self::$initialized = true;

		$container = self::getContainer();

		$hookManager = $container->get(IHookManager::class);
		$listeners = $container->get(IClassMap::class)->getInstancesByInterface(IHookListener::class);
		foreach ($listeners as $listener) {
			$hookManager->addHookListener($listener);
		}
		$hookManager->dispatch('bootstrap.init');

		$plugins = $container->get(IClassMap::class)->getInstancesByInterface(IPlugin::class);
		foreach ($plugins as $plugin) {
			$plugin->init();
		}
		$hookManager->dispatch('bootstrap.start');

		$hookManager->dispatch('bootstrap.finish');
	}

	public static function hasOutput(string $name): bool {
		if ($name === '') {
			return false;
		}

		$container = self::getContainer();
		/** @var IClassMap $classmap */
		$classmap = $container->get(IClassMap::class);

		$instance = $classmap->getInstanceByInterfaceName(IOutput::class, $name);
		return $instance !== null;
	}

	public static function run(): string {
		self::initOnce();

		$container = self::getContainer();
		/** @var IServiceSelector $serviceSelector */
		$serviceSelector = $container->get(IServiceSelector::class);

		return $serviceSelector->go();
	}

	/**
	 * Run BASE3 output for a given name/out without touching PHP superglobals.
	 */
	public static function runName(string $name, string $out = 'html'): string {
		$name = trim($name);
		$out = trim($out);

		if ($name === '') {
			return '';
		}
		if ($out === '') {
			$out = 'html';
		}

		self::initOnce();

		$container = self::getContainer();
		$request = $container->get(IRequest::class);

		// We expect our WordpressRequest, but keep this robust.
		if (!$request instanceof WordpressRequest) {
			return self::run();
		}

		$prevName = $request->get('name', null);
		$prevOut = $request->get('out', null);

		$request->setGetParam('name', $name);
		$request->setGetParam('out', $out);

		try {
			$result = self::run();
		} finally {
			if ($prevName === null) {
				$request->unsetGetParam('name');
			} else {
				$request->setGetParam('name', $prevName);
			}

			if ($prevOut === null) {
				$request->unsetGetParam('out');
			} else {
				$request->setGetParam('out', $prevOut);
			}
		}

		return $result;
	}
}
