<?php

declare(strict_types=1);

namespace App\Review\Application\Query;

use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetProductRatingHandler
{
    public function __construct(
        private ProductReviewRepositoryInterface $reviewRepository,
    ) {
    }

    public function __invoke(GetProductRating $query): RatingDTO
    {
        $averageRating = $this->reviewRepository->getAverageRating($query->productId, $query->tenantId);
        $reviewCount = $this->reviewRepository->getReviewCount($query->productId, $query->tenantId);

        return new RatingDTO(
            productId: $query->productId->toString(),
            averageRating: $averageRating ?? 0.0,
            reviewCount: $reviewCount
        );
    }
}

/**
 * DTO for Product Rating.
 */
final readonly class RatingDTO
{
    public function __construct(
        public string $productId,
        public float $averageRating,
        public int $reviewCount,
    ) {
    }
}
