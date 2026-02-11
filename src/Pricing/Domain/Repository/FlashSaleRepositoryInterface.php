<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Shared\Domain\ValueObject\TenantId;

interface FlashSaleRepositoryInterface
{
    public function save(FlashSale $flashSale): void;

    public function findById(FlashSaleId $id, TenantId $tenantId): ?FlashSale;

    /**
     * Find currently active flash sales for a tenant.
     *
     * @return FlashSale[]
     */
    public function findActiveByTenant(TenantId $tenantId): array;

    /**
     * Find upcoming scheduled flash sales for a tenant.
     *
     * @return FlashSale[]
     */
    public function findUpcoming(TenantId $tenantId): array;

    /**
     * Find active flash sale for a specific product.
     */
    public function findActiveByProductId(ProductId $productId, TenantId $tenantId): ?FlashSale;

    /**
     * Find all flash sales for a tenant.
     *
     * @return FlashSale[]
     */
    public function findAll(TenantId $tenantId): array;

    /**
     * Find flash sales that should be activated at the given time.
     *
     * @return FlashSale[]
     */
    public function findScheduledToActivateAt(\DateTimeImmutable $dateTime): array;

    /**
     * Find active flash sales that should end at the given time.
     *
     * @return FlashSale[]
     */
    public function findActiveToEndAt(\DateTimeImmutable $dateTime): array;

    public function delete(FlashSale $flashSale): void;
}
