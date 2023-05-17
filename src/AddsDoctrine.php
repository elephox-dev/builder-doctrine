<?php
declare(strict_types=1);

namespace Elephox\Builder\Doctrine;

use Doctrine\ORM\Configuration as DoctrineConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Elephox\Cache\APCu\APCuCache as ElephoxApcuCache;
use Elephox\Cache\APCu\APCuCacheConfiguration as ElephoxApcuCacheConfiguration;
use Elephox\Cache\Contract\Cache as ElephoxCacheContract;
use Elephox\Cache\Contract\CacheConfiguration as ElephoxCacheConfiguration;
use Elephox\Cache\InMemoryCache as ElephoxInMemoryCache;
use Elephox\Cache\InMemoryCacheConfiguration as ElephoxInMemoryCacheConfiguration;
use Elephox\DI\Contract\ServiceCollection;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter as SymfonyInMemoryCache;

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

		$this->getServices()->tryAddSingleton(EntityManagerProvider::class, DefaultEntityManagerProvider::class);

		$this->getServices()->addSingleton(
			EntityManagerInterface::class,
			factory: static fn (EntityManagerProvider $provider): EntityManagerInterface => $provider->getDefaultManager(),
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

		if (!interface_exists(ElephoxCacheContract::class)) {
			throw new RuntimeException("You haven't added a cache implementation with the " . CacheItemPoolInterface::class . ' service. Please install elephox/cache or symfony/cache and register an implementation in your container');
		}

		if (class_exists(ElephoxApcuCache::class)) {
			if (!$this->getServices()->has(ElephoxApcuCacheConfiguration::class)) {
				$this->getServices()->addSingleton(ElephoxApcuCacheConfiguration::class, ElephoxApcuCacheConfiguration::class);
				$this->getServices()->addSingleton(ElephoxCacheConfiguration::class, factory: static fn (ElephoxApcuCacheConfiguration $c): ElephoxApcuCacheConfiguration => $c);
			}

			$this->getServices()->addSingleton(CacheItemPoolInterface::class, ElephoxApcuCache::class);

			return;
		}

		// TODO: implement check for memcached and redis elephox cache implementations

		if (!$this->getServices()->has(ElephoxInMemoryCacheConfiguration::class)) {
			$this->getServices()->addSingleton(ElephoxInMemoryCacheConfiguration::class, ElephoxInMemoryCacheConfiguration::class);
			$this->getServices()->addSingleton(ElephoxCacheConfiguration::class, factory: static fn (ElephoxInMemoryCacheConfiguration $c): ElephoxInMemoryCacheConfiguration => $c);
		}

		$this->getServices()->addSingleton(CacheItemPoolInterface::class, ElephoxInMemoryCache::class);
	}
}
