<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\Money;

/**
 * Command to capture an authorized payment.
 *
 * This is the third step in the payment flow:
 * 1. Create payment intent - reserves funds at gateway
 * 2. Confirm payment intent - completes authorization
 * 3. Capture payment (this command) - transfers funds to merchant
 *
 * Supports partial capture - if amount is provided and less than
 * authorized amount, only that amount will be captured.
 */
final readonly class CapturePayment
{
    public function __construct(
        public PaymentId $id,
        public ?Money $amount = null,  // null = capture full authorized amount
    ) {
    }
}
