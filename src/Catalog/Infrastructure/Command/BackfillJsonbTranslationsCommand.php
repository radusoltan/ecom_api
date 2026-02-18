<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:i18n:backfill-jsonb',
    description: 'Backfill JSONB translations from Gedmo ext_translations table'
)]
final class BackfillJsonbTranslationsCommand extends Command
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'batch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Batch size for processing',
                self::BATCH_SIZE
            )
            ->addOption(
                'tenant',
                't',
                InputOption::VALUE_REQUIRED,
                'Specific tenant ID to process (* for all)',
                '*'
            )
            ->addOption(
                'entity',
                'e',
                InputOption::VALUE_REQUIRED,
                'Entity type to process (product|category|all)',
                'all'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run without making any changes'
            )
            ->addOption(
                'resume',
                'r',
                InputOption::VALUE_NONE,
                'Resume from last processed position'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('JSONB Translation Backfill');

        $batchSize = (int) $input->getOption('batch');
        $tenantId = $input->getOption('tenant');
        $entityType = $input->getOption('entity');
        $dryRun = $input->getOption('dry-run');
        $resume = $input->getOption('resume');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No changes will be made');
        }

        // Check if ext_translations table exists
        if (!$this->checkExtTranslationsTable($io)) {
            return Command::FAILURE;
        }

        // Process entities based on option
        $entities = match ($entityType) {
            'product' => ['catalog_products'],
            'category' => ['catalog_categories'],
            'all' => ['catalog_products', 'catalog_categories'],
            default => [],
        };

        if (empty($entities)) {
            $io->error('Invalid entity type specified');

            return Command::FAILURE;
        }

        $totalProcessed = 0;

        foreach ($entities as $tableName) {
            $io->section(sprintf('Processing %s', $tableName));

            $processed = $this->processEntity(
                $io,
                $tableName,
                $tenantId,
                $batchSize,
                $dryRun,
                $resume
            );

            $totalProcessed += $processed;
        }

        $io->success(sprintf(
            'Backfill completed! Processed %d total records',
            $totalProcessed
        ));

        return Command::SUCCESS;
    }

    private function checkExtTranslationsTable(SymfonyStyle $io): bool
    {
        try {
            $result = $this->connection->fetchOne(
                "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'ext_translations')"
            );

            if (!$result) {
                $io->error('ext_translations table does not exist. Gedmo translations not found.');

                return false;
            }

            return true;
        } catch (\Exception $e) {
            $io->error('Failed to check ext_translations table: '.$e->getMessage());

            return false;
        }
    }

    private function processEntity(
        SymfonyStyle $io,
        string $tableName,
        string $tenantId,
        int $batchSize,
        bool $dryRun,
        bool $resume,
    ): int {
        $entityType = str_replace('catalog_', '', $tableName);
        $className = match ($entityType) {
            'products' => 'App\\Catalog\\Infrastructure\\Persistence\\Doctrine\\Entity\\ProductEntity',
            'categories' => 'App\\Catalog\\Infrastructure\\Persistence\\Doctrine\\Entity\\CategoryEntity',
            default => null,
        };

        if (!$className) {
            $io->error('Unknown entity type: '.$entityType);

            return 0;
        }

        // Get tracking info if resuming
        $lastProcessedId = null;
        if ($resume) {
            $lastProcessedId = $this->getLastProcessedId($entityType, $tenantId);
            if ($lastProcessedId) {
                $io->info(sprintf('Resuming from ID: %s', $lastProcessedId));
            }
        }

        // Count total entities to process
        $totalCount = $this->getTotalCount($tableName, $tenantId, $lastProcessedId);
        if (0 === $totalCount) {
            $io->info('No entities to process');

            return 0;
        }

        $io->info(sprintf('Found %d entities to process', $totalCount));

        // Create progress bar
        $progressBar = new ProgressBar($output, $totalCount);
        $progressBar->start();

        $processed = 0;
        $offset = 0;

        while ($offset < $totalCount) {
            // Get batch of entities
            $entities = $this->getEntitiesBatch(
                $tableName,
                $tenantId,
                $batchSize,
                $offset,
                $lastProcessedId
            );

            if (empty($entities)) {
                break;
            }

            // Process each entity
            foreach ($entities as $entity) {
                $translations = $this->getEntityTranslations($className, $entity['id']);

                if (!empty($translations)) {
                    $jsonbData = $this->convertToJsonb($translations);

                    if (!$dryRun) {
                        $this->updateEntityTranslations(
                            $tableName,
                            $entity['id'],
                            $jsonbData
                        );

                        // Update tracking
                        $this->updateTracking($entityType, $tenantId, $entity['id']);
                    }
                }

                ++$processed;
                $progressBar->advance();
            }

            $offset += $batchSize;
        }

        $progressBar->finish();
        $io->newLine(2);

        return $processed;
    }

    private function getEntityTranslations(string $className, string $entityId): array
    {
        $sql = <<<SQL
            SELECT locale, field, content
            FROM ext_translations
            WHERE object_class = :class
            AND foreign_key = :id
            AND field IN ('name', 'description', 'shortDescription')
        SQL;

        return $this->connection->fetchAllAssociative($sql, [
            'class' => $className,
            'id' => $entityId,
        ]);
    }

    private function convertToJsonb(array $translations): array
    {
        $jsonbData = [
            'name' => [],
            'description' => [],
            'short_description' => [],
        ];

        foreach ($translations as $translation) {
            $field = match ($translation['field']) {
                'shortDescription' => 'short_description',
                default => $translation['field'],
            };

            if (isset($jsonbData[$field])) {
                // Normalize locale format (e.g., en-US to en_US)
                $locale = str_replace('-', '_', $translation['locale']);
                $jsonbData[$field][$locale] = $translation['content'];
            }
        }

        return $jsonbData;
    }

    private function updateEntityTranslations(
        string $tableName,
        string $entityId,
        array $jsonbData,
    ): void {
        $sql = sprintf(
            'UPDATE %s SET
                name_translations = :name,
                description_translations = :description%s
            WHERE id = :id',
            $tableName,
            'catalog_products' === $tableName ? ', short_description_translations = :short_description' : ''
        );

        $params = [
            'id' => $entityId,
            'name' => json_encode($jsonbData['name']),
            'description' => json_encode($jsonbData['description']),
        ];

        if ('catalog_products' === $tableName) {
            $params['short_description'] = json_encode($jsonbData['short_description']);
        }

        $this->connection->executeStatement($sql, $params);
    }

    private function getTotalCount(string $tableName, string $tenantId, ?string $lastProcessedId): int
    {
        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE 1=1', $tableName);
        $params = [];

        if ('*' !== $tenantId) {
            $sql .= ' AND tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        if ($lastProcessedId) {
            $sql .= ' AND id > :last_id';
            $params['last_id'] = $lastProcessedId;
        }

        return (int) $this->connection->fetchOne($sql, $params);
    }

    private function getEntitiesBatch(
        string $tableName,
        string $tenantId,
        int $batchSize,
        int $offset,
        ?string $lastProcessedId,
    ): array {
        $sql = sprintf('SELECT id, tenant_id FROM %s WHERE 1=1', $tableName);
        $params = [];

        if ('*' !== $tenantId) {
            $sql .= ' AND tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        if ($lastProcessedId) {
            $sql .= ' AND id > :last_id';
            $params['last_id'] = $lastProcessedId;
        }

        $sql .= ' ORDER BY id ASC LIMIT :limit OFFSET :offset';
        $params['limit'] = $batchSize;
        $params['offset'] = $offset;

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    private function getLastProcessedId(string $entityType, string $tenantId): ?string
    {
        $sql = 'SELECT last_processed_id FROM i18n_backfill_tracking
                WHERE entity_type = :entity_type AND tenant_id = :tenant_id';

        $result = $this->connection->fetchOne($sql, [
            'entity_type' => $entityType,
            'tenant_id' => '*' === $tenantId ? 'all' : $tenantId,
        ]);

        return $result ?: null;
    }

    private function updateTracking(string $entityType, string $tenantId, string $lastId): void
    {
        $sql = <<<SQL
            INSERT INTO i18n_backfill_tracking (entity_type, tenant_id, last_processed_id, status, started_at)
            VALUES (:entity_type, :tenant_id, :last_id, 'processing', NOW())
            ON CONFLICT (entity_type, tenant_id)
            DO UPDATE SET
                last_processed_id = EXCLUDED.last_processed_id,
                processed_count = i18n_backfill_tracking.processed_count + 1
        SQL;

        $this->connection->executeStatement($sql, [
            'entity_type' => $entityType,
            'tenant_id' => '*' === $tenantId ? 'all' : $tenantId,
            'last_id' => $lastId,
        ]);
    }
}
