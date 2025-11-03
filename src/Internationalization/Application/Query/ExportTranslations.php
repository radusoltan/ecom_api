<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Export Translations Query
 *
 * Triggers export of translations with optional filters.
 *
 * Filters:
 * - domain: Filter by specific domain (messages, validators, etc.)
 * - locale: Filter by specific locale (en, fr, de)
 * - createdAfter: Filter by creation date (ISO 8601 string)
 * - createdBefore: Filter by creation date (ISO 8601 string)
 * - updatedAfter: Filter by update date (ISO 8601 string)
 *
 * Format:
 * - 'csv': Comma-separated values
 * - 'json': JSON array
 */
final readonly class ExportTranslations
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public TenantId $tenantId,
        public string $format, // 'csv' or 'json'
        public array $filters = [],
    ) {}
}
