<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\ExportPromotions;

use App\Pricing\Application\DTO\ExportFilter;

/**
 * Query to export Promotions to CSV or JSON format.
 *
 * Supports filtering by date range, status, and tenant
 */
final readonly class ExportPromotionsQuery
{
    public const FORMAT_CSV = 'csv';
    public const FORMAT_JSON = 'json';

    public function __construct(
        public ExportFilter $filter,
        public string $format = self::FORMAT_CSV,
    ) {
        $this->validateFormat();
    }

    private function validateFormat(): void
    {
        if (!in_array($this->format, [self::FORMAT_CSV, self::FORMAT_JSON], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid export format: %s. Supported formats: csv, json', $this->format));
        }
    }

    public function isCsvFormat(): bool
    {
        return self::FORMAT_CSV === $this->format;
    }

    public function isJsonFormat(): bool
    {
        return self::FORMAT_JSON === $this->format;
    }
}
