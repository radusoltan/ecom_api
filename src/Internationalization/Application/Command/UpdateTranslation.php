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
    public function __construct(
        public int $id,
        public string $tenantId,
        public string $value,
    ) {
    }
}
