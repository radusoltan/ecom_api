<?php

declare(strict_types=1);

namespace App\Returns\Application\Command;

/**
 * Command: MarkReturnAsReceived.
 *
 * Warehouse marks a returned item as received.
 */
final readonly class MarkReturnAsReceived
{
    public function __construct(
        public string $returnRequestId,
        public string $warehouseId,
    ) {
    }
}
