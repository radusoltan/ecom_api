<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class OrderStatusChanged
{
    public function __construct(
        public OrderId $orderId,
        public TenantId $tenantId,
        public OrderStatus $oldStatus,
        public OrderStatus $newStatus
    ) {
    }
}
