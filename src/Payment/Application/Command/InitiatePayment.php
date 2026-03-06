<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to initiate a payment by creating a Payment entity
 * and a Stripe PaymentIntent.
 */
final readonly class InitiatePayment
{
    public function __construct(
        public PaymentId $paymentId,
        public TenantId $tenantId,
        public string $orderId,
        public Money $amount,
        public string $customerEmail,
        public PaymentMethod $method,
        public PaymentGateway $gateway,
    ) {
    }
}
