<?php

declare(strict_types=1);

namespace App\Returns\Application\Command;

/**
 * Command: RejectReturnRequest
 *
 * Reject a return request with a reason.
 */
final readonly class RejectReturnRequest
{
    public function __construct(
        public string $returnRequestId,
        public string $rejectionReason
    ) {
    }
}
