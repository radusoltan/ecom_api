<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Command\ApproveReview;
use App\Review\Application\Command\ApproveReviewHandler;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use App\Review\Domain\ValueObject\Rating;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApproveReviewHandler::class)]
final class ApproveReviewHandlerTest extends TestCase
{
    private ProductReviewRepositoryInterface $repository;
    private ApproveReviewHandler $handler;

    protected function setUp(): void
    {
        // ReviewId is defined in ProductReview.php, force autoload
        class_exists(ProductReview::class);

        $this->repository = $this->createMock(ProductReviewRepositoryInterface::class);
        $this->handler = new ApproveReviewHandler($this->repository);
    }

    #[Test]
    public function handleApprovesAndSavesReview(): void
    {
        $reviewId = ReviewId::generate();
        $review = $this->buildPendingReview($reviewId);
        $command = new ApproveReview($reviewId);

        $this->repository->method('findById')->willReturn($review);
        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(
                fn (ProductReview $r) => $r->status()->isApproved(),
            ));

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsWhenReviewNotFound(): void
    {
        $command = new ApproveReview(ReviewId::generate());

        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Review not found');

        ($this->handler)($command);
    }

    private function buildPendingReview(ReviewId $reviewId): ProductReview
    {
        return ProductReview::submit(
            id: $reviewId,
            productId: ProductId::generate(),
            tenantId: TenantId::generate(),
            customerId: null,
            customerName: null,
            rating: Rating::fromInt(4),
        );
    }
}
