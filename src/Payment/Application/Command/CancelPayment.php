<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CancelPayment
{
    public function __construct(
        public PaymentId $id,
        public TenantId $tenantId,
        public string $reason
    ) {
    }
}
