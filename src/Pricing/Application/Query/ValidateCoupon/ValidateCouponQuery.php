<?php

declare(strict_types=1);

namespace App\Pricing\Application\Query\ValidateCoupon;

use App\Pricing\Domain\ValueObject\CouponCode;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class ValidateCouponQuery
{
    public function __construct(
        public CouponCode $couponCode,
        public Money $cartTotal,
        public TenantId $tenantId,
    ) {
    }
}
