<?php

declare(strict_types=1);

namespace App\Payment\Domain\Event;

use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class PaymentAuthorized
{
    public function __construct(
        public PaymentId $paymentId,
        public TenantId $tenantId,
        public string $gatewayTransactionId,
    ) {
    }
}
