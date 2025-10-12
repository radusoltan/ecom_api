<?php

declare(strict_types=1);

namespace App\Order\Application\Command;

final readonly class UpdateOrderStatusCommand
{
    public function __construct(
        public string $orderId,
        public string $tenantId,
        public string $newStatus // 'processing', 'shipped', 'delivered'
    ) {
    }
}
