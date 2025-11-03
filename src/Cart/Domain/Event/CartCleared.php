<?php

declare(strict_types=1);

namespace App\Cart\Domain\Event;

use App\Cart\Domain\Model\CartId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CartCleared
{
    public function __construct(
        public CartId $cartId,
        public TenantId $tenantId
    ) {
    }
}