<?php

declare(strict_types=1);

namespace App\Internationalization\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Get Translation Statistics Query.
 *
 * Retrieves comprehensive translation coverage statistics:
 * - Coverage per domain per locale
 * - Overall statistics
 * - Heatmap data for visualization
 */
final readonly class GetTranslationStats
{
    public function __construct(
        public TenantId $tenantId,
    ) {
    }
}
