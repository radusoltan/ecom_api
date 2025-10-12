<?php

declare(strict_types=1);

namespace App\Returns\Application\Command;

/**
 * Command: ApproveReturnRequest
 *
 * Customer service approves a return request.
 */
final readonly class ApproveReturnRequest
{
    public function __construct(
        public string $returnRequestId
    ) {
    }
}
