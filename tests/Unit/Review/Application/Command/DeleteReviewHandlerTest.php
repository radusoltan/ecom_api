<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Command\DeleteReview;
use App\Review\Application\Command\DeleteReviewHandler;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use App\Review\Domain\ValueObject\Rating;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteReviewHandler::class)]
final class DeleteReviewHandlerTest extends TestCase
{
    private ProductReviewRepositoryInterface $repository;
    private DeleteReviewHandler $handler;

    protected function setUp(): void
    {
        // ReviewId is defined in ProductReview.php, force autoload
        class_exists(ProductReview::class);

        $this->repository = $this->createMock(ProductReviewRepositoryInterface::class);
        $this->handler = new DeleteReviewHandler($this->repository);
    }

    #[Test]
    public function handleDeletesReviewByOwner(): void
    {
        $reviewId = ReviewId::generate();
        $review = $this->buildReviewForCustomer($reviewId, 'cust-001');
        $command = new DeleteReview($reviewId, 'cust-001');

        $this->repository->method('findById')->willReturn($review);
        $this->repository->expects(self::once())
            ->method('delete')
            ->with($review);

        ($this->handler)($command);
    }

    #[Test]
    public function handleDeletesWithoutOwnerCheck(): void
    {
        $reviewId = ReviewId::generate();
        $review = $this->buildReviewForCustomer($reviewId, 'cust-001');
        $command = new DeleteReview($reviewId, customerId: null);

        $this->repository->method('findById')->willReturn($review);
        $this->repository->expects(self::once())
            ->method('delete');

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsWhenReviewNotFound(): void
    {
        $command = new DeleteReview(ReviewId::generate(), 'cust-001');

        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Review not found');

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsWhenCustomerDoesNotOwnReview(): void
    {
        $reviewId = ReviewId::generate();
        $review = $this->buildReviewForCustomer($reviewId, 'cust-001');
        $command = new DeleteReview($reviewId, 'cust-999');

        $this->repository->method('findById')->willReturn($review);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('You can only delete your own reviews');

        ($this->handler)($command);
    }

    private function buildReviewForCustomer(ReviewId $reviewId, string $customerId): ProductReview
    {
        return ProductReview::submit(
            id: $reviewId,
            productId: ProductId::generate(),
            tenantId: TenantId::generate(),
            customerId: $customerId,
            customerName: 'Jane Doe',
            rating: Rating::fromInt(4),
        );
    }
}
