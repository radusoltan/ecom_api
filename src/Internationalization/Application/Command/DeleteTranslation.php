<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Command;

/**
 * DeleteTranslation Command (DTO).
 *
 * Command to delete a translation entry.
 */
final readonly class DeleteTranslation
{
    public function __construct(
        public string $id,
        public string $tenantId,
    ) {
    }
}
