<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * OrderPaid Domain Event.
 *
 * Emitted when payment for an order has been successfully captured.
 * This event triggers:
 * - Order Saga: Stock allocation, fulfillment preparation
 * - Inventory: Allocate reserved stock
 * - Fulfillment: Start fulfillment workflow
 * - Customer: Update loyalty points
 *
 * Business Rules:
 * - Order status should be "processing" when this event is emitted
 * - Payment must be captured (not just authorized)
 * - Triggers async fulfillment workflow
 */
final readonly class OrderPaid
{
    public function __construct(
        public string $orderId,
        public TenantId $tenantId,
        public string $paymentId,
        public int $paidAmountInCents,
        public \DateTimeImmutable $occurredOn
    ) {
    }
}
