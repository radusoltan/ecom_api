<?php

declare(strict_types=1);

namespace App\Review\Application\Query;

use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetProductReviewsHandler
{
    public function __construct(
        private ProductReviewRepositoryInterface $reviewRepository
    ) {}

    /**
     * @return array<ReviewDTO>
     */
    public function __invoke(GetProductReviews $query): array
    {
        $reviews = $query->onlyApproved
            ? $this->reviewRepository->findApprovedByProductId($query->productId, $query->tenantId)
            : $this->reviewRepository->findByProductId($query->productId, $query->tenantId);

        return array_map(
            fn($review) => new ReviewDTO(
                id: $review->id()->toString(),
                productId: $review->productId()->toString(),
                customerId: $review->customerId(),
                customerName: $review->customerName() ?? 'Anonymous',
                rating: $review->rating()->value(),
                title: $review->title(),
                content: $review->content(),
                status: $review->status()->value,
                isVerifiedPurchase: $review->isVerifiedPurchase(),
                createdAt: $review->createdAt()
            ),
            $reviews
        );
    }
}

/**
 * DTO for Review
 */
final readonly class ReviewDTO
{
    public function __construct(
        public string $id,
        public string $productId,
        public ?string $customerId,
        public string $customerName,
        public int $rating,
        public ?string $title,
        public ?string $content,
        public string $status,
        public bool $isVerifiedPurchase,
        public \DateTimeImmutable $createdAt
    ) {}
}
