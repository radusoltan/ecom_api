<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Command;

/**
 * UpdateTranslation Command (DTO).
 *
 * Command to update an existing translation entry.
 */
final readonly class UpdateTranslation
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $value,
        public array $parameters = [],
    ) {
    }
}
