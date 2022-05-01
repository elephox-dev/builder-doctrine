<?php
declare(strict_types=1);

namespace Elephox\Builder\Doctrine;

use Doctrine\ORM\Configuration as DoctrineConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Elephox\Cache\APCu\APCuCache as ElephoxApcuCache;
use Elephox\Cache\APCu\APCuCacheConfiguration as ElephoxApcuCacheConfiguration;
use Elephox\Cache\Contract\Cache as ElephoxCache;
use Symfony\Component\Cache\Adapter\ArrayAdapter as SymfonyArrayCache;
use Elephox\DI\Contract\ServiceCollection;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

trait AddsDoctrine
{
	abstract protected function getServices(): ServiceCollection;

	/**
	 * @param null|callable(mixed): DoctrineConfiguration $setup
	 */
	public function addDoctrine(?callable $setup = null): void
	{
		$this->registerDefaultCacheDriver();

		if ($setup !== null) {
			$this->getServices()->addSingleton(DoctrineConfiguration::class, DoctrineConfiguration::class, $setup);
		}

		$this->getServices()->addSingleton(EntityManagerProvider::class, DefaultEntityManagerProvider::class);

		$this->getServices()->addSingleton(
			EntityManagerInterface::class,
			EntityManager::class,
			static function (DefaultEntityManagerProvider $provider): EntityManagerInterface {
				return $provider->getDefaultManager();
			},
		);
	}

	private function registerDefaultCacheDriver(): void
	{
		if ($this->getServices()->hasService(CacheItemPoolInterface::class)) {
			return;
		}

		if (class_exists(SymfonyArrayCache::class)) {
			// Doctrine has its own method to create a cache instance. We won't interfere by registering a default cache driver.

			return;
		}

		if (!class_exists(ElephoxCache::class)) {
			throw new RuntimeException("You haven't added a cache implementation with the " . CacheItemPoolInterface::class . " service. Please install elephox/cache or symfony/cache and register an implementation in your container");
		}

		if (class_exists(ElephoxApcuCache::class)) {
			if (!$this->getServices()->hasService(ElephoxApcuCacheConfiguration::class)) {
				$this->getServices()->addSingleton(ElephoxApcuCacheConfiguration::class, implementation: new ElephoxApcuCacheConfiguration());
			}

			$this->getServices()->addSingleton(CacheItemPoolInterface::class, ElephoxApcuCache::class);
		}

		// TODO: implement check for memcached and redis elephox caches

		if (!$this->getServices()->hasService(CacheItemPoolInterface::class)) {
			throw new RuntimeException("Could not register a default cache driver. Please install symfony/cache or add an Elephox cache implementation (e.g. elephox/apcu-cache)");
		}
	}
}
