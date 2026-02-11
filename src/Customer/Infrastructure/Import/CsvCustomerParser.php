<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Import;

/**
 * CSV parser for customer import files.
 *
 * Handles CSV parsing with validation of required columns.
 */
final class CsvCustomerParser
{
    private const REQUIRED_COLUMNS = ['email', 'first_name', 'last_name'];

    /**
     * Parse CSV content into array of rows.
     *
     * @return array<array<string, string>>
     *
     * @throws \InvalidArgumentException if CSV is invalid or missing required columns
     */
    public function parse(string $content): array
    {
        $lines = str_getcsv($content, "\n");
        // @phpstan-ignore count.alwaysGreaterThan (PHPStan doesn't understand str_getcsv can return empty array from empty string)
        if (0 === count($lines)) {
            throw new \InvalidArgumentException('CSV file is empty');
        }

        // Parse header
        $firstLine = array_shift($lines);
        if (null === $firstLine) {
            throw new \InvalidArgumentException('CSV file has no header');
        }

        $headerRaw = str_getcsv($firstLine);

        // Normalize header (trim and lowercase), filter nulls
        /** @var array<string> $header */
        $header = array_values(array_map(
            fn ($col) => strtolower(trim((string) $col)),
            array_filter($headerRaw, fn ($val) => null !== $val)
        ));

        // Validate required columns exist
        $this->validateRequiredColumns($header);

        // Parse rows
        $rows = [];
        foreach ($lines as $line) {
            if (null === $line || '' === trim($line)) {
                continue; // Skip empty lines
            }

            $valuesRaw = str_getcsv($line);

            // Convert to strings and pad if needed
            $values = array_map(fn ($val) => (string) ($val ?? ''), $valuesRaw);
            $values = array_pad($values, count($header), '');

            $row = array_combine($header, $values);
            if ($row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Validate that all required columns are present in the header.
     *
     * @param array<string> $header
     *
     * @throws \InvalidArgumentException if required columns are missing
     */
    private function validateRequiredColumns(array $header): void
    {
        $missingColumns = array_diff(self::REQUIRED_COLUMNS, $header);
        if (!empty($missingColumns)) {
            throw new \InvalidArgumentException('CSV is missing required columns: '.implode(', ', $missingColumns));
        }
    }

    /**
     * Get list of required column names.
     *
     * @return array<string>
     */
    public function getRequiredColumns(): array
    {
        return self::REQUIRED_COLUMNS;
    }
}
