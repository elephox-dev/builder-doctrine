<?php
declare(strict_types=1);

namespace Elephox\Builder\Doctrine\Commands;

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Elephox\Console\Command\CommandInvocation;
use Elephox\Console\Command\CommandTemplateBuilder;
use Elephox\Console\Command\Contract\CommandHandler;
use Exception;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;

readonly class DoctrineCommand implements CommandHandler
{
	public function __construct(
		private EntityManagerProvider $entityManagerProvider,
	) {
	}

	public function configure(CommandTemplateBuilder $builder): void
	{
		$builder
			->setName('doc')
			->addArgument('command', description: 'The command to relay to the doctrine executable')
		;
	}

	/**
	 * @throws Exception
	 */
	public function handle(CommandInvocation $command): int|null
	{
		$doctrineCommand = substr($command->raw->commandLine, strpos($command->raw->commandLine, $command->arguments->get('command')->string()));

		return ConsoleRunner::createApplication($this->entityManagerProvider)->run(
			new StringInput($doctrineCommand),
			new ConsoleOutput(),
		);
	}
}
