<?php

declare(strict_types=1);

namespace App\Review\Application\Command;

use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SubmitReviewHandler
{
    public function __construct(
        private ProductReviewRepositoryInterface $reviewRepository,
    ) {
    }

    public function __invoke(SubmitReview $command): void
    {
        $review = ProductReview::submit(
            id: $command->reviewId,
            productId: $command->productId,
            tenantId: $command->tenantId,
            customerId: $command->customerId,
            customerName: $command->customerName,
            rating: $command->rating,
            title: $command->title,
            content: $command->content,
            isVerifiedPurchase: $command->isVerifiedPurchase
        );

        $this->reviewRepository->save($review);
    }
}
