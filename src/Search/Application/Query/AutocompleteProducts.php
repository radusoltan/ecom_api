<?php

declare(strict_types=1);

namespace App\Search\Application\Query;

/**
 * AutocompleteProducts Query DTO.
 *
 * Represents a request for product autocomplete suggestions.
 */
final readonly class AutocompleteProducts
{
    public function __construct(
        public string $tenantId,
        public string $query,
        public string $locale = 'en',
        public int $limit = 5,
    ) {
        if ($this->limit < 1 || $this->limit > 20) {
            throw new \InvalidArgumentException('Autocomplete limit must be between 1 and 20');
        }
    }
}
