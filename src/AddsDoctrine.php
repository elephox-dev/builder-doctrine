<?php
declare(strict_types=1);

namespace Elephox\Builder\Doctrine;

use Doctrine\ORM\Configuration as DoctrineConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup as DoctrineSetup;
use Elephox\Configuration\Contract\ConfigurationRoot;
use Elephox\DI\Contract\ServiceCollection;
use Elephox\Web\ConfigurationException;
use Elephox\Web\Contract\WebEnvironment;

trait AddsDoctrine
{
    abstract protected function getServices(): ServiceCollection;

    /**
     * @param null|callable(mixed): \Doctrine\ORM\Configuration $setup
     */
    public function addDoctrine(?callable $setup = null): void
    {
        $this->getServices()->addSingleton(
            EntityManagerInterface::class,
            EntityManager::class,
            implementationFactory: function (ConfigurationRoot $configuration) use ($setup): EntityManagerInterface {
                $setup ??= static function (ConfigurationRoot $conf, WebEnvironment $env): DoctrineConfiguration {
                    /** @var scalar|null $setupDriver */
                    $setupDriver = $conf['doctrine:metadata:driver'];
                    if (!is_string($setupDriver)) {
                        throw new ConfigurationException('Doctrine configuration error: "doctrine:metadata:driver" must be a string.');
                    }

                    $setupMethod = match ($setupDriver) {
                        'annotation' => 'createAnnotationMetadataConfiguration',
                        'yaml' => 'createYAMLMetadataConfiguration',
                        'xml' => 'createXMLMetadataConfiguration',
                        default => throw new ConfigurationException('Unsupported doctrine metadata driver: ' . $setupDriver),
                    };

                    /** @var DoctrineConfiguration */
                    return DoctrineSetup::{$setupMethod}(
                        $conf['doctrine:metadata:paths'],
                        $conf['doctrine:dev'] ?? $env->isDevelopment(),
                    );
                };

                /** @psalm-suppress ArgumentTypeCoercion */
                $setupConfig = $this->getServices()->resolver()->callback($setup);

                /** @var array<string, mixed>|null $connection */
                $connection = $configuration['doctrine:connection'];
                if (!is_array($connection)) {
                    throw new ConfigurationException('No doctrine connection specified at "doctrine:connection"');
                }

                /** @var EntityManager */
                return EntityManager::create($connection, $setupConfig);
            },
        );
    }
}
