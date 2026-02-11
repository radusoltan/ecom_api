<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Export;

/**
 * CSV writer for customer export files.
 *
 * Handles CSV generation with proper escaping and character encoding.
 */
final class CsvCustomerWriter
{
    /**
     * Write customer data to CSV format.
     *
     * @param array<string>        $header
     * @param array<array<string>> $rows
     *
     * @return string CSV content
     *
     * @throws \RuntimeException if file operations fail
     */
    public function write(array $header, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Failed to create temporary file for CSV export');
        }

        try {
            // Write header
            fputcsv($handle, $header);

            // Write data rows using streaming for memory efficiency
            foreach ($this->streamRows($rows) as $row) {
                fputcsv($handle, $row);
            }

            rewind($handle);
            $csv = stream_get_contents($handle);

            if (false === $csv) {
                throw new \RuntimeException('Failed to read CSV content');
            }

            return $csv;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Stream rows for memory-efficient processing.
     *
     * @param array<array<string>> $rows
     *
     * @return \Generator<array<string>>
     */
    private function streamRows(array $rows): \Generator
    {
        foreach ($rows as $row) {
            yield $this->escapeRow($row);
        }
    }

    /**
     * Escape special characters in row values.
     *
     * @param array<string> $row
     *
     * @return array<string>
     */
    private function escapeRow(array $row): array
    {
        return array_map(
            fn (string $value) => $this->escapeValue($value),
            $row
        );
    }

    /**
     * Escape special characters in a single value.
     *
     * Handles:
     * - Double quotes
     * - Line breaks
     * - Special characters
     */
    private function escapeValue(string $value): string
    {
        // Remove control characters except tabs and newlines
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
        if (null === $cleaned) {
            return '';
        }

        // Ensure UTF-8 encoding
        if (!mb_check_encoding($cleaned, 'UTF-8')) {
            $converted = mb_convert_encoding($cleaned, 'UTF-8', 'auto');

            return is_string($converted) ? $converted : '';
        }

        return $cleaned;
    }

    /**
     * Get CSV template with example data.
     *
     * @return string CSV template content
     */
    public function getTemplate(): string
    {
        return $this->write(
            header: ['email', 'first_name', 'last_name', 'phone_number', 'segment', 'status'],
            rows: [
                ['john.doe@example.com', 'John', 'Doe', '+40723456789', 'regular', 'active'],
                ['jane.smith@example.com', 'Jane', 'Smith', '+40723456790', 'vip', 'active'],
            ]
        );
    }
}
