<?php
declare(strict_types=1);

namespace Elephox\Builder\Doctrine\Commands;

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Elephox\Console\Command\CommandInvocation;
use Elephox\Console\Command\CommandTemplateBuilder;
use Elephox\Console\Command\Contract\CommandHandler;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class DoctrineCommand implements CommandHandler
{
	public function __construct(
		private readonly EntityManagerProvider $entityManagerProvider,
	)
	{
	}

	public function configure(CommandTemplateBuilder $builder): void
	{
		$builder
			->name('doc')
			->required('command', 'The command to relay to the doctrine executable')
		;
	}

	/**
	 * @throws \Exception
	 */
	public function handle(CommandInvocation $command): int|null
	{
		$doctrineCommand = $command->getArgument('command')->value;

		return ConsoleRunner::createApplication($this->entityManagerProvider)->run(
			new ArrayInput([
				'command' => $doctrineCommand,
				...$command->raw->arguments->toList(),
			]),
			new ConsoleOutput()
		);
	}
}
