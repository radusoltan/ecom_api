<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\CreateFlashSale;

use App\Pricing\Domain\Model\FlashSaleId;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CreateFlashSaleCommand
{
    /**
     * @param array<string> $productIds
     */
    public function __construct(
        public FlashSaleId $flashSaleId,
        public TenantId $tenantId,
        public string $name,
        public array $productIds,
        public string $discountType,
        public float $discountValue,
        public string $startTime,
        public string $endTime
    ) {
    }
}
