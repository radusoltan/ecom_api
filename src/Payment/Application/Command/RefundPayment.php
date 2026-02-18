<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Model\PaymentId;

/**
 * Command to refund a captured payment.
 *
 * Refunds return money to the customer's original payment method.
 * Supports partial refunds - can refund less than the captured amount.
 *
 * Use Cases:
 * - Order returned by customer
 * - Product damaged/defective
 * - Service not delivered
 * - Customer requested refund
 * - Partial refund for damaged items
 *
 * Business Rules:
 * - Can only refund captured payments
 * - Refund amount cannot exceed (captured amount - already refunded amount)
 * - Supports multiple partial refunds up to total captured amount
 */
final readonly class RefundPayment
{
    public function __construct(
        public PaymentId $id,
        public int $refundAmountInCents,
        public string $reason,
    ) {
    }
}
