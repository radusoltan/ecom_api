<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\UpdatePromotion;

use App\Pricing\Domain\ValueObject\PromotionId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class UpdatePromotionCommand
{
    public function __construct(
        public PromotionId $promotionId,
        public TenantId $tenantId,
        public string $name,
        public string $discountType,
        public float $discountValue,
        public int $priority,
        public array $conditions,
        public ?string $validFrom,
        public ?string $validTo,
    ) {
    }
}
