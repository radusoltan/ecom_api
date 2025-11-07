<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Get Missing Translations Query.
 *
 * Retrieves translations that exist in source locale but missing in target locale.
 *
 * Filters:
 * - targetLocale (required): Locale to check for missing translations
 * - sourceLocale (optional): Reference locale (default: 'en')
 * - domain (optional): Filter by specific domain
 */
final readonly class GetMissingTranslations
{
    public function __construct(
        public TenantId $tenantId,
        public string $targetLocale,
        public ?string $sourceLocale = null,
        public ?string $domain = null,
    ) {
    }
}
