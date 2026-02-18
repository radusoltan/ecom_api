<?php

declare(strict_types=1);

namespace App\Payment\Application\Command;

use App\Payment\Domain\Model\PaymentId;

/**
 * Command to cancel/void an authorized payment.
 *
 * Cancellation releases reserved funds back to the customer.
 * Can only cancel payments that have NOT been captured yet.
 *
 * Use Cases:
 * - Order cancelled before shipment
 * - Customer requested cancellation
 * - Inventory unavailable
 * - Fraud prevention
 */
final readonly class CancelPayment
{
    public function __construct(
        public PaymentId $id,
        public string $reason,
    ) {
    }
}
