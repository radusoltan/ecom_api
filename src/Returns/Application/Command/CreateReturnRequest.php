<?php

declare(strict_types=1);

namespace App\Returns\Application\Command;

/**
 * Command: CreateReturnRequest.
 *
 * Customer creates a return request (RMA) for a delivered order.
 */
final readonly class CreateReturnRequest
{
    public function __construct(
        public string $returnRequestId,
        public string $tenantId,
        public string $orderId,
        public string $reason
    ) {
    }
}
