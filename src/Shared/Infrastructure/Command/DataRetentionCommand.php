<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Command;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data:cleanup',
    description: 'Clean up expired data based on retention policies',
)]
final class DataRetentionCommand extends Command
{
    /**
     * @var array<string, array{table: string, column: string, days: int}>
     */
    private const RETENTION_POLICIES = [
        'audit_logs' => ['table' => 'audit_log', 'column' => 'occurred_at', 'days' => 90],
        'sessions' => ['table' => 'refresh_tokens', 'column' => 'valid', 'days' => 30],
        'notifications' => ['table' => 'notifications', 'column' => 'created_at', 'days' => 60],
        'abandoned_carts' => ['table' => 'carts', 'column' => 'updated_at', 'days' => 30],
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting')
            ->addOption('policy', null, InputOption::VALUE_REQUIRED, 'Run only a specific policy (audit_logs, sessions, notifications, abandoned_carts)')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of rows to delete per batch', '1000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $specificPolicy = $input->getOption('policy');
        $batchSize = max(1, (int) $input->getOption('batch-size'));

        $io->title('Data Retention Cleanup');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No data will be deleted');
        }

        $this->connection->executeStatement('SET ROLE ecom_admin');

        $policies = self::RETENTION_POLICIES;

        if (null !== $specificPolicy) {
            if (!is_string($specificPolicy) || !isset($policies[$specificPolicy])) {
                $io->error(sprintf(
                    'Unknown policy: "%s". Available policies: %s',
                    (string) $specificPolicy,
                    implode(', ', array_keys($policies)),
                ));

                $this->connection->executeStatement('RESET ROLE');

                return Command::FAILURE;
            }

            $policies = [$specificPolicy => $policies[$specificPolicy]];
        }

        $totalDeleted = 0;

        foreach ($policies as $name => $policy) {
            try {
                $deleted = $this->applyPolicy($io, $name, $policy, $dryRun, $batchSize);
                $totalDeleted += $deleted;
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to apply policy "%s": %s', $name, $e->getMessage()));
                $this->logger->error('Data retention policy failed', [
                    'policy' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->connection->executeStatement('RESET ROLE');

        $io->success(sprintf(
            'Data retention cleanup complete. Total rows %s: %d',
            $dryRun ? 'that would be deleted' : 'deleted',
            $totalDeleted,
        ));

        $this->logger->info('Data retention cleanup completed', [
            'total_deleted' => $totalDeleted,
            'dry_run' => $dryRun,
        ]);

        return Command::SUCCESS;
    }

    /**
     * @param array{table: string, column: string, days: int} $policy
     */
    private function applyPolicy(
        SymfonyStyle $io,
        string $name,
        array $policy,
        bool $dryRun,
        int $batchSize,
    ): int {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $policy['days']));

        $tableExists = (bool) $this->connection->executeQuery(
            'SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table)',
            ['schema' => 'public', 'table' => $policy['table']],
        )->fetchOne();

        if (!$tableExists) {
            $io->note(sprintf('Skipping "%s": table "%s" does not exist', $name, $policy['table']));

            return 0;
        }

        $extraCondition = 'abandoned_carts' === $name ? " AND status = 'active'" : '';
        $cutoffFormatted = $cutoffDate->format('Y-m-d H:i:s');

        $count = (int) $this->connection->executeQuery(
            sprintf(
                'SELECT COUNT(*) FROM %s WHERE %s < :cutoff%s',
                $policy['table'],
                $policy['column'],
                $extraCondition,
            ),
            ['cutoff' => $cutoffFormatted],
        )->fetchOne();

        $io->section(sprintf('Policy: %s (retain %d days, table: %s)', $name, $policy['days'], $policy['table']));
        $io->text(sprintf('  Cutoff date: %s', $cutoffFormatted));
        $io->text(sprintf('  Rows to delete: %d', $count));

        if ($dryRun || 0 === $count) {
            return $count;
        }

        $deleted = 0;
        do {
            $affected = (int) $this->connection->executeStatement(
                sprintf(
                    'DELETE FROM %s WHERE ctid = ANY(ARRAY(SELECT ctid FROM %s WHERE %s < :cutoff%s LIMIT :batch))',
                    $policy['table'],
                    $policy['table'],
                    $policy['column'],
                    $extraCondition,
                ),
                ['cutoff' => $cutoffFormatted, 'batch' => $batchSize],
            );

            $deleted += $affected;

            if ($affected > 0) {
                $io->text(sprintf('  Deleted batch: %d (total: %d/%d)', $affected, $deleted, $count));
            }
        } while ($affected > 0 && $deleted < $count);

        $this->logger->info('Data retention policy applied', [
            'policy' => $name,
            'table' => $policy['table'],
            'retention_days' => $policy['days'],
            'deleted' => $deleted,
        ]);

        return $deleted;
    }
}
