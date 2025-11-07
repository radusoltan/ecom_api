<?php

declare(strict_types=1);

namespace App\Internationalization\Infrastructure\Parser;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * CSV Translation Parser.
 *
 * Parses CSV files to translation array format.
 * Expected CSV format: locale,domain,key,value
 *
 * Features:
 * - Header row detection (auto-skip if contains 'locale')
 * - UTF-8 encoding support
 * - Validation and error reporting
 * - Empty line skipping
 */
final class CSVTranslationParser
{
    private const EXPECTED_COLUMNS = 4;
    private const COLUMN_LOCALE = 0;
    private const COLUMN_DOMAIN = 1;
    private const COLUMN_KEY = 2;
    private const COLUMN_VALUE = 3;

    /**
     * Parse CSV file to array format.
     *
     * @return array<array{locale: string, domain: string, key: string, value: string}>
     *
     * @throws \RuntimeException If file cannot be read or parsed
     */
    public function parse(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Invalid file upload');
        }

        $filePath = $file->getRealPath();
        if (false === $filePath) {
            throw new \RuntimeException('Could not read uploaded file');
        }

        $handle = fopen($filePath, 'r');
        if (false === $handle) {
            throw new \RuntimeException('Could not open file for reading');
        }

        $translations = [];
        $lineNumber = 0;
        $isFirstLine = true;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                ++$lineNumber;

                // Skip empty lines
                if (0 === count($row) || (1 === count($row) && '' === trim($row[0] ?? ''))) {
                    continue;
                }

                // Auto-detect and skip header row
                if ($isFirstLine && $this->isHeaderRow($row)) {
                    $isFirstLine = false;

                    continue;
                }
                $isFirstLine = false;

                // Validate column count
                if (self::EXPECTED_COLUMNS !== count($row)) {
                    throw new \RuntimeException(sprintf('Line %d: Expected %d columns, got %d. Format: locale,domain,key,value', $lineNumber, self::EXPECTED_COLUMNS, count($row)));
                }

                $translations[] = [
                    'locale' => trim($row[self::COLUMN_LOCALE]),
                    'domain' => trim($row[self::COLUMN_DOMAIN]),
                    'key' => trim($row[self::COLUMN_KEY]),
                    'value' => trim($row[self::COLUMN_VALUE]),
                ];
            }
        } finally {
            fclose($handle);
        }

        if (empty($translations)) {
            throw new \RuntimeException('No translations found in CSV file');
        }

        return $translations;
    }

    /**
     * Parse CSV content from string.
     *
     * @param string $content CSV content
     *
     * @return array<array{locale: string, domain: string, key: string, value: string}>
     */
    public function parseString(string $content): array
    {
        $lines = explode("\n", $content);
        $translations = [];
        $isFirstLine = true;

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            // Skip empty lines
            if ('' === $line) {
                continue;
            }

            $row = str_getcsv($line);

            // Auto-detect and skip header row
            if ($isFirstLine && $this->isHeaderRow($row)) {
                $isFirstLine = false;

                continue;
            }
            $isFirstLine = false;

            // Validate column count
            if (self::EXPECTED_COLUMNS !== count($row)) {
                throw new \RuntimeException(sprintf('Line %d: Expected %d columns, got %d. Format: locale,domain,key,value', $lineNumber + 1, self::EXPECTED_COLUMNS, count($row)));
            }

            $translations[] = [
                'locale' => trim($row[self::COLUMN_LOCALE] ?? ''),
                'domain' => trim($row[self::COLUMN_DOMAIN] ?? ''),
                'key' => trim($row[self::COLUMN_KEY] ?? ''),
                'value' => trim($row[self::COLUMN_VALUE] ?? ''),
            ];
        }

        if (0 === count($translations)) {
            throw new \RuntimeException('No translations found in CSV content');
        }

        return $translations;
    }

    /**
     * Generate CSV content from translations array.
     *
     * @param array<array{locale: string, domain: string, key: string, value: string}> $translations
     */
    public function generateCSV(array $translations, bool $includeHeader = true): string
    {
        $output = '';

        if ($includeHeader) {
            $output .= "locale,domain,key,value\n";
        }

        foreach ($translations as $translation) {
            $row = [
                $translation['locale'],
                $translation['domain'],
                $translation['key'],
                $translation['value'],
            ];

            // Escape values that contain commas or quotes
            $escapedRow = array_map(function ($value) {
                if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                    return '"'.str_replace('"', '""', $value).'"';
                }

                return $value;
            }, $row);

            $output .= implode(',', $escapedRow)."\n";
        }

        return $output;
    }

    /**
     * Check if row is a header row.
     *
     * @param array<int, string|null> $row
     */
    private function isHeaderRow(array $row): bool
    {
        // Check if first column contains 'locale' (case-insensitive)
        return isset($row[0]) && 'locale' === strtolower(trim($row[0] ?? ''));
    }
}
