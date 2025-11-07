<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Query;

use App\Internationalization\Domain\Service\TranslationExportService;
use App\Internationalization\Infrastructure\Parser\CSVTranslationParser;
use App\Internationalization\Infrastructure\Parser\JSONTranslationParser;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Export Translations Query Handler.
 *
 * Orchestrates the translation export process:
 * 1. Fetch translations from repository with filters
 * 2. Convert to array format
 * 3. Format as CSV or JSON
 * 4. Return formatted string ready for download
 */
#[AsMessageHandler]
final readonly class ExportTranslationsHandler
{
    public function __construct(
        private TranslationExportService $exportService,
        private CSVTranslationParser $csvParser,
        private JSONTranslationParser $jsonParser,
    ) {
    }

    public function __invoke(ExportTranslations $query): string
    {
        // Fetch translations with filters
        $entries = $this->exportService->export(
            tenantId: $query->tenantId,
            filters: $query->filters,
        );

        // Convert to array format
        $data = $this->exportService->toArrayFormat($entries);

        // Format based on requested format
        return match ($query->format) {
            'csv' => $this->csvParser->generateCSV($data, includeHeader: true),
            'json' => $this->jsonParser->generateJSON($data, prettyPrint: true),
            default => throw new \InvalidArgumentException(sprintf('Unsupported format "%s". Supported formats: csv, json', $query->format)),
        };
    }
}
