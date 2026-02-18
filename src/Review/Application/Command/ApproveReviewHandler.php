<?php

declare(strict_types=1);

namespace App\Review\Application\Command;

use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ApproveReviewHandler
{
    public function __construct(
        private ProductReviewRepositoryInterface $reviewRepository,
    ) {
    }

    public function __invoke(ApproveReview $command): void
    {
        $review = $this->reviewRepository->findById($command->reviewId);

        if (null === $review) {
            throw new \DomainException('Review not found');
        }

        $review->approve();

        $this->reviewRepository->save($review);
    }
}
