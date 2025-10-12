<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;

final readonly class OrderStatusChanged
{
    public function __construct(
        public OrderId $orderId,
        public OrderStatus $oldStatus,
        public OrderStatus $newStatus
    ) {
    }
}
