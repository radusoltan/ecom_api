<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Command;

use App\Internationalization\Domain\Service\ImportResult;
use App\Internationalization\Domain\Service\TranslationImportService;
use App\Internationalization\Infrastructure\Cache\TranslationCacheService;
use App\Internationalization\Infrastructure\Parser\CSVTranslationParser;
use App\Internationalization\Infrastructure\Parser\JSONTranslationParser;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Import Translations Command Handler
 *
 * Orchestrates the translation import process:
 * 1. Parse uploaded file (CSV or JSON)
 * 2. Validate data format
 * 3. Import translations via domain service
 * 4. Invalidate cache (bulk operation for all locales/domains)
 * 5. Return import result with statistics
 */
#[AsMessageHandler]
final readonly class ImportTranslationsHandler
{
    public function __construct(
        private TranslationImportService $importService,
        private CSVTranslationParser $csvParser,
        private JSONTranslationParser $jsonParser,
        private TranslationCacheService $cacheService,
    ) {}

    public function __invoke(ImportTranslations $command): ImportResult
    {
        // Parse file based on format
        $data = match ($command->format) {
            'csv' => $this->csvParser->parse($command->file),
            'json' => $this->jsonParser->parse($command->file),
            default => throw new \InvalidArgumentException(sprintf(
                'Unsupported format "%s". Supported formats: csv, json',
                $command->format
            )),
        };

        // Import translations
        $result = $this->importService->import(
            tenantId: $command->tenantId,
            data: $data,
            dryRun: $command->dryRun,
        );

        // Invalidate all caches after successful import (only if not dry-run)
        if (!$command->dryRun && $result->isSuccessful()) {
            $this->cacheService->invalidateAll($command->tenantId);
        }

        return $result;
    }
}
