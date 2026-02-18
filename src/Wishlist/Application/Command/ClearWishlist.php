<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class ClearWishlist
{
    public function __construct(
        public string $customerId,
        public TenantId $tenantId,
    ) {
    }
}
