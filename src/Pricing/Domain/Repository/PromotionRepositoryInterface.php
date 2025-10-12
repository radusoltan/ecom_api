<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Repository;

use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Shared\Domain\ValueObject\TenantId;

interface PromotionRepositoryInterface
{
    public function save(Promotion $promotion): void;

    public function findById(PromotionId $id, TenantId $tenantId): ?Promotion;

    public function findByCouponCode(CouponCode $couponCode, TenantId $tenantId): ?Promotion;

    /**
     * Find active promotions ordered by priority (highest first).
     *
     * @return Promotion[]
     */
    public function findActivePromotions(TenantId $tenantId): array;

    /**
     * Find all promotions for tenant.
     *
     * @return Promotion[]
     */
    public function findAll(TenantId $tenantId): array;

    public function delete(Promotion $promotion): void;
}
