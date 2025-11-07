<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Query;

/**
 * GetTranslations Query (DTO).
 *
 * Query to retrieve translations with optional filters.
 */
final readonly class GetTranslations
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public string $tenantId,
        public array $filters = [],
        public int $page = 1,
        public int $perPage = 50,
    ) {
    }
}
