<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command;

use App\Privacy\Domain\ValueObject\ConsentId;

final readonly class WithdrawConsentCommand
{
    public function __construct(
        public ConsentId $consentId,
    ) {
    }
}
