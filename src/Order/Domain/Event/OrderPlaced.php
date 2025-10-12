<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class OrderPlaced
{
    public function __construct(
        public OrderId $orderId,
        public TenantId $tenantId,
        public string $customerEmail,
        public Money $total
    ) {
    }
}
