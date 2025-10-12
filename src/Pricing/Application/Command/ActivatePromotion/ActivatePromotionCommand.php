<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\ActivatePromotion;

use App\Pricing\Domain\ValueObject\PromotionId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class ActivatePromotionCommand
{
    public function __construct(
        public PromotionId $promotionId,
        public TenantId $tenantId
    ) {
    }
}
