<?php

declare(strict_types=1);

namespace App\Pricing\Application\Service;

use App\Pricing\Application\DTO\ImportRow;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service for importing pricing data from CSV/JSON files.
 *
 * Business Rules:
 * - Maximum file size: 10MB
 * - Maximum rows per import: 10,000
 * - Supported formats: CSV, JSON
 * - Import is idempotent (can re-run same file)
 */
final class PricingImportService
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const MAX_ROWS = 10000;
    private const LARGE_FILE_THRESHOLD = 1000;

    public function __construct(
        private readonly ImportValidationService $validationService,
    ) {
    }

    /**
     * Parse and validate uploaded file.
     *
     * @return array<ImportRow>
     *
     * @throws \InvalidArgumentException if file is invalid
     */
    public function parseFile(UploadedFile $file): array
    {
        $this->validateFile($file);

        $extensionRaw = $file->getClientOriginalExtension();
        $extension = $extensionRaw ? strtolower($extensionRaw) : null;
        if (!$extension) {
            throw new \InvalidArgumentException('File extension cannot be determined');
        }

        return match ($extension) {
            'csv' => $this->parseCsvFile($file),
            'json' => $this->parseJsonFile($file),
            default => throw new \InvalidArgumentException(sprintf('Unsupported file format: %s. Only CSV and JSON are supported.', $extension)),
        };
    }

    /**
     * Validate import rows for PriceList.
     *
     * @param array<ImportRow> $rows
     *
     * @return array<int, array<string>> Map of row number to validation errors
     */
    public function validatePriceListRows(array $rows): array
    {
        $errors = [];

        foreach ($rows as $row) {
            $rowErrors = $this->validationService->validatePriceListRow($row);
            if (!empty($rowErrors)) {
                $errors[$row->rowNumber] = $rowErrors;
            }
        }

        return $errors;
    }

    /**
     * Validate import rows for Promotion.
     *
     * @param array<ImportRow> $rows
     *
     * @return array<int, array<string>> Map of row number to validation errors
     */
    public function validatePromotionRows(array $rows): array
    {
        $errors = [];

        foreach ($rows as $row) {
            $rowErrors = $this->validationService->validatePromotionRow($row);
            if (!empty($rowErrors)) {
                $errors[$row->rowNumber] = $rowErrors;
            }
        }

        return $errors;
    }

    /**
     * Check if import should be processed asynchronously.
     */
    public function shouldProcessAsync(int $rowCount): bool
    {
        return $rowCount > self::LARGE_FILE_THRESHOLD;
    }

    /**
     * @return array<ImportRow>
     */
    private function parseCsvFile(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getPathname(), 'r');

        if (false === $handle) {
            throw new \RuntimeException('Failed to open CSV file');
        }

        try {
            // Read header row
            $headers = fgetcsv($handle);
            if (false === $headers) {
                throw new \InvalidArgumentException('CSV file is empty or invalid');
            }

            $rowNumber = 1; // Start from 1 (header is row 0)

            while (($data = fgetcsv($handle)) !== false) {
                if (count($rows) >= self::MAX_ROWS) {
                    throw new \InvalidArgumentException(sprintf('Import file exceeds maximum of %d rows', self::MAX_ROWS));
                }

                ++$rowNumber;

                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }

                // Ensure headers and data have same count
                if (count($headers) !== count($data)) {
                    throw new \InvalidArgumentException(sprintf('CSV column count mismatch at row %d', $rowNumber));
                }

                // Combine headers with data - ensure proper types for array_combine
                /** @var array<string> $cleanHeaders */
                $cleanHeaders = array_map('strval', $headers);
                /** @var array<string> $cleanData */
                $cleanData = array_map(fn ($v) => false === $v ? '' : (string) $v, $data);

                $rowData = array_combine($cleanHeaders, $cleanData);
                if (!is_array($rowData) || empty($rowData)) {
                    throw new \InvalidArgumentException(sprintf('Invalid CSV format at row %d', $rowNumber));
                }

                $rows[] = new ImportRow($rowNumber, $rowData);
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * @return array<ImportRow>
     */
    private function parseJsonFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());
        if (false === $content) {
            throw new \RuntimeException('Failed to read JSON file');
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('JSON file must contain an array of objects');
        }

        if (count($data) > self::MAX_ROWS) {
            throw new \InvalidArgumentException(sprintf('Import file exceeds maximum of %d rows', self::MAX_ROWS));
        }

        $rows = [];
        foreach ($data as $index => $rowData) {
            if (!is_array($rowData)) {
                throw new \InvalidArgumentException(sprintf('Invalid JSON format at row %d', $index + 1));
            }

            $rows[] = new ImportRow($index + 1, $rowData);
        }

        return $rows;
    }

    private function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Invalid file upload');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(sprintf('File size exceeds maximum of %d MB', self::MAX_FILE_SIZE / 1024 / 1024));
        }

        $extensionRaw = $file->getClientOriginalExtension();
        $extension = $extensionRaw ? strtolower($extensionRaw) : null;

        if (!$extension || !in_array($extension, ['csv', 'json'], true)) {
            throw new \InvalidArgumentException('Only CSV and JSON files are supported');
        }
    }
}
