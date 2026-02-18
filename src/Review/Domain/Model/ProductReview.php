<?php

declare(strict_types=1);

namespace App\Review\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Domain\Event\ReviewApproved;
use App\Review\Domain\Event\ReviewRejected;
use App\Review\Domain\Event\ReviewSubmitted;
use App\Review\Domain\ValueObject\Rating;
use App\Review\Domain\ValueObject\ReviewStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * ProductReview Aggregate Root
 * Represents a customer review for a product.
 *
 * Business Rules:
 * - Rating is required (1-5 stars)
 * - Review text (title and content) is optional
 * - Only customers can submit text reviews
 * - Anonymous users can only submit ratings
 * - Reviews must be approved before being public
 * - Title max 200 characters
 * - Content max 5000 characters
 */
final class ProductReview extends AggregateRoot
{
    private const MAX_TITLE_LENGTH = 200;
    private const MAX_CONTENT_LENGTH = 5000;

    private function __construct(
        private ReviewId $id,
        private ProductId $productId,
        private TenantId $tenantId,
        private ?string $customerId,
        private ?string $customerName,
        private Rating $rating,
        private ?string $title,
        private ?string $content,
        private ReviewStatus $status,
        private bool $isVerifiedPurchase,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->validateReview();
    }

    /**
     * Submit a new review.
     */
    public static function submit(
        ReviewId $id,
        ProductId $productId,
        TenantId $tenantId,
        ?string $customerId,
        ?string $customerName,
        Rating $rating,
        ?string $title = null,
        ?string $content = null,
        bool $isVerifiedPurchase = false,
    ): self {
        $review = new self(
            id: $id,
            productId: $productId,
            tenantId: $tenantId,
            customerId: $customerId,
            customerName: $customerName,
            rating: $rating,
            title: $title ? trim($title) : null,
            content: $content ? trim($content) : null,
            status: ReviewStatus::PENDING,
            isVerifiedPurchase: $isVerifiedPurchase,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $review->recordEvent(new ReviewSubmitted(
            $id,
            $productId,
            $customerId,
            $rating,
            $title,
            $content,
            new \DateTimeImmutable()
        ));

        return $review;
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstituteFromPersistence(
        ReviewId $id,
        ProductId $productId,
        TenantId $tenantId,
        ?string $customerId,
        ?string $customerName,
        Rating $rating,
        ?string $title,
        ?string $content,
        ReviewStatus $status,
        bool $isVerifiedPurchase,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            $productId,
            $tenantId,
            $customerId,
            $customerName,
            $rating,
            $title,
            $content,
            $status,
            $isVerifiedPurchase,
            $createdAt,
            $updatedAt
        );
    }

    /**
     * Approve the review.
     */
    public function approve(): void
    {
        if ($this->status->isApproved()) {
            throw new \DomainException('Review is already approved');
        }

        $this->status = ReviewStatus::APPROVED;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ReviewApproved($this->id, new \DateTimeImmutable()));
    }

    /**
     * Reject the review.
     */
    public function reject(string $reason): void
    {
        if ($this->status->isRejected()) {
            throw new \DomainException('Review is already rejected');
        }

        $this->status = ReviewStatus::REJECTED;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ReviewRejected($this->id, $reason, new \DateTimeImmutable()));
    }

    /**
     * Update review content (only if pending).
     */
    public function updateContent(string $title, string $content): void
    {
        if (!$this->status->isPending()) {
            throw new \DomainException('Can only update pending reviews');
        }

        $this->title = trim($title);
        $this->content = trim($content);
        $this->updatedAt = new \DateTimeImmutable();

        $this->validateReview();
    }

    // Getters
    public function id(): ReviewId
    {
        return $this->id;
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function customerId(): ?string
    {
        return $this->customerId;
    }

    public function customerName(): ?string
    {
        return $this->customerName;
    }

    public function rating(): Rating
    {
        return $this->rating;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function content(): ?string
    {
        return $this->content;
    }

    public function status(): ReviewStatus
    {
        return $this->status;
    }

    public function isVerifiedPurchase(): bool
    {
        return $this->isVerifiedPurchase;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function validateReview(): void
    {
        // Text reviews require customer
        if ((null !== $this->title || null !== $this->content) && null === $this->customerId) {
            throw new \DomainException('Only logged-in customers can submit text reviews');
        }

        // Validate title length
        if (null !== $this->title && mb_strlen($this->title) > self::MAX_TITLE_LENGTH) {
            throw new \DomainException(sprintf('Review title cannot exceed %d characters', self::MAX_TITLE_LENGTH));
        }

        // Validate content length
        if (null !== $this->content && mb_strlen($this->content) > self::MAX_CONTENT_LENGTH) {
            throw new \DomainException(sprintf('Review content cannot exceed %d characters', self::MAX_CONTENT_LENGTH));
        }
    }
}

/**
 * Value object for Review ID.
 */
final readonly class ReviewId
{
    private function __construct(
        private string $value,
    ) {
        if (empty($value)) {
            throw new \InvalidArgumentException('Review ID cannot be empty');
        }
    }

    public static function generate(): self
    {
        return new self(\Symfony\Component\Uid\Uuid::v4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
