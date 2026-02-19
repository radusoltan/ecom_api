<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Console;

use App\Payment\Domain\Repository\WebhookEventRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to clean up old webhook event deduplication records.
 *
 * Deletes records older than the configured retention period (default: 30 days).
 * Should be run periodically via cron or Symfony scheduler.
 *
 * Usage:
 *   php bin/console app:payment:cleanup-webhook-events
 *   php bin/console app:payment:cleanup-webhook-events --retention-days=60
 *   php bin/console app:payment:cleanup-webhook-events --dry-run
 */
#[AsCommand(
    name: 'app:payment:cleanup-webhook-events',
    description: 'Delete old webhook event deduplication records',
)]
final class CleanupWebhookEventsCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS = 30;

    public function __construct(
        private readonly WebhookEventRepositoryInterface $webhookEventRepository,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'retention-days',
                'r',
                InputOption::VALUE_REQUIRED,
                'Number of days to retain webhook event records',
                self::DEFAULT_RETENTION_DAYS
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show how many records would be deleted without actually deleting'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $retentionDays = (int) $input->getOption('retention-days');
        $isDryRun = (bool) $input->getOption('dry-run');

        if ($retentionDays < 1) {
            $io->error('Retention days must be at least 1.');

            return Command::FAILURE;
        }

        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $retentionDays));

        $io->title('Webhook Event Cleanup');
        $io->text(sprintf('Retention period: %d days (before %s)', $retentionDays, $cutoffDate->format('Y-m-d H:i:s')));

        if ($isDryRun) {
            $io->warning('DRY-RUN mode - no records will be deleted');
        }

        try {
            if ($isDryRun) {
                $io->info('Run without --dry-run to actually delete records.');

                return Command::SUCCESS;
            }

            $deletedCount = $this->webhookEventRepository->deleteOlderThan($cutoffDate);

            $this->logger->info('Webhook event cleanup completed', [
                'deleted_count' => $deletedCount,
                'retention_days' => $retentionDays,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            ]);

            $io->success(sprintf('Deleted %d webhook event record(s) older than %d days.', $deletedCount, $retentionDays));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('Webhook event cleanup failed', [
                'error' => $e->getMessage(),
            ]);

            $io->error(sprintf('Cleanup failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
