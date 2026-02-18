<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * OrderDelivered Domain Event.
 *
 * Emitted when an order has been successfully delivered to the customer.
 * This event triggers:
 * - Customer: Delivery confirmation email
 * - Order Saga: Complete order lifecycle
 * - Analytics: Update delivery metrics
 * - Customer: Update delivery history
 *
 * Business Rules:
 * - Order status should be "shipped" before delivery
 * - Delivery date must be after shipment date
 * - Triggers delivery confirmation notification
 */
final readonly class OrderDelivered
{
    public function __construct(
        public OrderId $orderId,
        public TenantId $tenantId,
        public \DateTimeImmutable $deliveryDate,
        public string $deliveryMethod,
        public string $customerEmail,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
