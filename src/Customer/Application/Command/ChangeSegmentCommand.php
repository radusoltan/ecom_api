<?php

declare(strict_types=1);

namespace App\Customer\Application\Command;

final readonly class ChangeSegmentCommand
{
    public function __construct(
        public string $customerId,
        public string $tenantId,
        public string $newSegment
    ) {
    }
}
