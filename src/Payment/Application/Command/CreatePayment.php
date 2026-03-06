<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CreatePayment
{
    public function __construct(
        public PaymentId $id,
        public TenantId $tenantId,
        public string $orderId,
        public Money $amount,
        public PaymentMethod $method,
        public PaymentGateway $gateway,
    ) {
    }
}
