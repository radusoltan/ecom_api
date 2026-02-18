<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command;

use App\Privacy\Domain\ValueObject\DataSubjectRequestId;

final readonly class RejectDataSubjectRequestCommand
{
    public function __construct(
        public DataSubjectRequestId $requestId,
        public string $rejectionReason,
    ) {
    }
}
