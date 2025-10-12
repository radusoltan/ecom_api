<?php

declare(strict_types=1);

namespace App\Returns\Application\Command;

/**
 * Command: CompleteReturnRequest
 *
 * Complete the return process by issuing a refund.
 */
final readonly class CompleteReturnRequest
{
    public function __construct(
        public string $returnRequestId,
        public int $refundAmount,
        public string $refundCurrency
    ) {
    }
}
