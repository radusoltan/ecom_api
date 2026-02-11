<?php

declare(strict_types=1);

namespace App\Customer\Application\Message;

use App\Customer\Domain\ValueObject\DeletionRequestId;

/**
 * Execute Deletion Message.
 *
 * Async message to execute account deletion and anonymization.
 */
final readonly class ExecuteDeletionMessage
{
    public function __construct(
        public DeletionRequestId $requestId
    ) {
    }
}
