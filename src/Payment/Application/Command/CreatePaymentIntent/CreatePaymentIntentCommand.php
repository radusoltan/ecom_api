<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\CreatePaymentIntent;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to create a payment intent at the gateway.
 *
 * This is the first step in the payment flow:
 * 1. Create payment intent (this command) - reserves funds at gateway
 * 2. Confirm payment intent - completes authorization (may require 3DS)
 * 3. Capture payment - transfers funds (on order shipment)
 *
 * Idempotency: Uses idempotency key to prevent duplicate payment intents.
 * If a payment intent already exists with the same key, returns existing intent.
 */
final readonly class CreatePaymentIntentCommand
{
    public function __construct(
        public TenantId $tenantId,
        public string $orderId,
        public int $amountInCents,
        public string $currency,
        public string $paymentMethod,  // 'stripe', 'paypal', etc.
        public string $idempotencyKey,
        public ?string $customerId = null,
        public ?string $customerEmail = null
    ) {
    }
}
