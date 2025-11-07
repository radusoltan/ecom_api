<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Console;

use App\Inventory\Application\Command\ReleaseExpiredReservations\ReleaseExpiredReservationsCommand as ReleaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Console command to release expired stock reservations.
 *
 * This command should be scheduled to run periodically (e.g., every minute):
 *
 * Cron example:
 * * * * * * php bin/console inventory:release-expired-reservations >> /var/log/reservations.log 2>&1
 *
 * Or using Symfony Scheduler (recommended):
 * Add to config/packages/scheduler.yaml
 */
#[AsCommand(
    name: 'inventory:release-expired-reservations',
    description: 'Release expired stock reservations (runs automatically via scheduler)',
)]
final class ReleaseExpiredReservationsCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Releasing expired stock reservations...');

        try {
            $this->messageBus->dispatch(new ReleaseCommand());

            $io->success('Expired reservations release process completed.');

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error(sprintf('Failed to release expired reservations: %s', $exception->getMessage()));

            return Command::FAILURE;
        }
    }
}
