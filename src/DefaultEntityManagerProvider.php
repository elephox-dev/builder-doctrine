<?php
declare(strict_types=1);

namespace Elephox\Builder\Doctrine;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration as DoctrineConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\UnknownManagerException;
use Elephox\Configuration\Contract\Configuration;
use Elephox\Configuration\Contract\Environment;
use Elephox\DI\Contract\Resolver;
use Elephox\DI\Contract\ServiceProvider;
use Elephox\Web\ConfigurationException;

class DefaultEntityManagerProvider implements EntityManagerProvider
{
	public const SUPPORTED_DRIVERS = [
		'attribute' => 'createAttributeMetadataConfiguration',
		'xml' => 'createXMLMetadataConfiguration',
		'without' => 'createConfiguration',
	];

	private ?EntityManager $entityManager = null;

	public function __construct(
		private readonly ServiceProvider $services,
		private readonly Configuration $configuration,
		private readonly Environment $environment,
	) {
	}

	public function getDefaultManager(bool $forceRecreate = false): EntityManager
	{
		if (!$forceRecreate && $this->entityManager !== null) {
			return $this->entityManager;
		}

		if ($this->services->has(DoctrineConfiguration::class)) {
			$config = $this->services->get(DoctrineConfiguration::class);
		} else {
			/** @var scalar|null $setupDriver */
			$setupDriver = $this->configuration['doctrine:metadata:driver'];
			if (!is_string($setupDriver)) {
				throw new ConfigurationException('Doctrine configuration error: "doctrine:metadata:driver" must be a string.');
			}

			$setupMethod = self::SUPPORTED_DRIVERS[$setupDriver] ?? null;
			if ($setupMethod === null) {
				throw new ConfigurationException('Unsupported doctrine metadata driver: ' . $setupDriver . '. Must be one of: ' . implode(', ', array_keys(self::SUPPORTED_DRIVERS)));
			}

			/** @var DoctrineConfiguration $config */
			$config = $this->services->get(Resolver::class)->callStaticMethod(
				ORMSetup::class,
				$setupMethod,
				[
					'paths' => $this->configuration['doctrine:metadata:paths'],
					'isDevMode' => $this->configuration['doctrine:dev'] ?? $this->environment->development,
					'proxyDir' => $this->configuration['doctrine:metadata:proxyDir'] ?? null,
					'isXsdValidationEnabled' => $this->configuration['doctrine:metadata:xsdValidation'] ?? null,
				],
			);
		}

		if ($this->services->has(DoctrineConnection::class)) {
			$connection = $this->services->get(DoctrineConnection::class);
		} else {
			/** @var array<string, mixed>|null $doctrineConnection */
			$connectionParams = $this->configuration['doctrine:connection'];
			if (!is_array($doctrineConnection)) {
				throw new ConfigurationException('No doctrine connection specified at "doctrine:connection"');
			}

			$connection = $this->services->get(Resolver::class)->call(
				DriverManager::getConnection(...),
				[
					'params' => $connectionParams,
					'config' => $config,
				],
			);
		}

		return $this->entityManager = $this->services->get(Resolver::class)->instantiate(
			EntityManager::class,
			[
				'conn' => $connection,
				'config' => $config,
			],
		);
	}

	public function getManager(string $name, bool $forceRecreate = false): EntityManager
	{
		if ($name !== 'default') {
			throw UnknownManagerException::unknownManager($name, ['default']);
		}

		return $this->getDefaultManager();
	}
}
