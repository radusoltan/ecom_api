<?php

declare(strict_types=1);

namespace App\Returns\Application\Command;

/**
 * Command: InspectReturnRequest.
 *
 * Warehouse inspects a returned item and determines resellability.
 */
final readonly class InspectReturnRequest
{
    public function __construct(
        public string $returnRequestId,
        public bool $isResellable,
        public string $inspectionNotes
    ) {
    }
}
