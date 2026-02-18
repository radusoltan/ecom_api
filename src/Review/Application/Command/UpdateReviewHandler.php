<?php

declare(strict_types=1);

namespace App\Review\Application\Command;

use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateReviewHandler
{
    public function __construct(
        private ProductReviewRepositoryInterface $reviewRepository,
    ) {
    }

    public function __invoke(UpdateReview $command): void
    {
        $review = $this->reviewRepository->findById($command->reviewId);

        if (null === $review) {
            throw new \DomainException('Review not found');
        }

        $review->updateContent($command->title, $command->content);

        $this->reviewRepository->save($review);
    }
}
