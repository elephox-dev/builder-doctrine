<?php
declare(strict_types=1);

namespace Elephox\Builder\Doctrine;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\Configuration as DoctrineConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ManagerException;
use Doctrine\ORM\ORMSetup as DoctrineSetup;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\UnknownManagerException;
use Elephox\Configuration\Contract\Configuration;
use Elephox\Configuration\Contract\Environment;
use Elephox\DI\Contract\ServiceCollection;
use Elephox\Web\ConfigurationException;

class DefaultEntityManagerProvider implements EntityManagerProvider
{
	public const SUPPORTED_DRIVERS = [
		'annotation' => 'createAnnotationMetadataConfiguration',
		'default' => 'createDefaultAnnotationDriver',
		'attribute' => 'createAttributeMetadataConfiguration',
		'xml' => 'createXMLMetadataConfiguration',
		'yaml' => 'createYAMLMetadataConfiguration',
		'without' => 'createConfiguration',
	];

	private ?EntityManager $entityManager = null;

	public function __construct(
		private readonly ServiceCollection $services,
		private readonly Configuration $configuration,
		private readonly Environment $environment
	)
	{
	}

	/**
	 * @throws ManagerException
	 * @throws Exception
	 */
	public function getDefaultManager(bool $forceRecreate = false): EntityManager
	{
		if (!$forceRecreate && $this->entityManager !== null) {
			return $this->entityManager;
		}

		if ($this->services->hasService(DoctrineConfiguration::class)) {
			$doctrineConfiguration = $this->services->requireService(DoctrineConfiguration::class);
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

			/** @var DoctrineConfiguration $doctrineConfiguration */
			$doctrineConfiguration = $this->services->resolver()->callStatic(
				DoctrineSetup::class,
				$setupMethod,
				[
					'paths' => $this->configuration['doctrine:metadata:paths'],
					'isDevMode' => $this->configuration['doctrine:dev'] ?? $this->environment->development,
					'proxyDir' => $this->configuration['doctrine:metadata:proxyDir'] ?? null,
				],
			);
		}

		/** @var array<string, mixed>|null $doctrineConnection */
		$doctrineConnection = $this->configuration['doctrine:connection'];
		if (!is_array($doctrineConnection)) {
			throw new ConfigurationException('No doctrine connection specified at "doctrine:connection"');
		}

		$this->entityManager = EntityManager::create($doctrineConnection, $doctrineConfiguration);

		return $this->entityManager;
	}

	/**
	 * @throws ManagerException
	 * @throws Exception
	 */
	public function getManager(string $name, bool $forceRecreate = false): EntityManager
	{
		if ($name !== 'default') {
			throw UnknownManagerException::unknownManager($name, ['default']);
		}

		return $this->getDefaultManager();
	}
}
