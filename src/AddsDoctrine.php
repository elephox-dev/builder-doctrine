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
use Elephox\Cache\Contract\CacheConfiguration as ElephoxCacheConfiguration;
use Elephox\Cache\InMemoryCache as ElephoxInMemoryCache;
use Elephox\Cache\InMemoryCacheConfiguration as ElephoxInMemoryCacheConfiguration;
use Symfony\Component\Cache\Adapter\ArrayAdapter as SymfonyInMemoryCache;
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
		if ($this->getServices()->has(CacheItemPoolInterface::class)) {
			return;
		}

		if (class_exists(SymfonyInMemoryCache::class)) {
			// Doctrine has its own method to create a cache instance. We won't interfere by registering a different cache driver.

			return;
		}

		if (!interface_exists(ElephoxCache::class)) {
			throw new RuntimeException("You haven't added a cache implementation with the " . CacheItemPoolInterface::class . " service. Please install elephox/cache or symfony/cache and register an implementation in your container");
		}

		if (class_exists(ElephoxApcuCache::class)) {
			if (!$this->getServices()->has(ElephoxApcuCacheConfiguration::class)) {
				$this->getServices()->addSingleton(ElephoxApcuCacheConfiguration::class, ElephoxApcuCacheConfiguration::class);
				$this->getServices()->setAlias(ElephoxCacheConfiguration::class, ElephoxApcuCacheConfiguration::class);
			}

			$this->getServices()->addSingleton(CacheItemPoolInterface::class, ElephoxApcuCache::class);

			return;
		}

		// TODO: implement check for memcached and redis elephox cache implementations

		if (!$this->getServices()->has(ElephoxInMemoryCacheConfiguration::class)) {
			$this->getServices()->addSingleton(ElephoxInMemoryCacheConfiguration::class, ElephoxInMemoryCacheConfiguration::class);
			$this->getServices()->setAlias(ElephoxCacheConfiguration::class, ElephoxInMemoryCacheConfiguration::class);
		}

		$this->getServices()->addSingleton(CacheItemPoolInterface::class, ElephoxInMemoryCache::class);
	}
}
