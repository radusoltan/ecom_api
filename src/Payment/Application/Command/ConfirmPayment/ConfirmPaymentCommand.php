<?php

declare(strict_types=1);

namespace App\Payment\Application\Command\ConfirmPayment;

use App\Payment\Domain\Model\PaymentId;

/**
 * Command to confirm a payment intent.
 *
 * This is the second step in the payment flow:
 * 1. Create payment intent - reserves funds at gateway
 * 2. Confirm payment intent (this command) - completes authorization
 * 3. Capture payment - transfers funds (on order shipment)
 *
 * Confirmation may require additional customer action (3D Secure, SCA).
 * The payment method ID comes from the frontend payment form (Stripe Elements, etc.)
 */
final readonly class ConfirmPaymentCommand
{
    public function __construct(
        public PaymentId $paymentId,
        public string $paymentMethodId,
    ) {
    }
}
