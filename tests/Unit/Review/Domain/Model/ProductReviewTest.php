<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Domain\Event\ReviewApproved;
use App\Review\Domain\Event\ReviewRejected;
use App\Review\Domain\Event\ReviewSubmitted;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\ValueObject\Rating;
use App\Review\Domain\ValueObject\ReviewStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductReview::class)]
final class ProductReviewTest extends TestCase
{
    private ReviewId $reviewId;
    private ProductId $productId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        // ReviewId is defined in ProductReview.php, force autoload
        class_exists(ProductReview::class);

        $this->reviewId = ReviewId::generate();
        $this->productId = ProductId::generate();
        $this->tenantId = TenantId::generate();
    }

    // --- submit() ---

    #[Test]
    public function submitCreatesReviewWithCorrectState(): void
    {
        $review = ProductReview::submit(
            id: $this->reviewId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            customerId: null,
            customerName: null,
            rating: Rating::fromInt(4),
        );

        self::assertTrue($review->id()->equals($this->reviewId));
        self::assertTrue($review->productId()->equals($this->productId));
        self::assertTrue($review->tenantId()->equals($this->tenantId));
        self::assertSame(4, $review->rating()->value());
        self::assertTrue($review->status()->isPending());
        self::assertNull($review->customerId());
        self::assertNull($review->customerName());
        self::assertNull($review->title());
        self::assertNull($review->content());
        self::assertFalse($review->isVerifiedPurchase());
    }

    #[Test]
    public function submitWithTextContentSetsFields(): void
    {
        $review = ProductReview::submit(
            id: $this->reviewId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            customerId: 'cust-001',
            customerName: 'Jane Doe',
            rating: Rating::fromInt(5),
            title: 'Great product',
            content: 'Would buy again.',
            isVerifiedPurchase: true,
        );

        self::assertSame('cust-001', $review->customerId());
        self::assertSame('Jane Doe', $review->customerName());
        self::assertSame('Great product', $review->title());
        self::assertSame('Would buy again.', $review->content());
        self::assertTrue($review->isVerifiedPurchase());
    }

    #[Test]
    public function submitRecordsReviewSubmittedEvent(): void
    {
        $review = ProductReview::submit(
            id: $this->reviewId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            customerId: null,
            customerName: null,
            rating: Rating::fromInt(3),
        );

        $events = $review->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ReviewSubmitted::class, $events[0]);
    }

    #[Test]
    public function submitWithTextAndNoCustomerThrowsException(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only logged-in customers can submit text reviews');

        ProductReview::submit(
            id: $this->reviewId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            customerId: null,
            customerName: null,
            rating: Rating::fromInt(4),
            title: 'Some title',
        );
    }

    #[Test]
    public function submitWithContentAndNoCustomerThrowsException(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only logged-in customers can submit text reviews');

        ProductReview::submit(
            id: $this->reviewId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            customerId: null,
            customerName: null,
            rating: Rating::fromInt(4),
            content: 'Some content',
        );
    }

    #[Test]
    public function submitWithTitleExceedingMaxLengthThrowsException(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Review title cannot exceed 200 characters');

        ProductReview::submit(
            id: $this->reviewId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            customerId: 'cust-001',
            customerName: 'Jane',
            rating: Rating::fromInt(4),
            title: str_repeat('A', 201),
        );
    }

    #[Test]
    public function submitWithContentExceedingMaxLengthThrowsException(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Review content cannot exceed 5000 characters');

        ProductReview::submit(
            id: $this->reviewId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            customerId: 'cust-001',
            customerName: 'Jane',
            rating: Rating::fromInt(4),
            content: str_repeat('A', 5001),
        );
    }

    // --- approve() ---

    #[Test]
    public function approveTransitionsToApproved(): void
    {
        $review = $this->submitRatingOnlyReview();
        $review->popEvents();

        $review->approve();

        self::assertTrue($review->status()->isApproved());
    }

    #[Test]
    public function approveRecordsReviewApprovedEvent(): void
    {
        $review = $this->submitRatingOnlyReview();
        $review->popEvents();

        $review->approve();

        $events = $review->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ReviewApproved::class, $events[0]);
    }

    #[Test]
    public function approveAlreadyApprovedThrowsException(): void
    {
        $review = $this->submitRatingOnlyReview();
        $review->approve();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Review is already approved');

        $review->approve();
    }

    // --- reject() ---

    #[Test]
    public function rejectTransitionsToRejected(): void
    {
        $review = $this->submitRatingOnlyReview();
        $review->popEvents();

        $review->reject('Spam content');

        self::assertTrue($review->status()->isRejected());
    }

    #[Test]
    public function rejectRecordsReviewRejectedEvent(): void
    {
        $review = $this->submitRatingOnlyReview();
        $review->popEvents();

        $review->reject('Spam content');

        $events = $review->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ReviewRejected::class, $events[0]);
    }

    #[Test]
    public function rejectAlreadyRejectedThrowsException(): void
    {
        $review = $this->submitRatingOnlyReview();
        $review->reject('First reason');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Review is already rejected');

        $review->reject('Second reason');
    }

    // --- updateContent() ---

    #[Test]
    public function updateContentOnPendingReviewSucceeds(): void
    {
        $review = $this->submitTextReview();

        $review->updateContent('Updated Title', 'Updated content.');

        self::assertSame('Updated Title', $review->title());
        self::assertSame('Updated content.', $review->content());
    }

    #[Test]
    public function updateContentOnApprovedReviewThrowsException(): void
    {
        $review = $this->submitTextReview();
        $review->approve();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Can only update pending reviews');

        $review->updateContent('New Title', 'New content.');
    }

    #[Test]
    public function updateContentOnRejectedReviewThrowsException(): void
    {
        $review = $this->submitTextReview();
        $review->reject('Policy violation');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Can only update pending reviews');

        $review->updateContent('New Title', 'New content.');
    }

    // --- reconstituteFromPersistence() ---

    #[Test]
    public function reconstituteFromPersistenceRestoresFields(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-01');
        $updatedAt = new \DateTimeImmutable('2025-01-02');

        $review = ProductReview::reconstituteFromPersistence(
            id: $this->reviewId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            customerId: 'cust-001',
            customerName: 'Jane Doe',
            rating: Rating::fromInt(4),
            title: 'Good product',
            content: 'I like it.',
            status: ReviewStatus::APPROVED,
            isVerifiedPurchase: true,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        self::assertTrue($review->id()->equals($this->reviewId));
        self::assertTrue($review->status()->isApproved());
        self::assertSame('cust-001', $review->customerId());
        self::assertSame(4, $review->rating()->value());
        self::assertTrue($review->isVerifiedPurchase());
        self::assertSame($createdAt, $review->createdAt());
        self::assertSame($updatedAt, $review->updatedAt());
    }

    #[Test]
    public function reconstituteFromPersistenceDoesNotRecordEvents(): void
    {
        $review = ProductReview::reconstituteFromPersistence(
            id: $this->reviewId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            customerId: null,
            customerName: null,
            rating: Rating::fromInt(3),
            title: null,
            content: null,
            status: ReviewStatus::PENDING,
            isVerifiedPurchase: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        self::assertEmpty($review->popEvents());
    }

    // --- helpers ---

    private function submitRatingOnlyReview(): ProductReview
    {
        return ProductReview::submit(
            id: $this->reviewId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            customerId: null,
            customerName: null,
            rating: Rating::fromInt(4),
        );
    }

    private function submitTextReview(): ProductReview
    {
        return ProductReview::submit(
            id: $this->reviewId,
            productId: $this->productId,
            tenantId: $this->tenantId,
            customerId: 'cust-001',
            customerName: 'Jane Doe',
            rating: Rating::fromInt(5),
            title: 'Excellent product',
            content: 'Would buy again.',
        );
    }
}
