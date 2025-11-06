<?php

declare(strict_types=1);

namespace App\Review\Application\Query;

use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetReviewByIdHandler
{
    public function __construct(
        private ProductReviewRepositoryInterface $reviewRepository
    ) {}

    public function __invoke(GetReviewById $query): ?ProductReview
    {
        return $this->reviewRepository->findById($query->reviewId);
    }
}
