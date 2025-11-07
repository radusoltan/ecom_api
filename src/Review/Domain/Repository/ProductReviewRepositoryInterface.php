<?php

declare(strict_types=1);

namespace App\Review\Domain\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Shared\Domain\ValueObject\TenantId;

interface ProductReviewRepositoryInterface
{
    public function save(ProductReview $review): void;

    public function findById(ReviewId $id): ?ProductReview;

    /**
     * @return ProductReview[]
     */
    public function findByProductId(ProductId $productId, TenantId $tenantId): array;

    /**
     * @return ProductReview[]
     */
    public function findApprovedByProductId(ProductId $productId, TenantId $tenantId): array;

    public function delete(ProductReview $review): void;

    /**
     * Get average rating for a product.
     */
    public function getAverageRating(ProductId $productId, TenantId $tenantId): ?float;

    /**
     * Get review count for a product.
     */
    public function getReviewCount(ProductId $productId, TenantId $tenantId): int;
}
