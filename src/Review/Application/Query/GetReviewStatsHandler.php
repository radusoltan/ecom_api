<?php

declare(strict_types=1);

namespace App\Review\Application\Query;

use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetReviewStatsHandler
{
    public function __construct(
        private ProductReviewRepositoryInterface $reviewRepository
    ) {
    }

    public function __invoke(GetReviewStats $query): array
    {
        $averageRating = $this->reviewRepository->getAverageRating(
            $query->productId,
            $query->tenantId
        );

        $totalReviews = $this->reviewRepository->getReviewCount(
            $query->productId,
            $query->tenantId
        );

        return [
            'averageRating' => $averageRating,
            'totalReviews' => $totalReviews,
            'productId' => $query->productId->toString(),
        ];
    }
}
