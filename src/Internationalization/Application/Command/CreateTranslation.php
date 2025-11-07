<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Command;

/**
 * CreateTranslation Command (DTO).
 *
 * Command to create a new translation entry.
 */
final readonly class CreateTranslation
{
    public function __construct(
        public string $tenantId,
        public string $locale,
        public string $domain,
        public string $key,
        public string $value,
    ) {
    }
}
