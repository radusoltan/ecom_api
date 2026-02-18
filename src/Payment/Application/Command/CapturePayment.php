<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\ValueObject\PaymentId;

/**
 * Command to capture an authorized payment.
 *
 * This is the third step in the payment flow:
 * 1. Create payment intent - reserves funds at gateway
 * 2. Confirm payment intent - completes authorization
 * 3. Capture payment (this command) - transfers funds to merchant
 *
 * Supports partial capture - if amountInCents is provided and less than
 * authorized amount, only that amount will be captured.
 */
final readonly class CapturePayment
{
    public function __construct(
        public PaymentId $id,
        public ?int $amountInCents = null,  // null = capture full authorized amount
    ) {
    }
}
