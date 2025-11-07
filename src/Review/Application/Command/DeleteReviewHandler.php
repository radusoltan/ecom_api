<?php

declare(strict_types=1);

namespace App\Review\Application\Command;

use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteReviewHandler
{
    public function __construct(
        private ProductReviewRepositoryInterface $reviewRepository
    ) {
    }

    public function __invoke(DeleteReview $command): void
    {
        $review = $this->reviewRepository->findById($command->reviewId);

        if (null === $review) {
            throw new \DomainException('Review not found');
        }

        // Business rule: Only the review author can delete their own review
        if (null !== $command->customerId && $review->customerId() !== $command->customerId) {
            throw new \DomainException('You can only delete your own reviews');
        }

        $this->reviewRepository->delete($review);
    }
}
