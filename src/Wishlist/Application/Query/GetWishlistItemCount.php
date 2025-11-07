<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;

final readonly class GetWishlistItemCount
{
    public function __construct(
        public string $customerId,
        public TenantId $tenantId
    ) {
    }
}
