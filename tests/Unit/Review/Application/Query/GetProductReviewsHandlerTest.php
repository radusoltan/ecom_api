<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Query\GetProductReviews;
use App\Review\Application\Query\GetProductReviewsHandler;
use App\Review\Application\Query\ReviewDTO;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use App\Review\Domain\ValueObject\Rating;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetProductReviewsHandler::class)]
final class GetProductReviewsHandlerTest extends TestCase
{
    private ProductReviewRepositoryInterface $repository;
    private GetProductReviewsHandler $handler;

    protected function setUp(): void
    {
        // ReviewId is defined in ProductReview.php, force autoload
        class_exists(ProductReview::class);

        $this->repository = $this->createMock(ProductReviewRepositoryInterface::class);
        $this->handler = new GetProductReviewsHandler($this->repository);
    }

    #[Test]
    public function handleReturnsApprovedReviewsWhenFlagIsTrue(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $query = new GetProductReviews($productId, $tenantId, onlyApproved: true);
        $review = $this->buildReview($productId, $tenantId, 'Alice');

        $this->repository->expects(self::once())
            ->method('findApprovedByProductId')
            ->with($productId, $tenantId)
            ->willReturn([$review]);
        $this->repository->expects(self::never())
            ->method('findByProductId');

        $results = ($this->handler)($query);

        self::assertCount(1, $results);
        self::assertInstanceOf(ReviewDTO::class, $results[0]);
        self::assertSame('Alice', $results[0]->customerName);
    }

    #[Test]
    public function handleReturnsAllReviewsWhenFlagIsFalse(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $query = new GetProductReviews($productId, $tenantId, onlyApproved: false);
        $review = $this->buildReview($productId, $tenantId);

        $this->repository->expects(self::once())
            ->method('findByProductId')
            ->with($productId, $tenantId)
            ->willReturn([$review]);
        $this->repository->expects(self::never())
            ->method('findApprovedByProductId');

        $results = ($this->handler)($query);

        self::assertCount(1, $results);
    }

    #[Test]
    public function handleSetsAnonymousWhenNoCustomerName(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $query = new GetProductReviews($productId, $tenantId, onlyApproved: true);
        $review = $this->buildReview($productId, $tenantId, customerName: null);

        $this->repository->method('findApprovedByProductId')->willReturn([$review]);

        $results = ($this->handler)($query);

        self::assertSame('Anonymous', $results[0]->customerName);
    }

    private function buildReview(
        ProductId $productId,
        TenantId $tenantId,
        ?string $customerName = null,
    ): ProductReview {
        return ProductReview::submit(
            id: ReviewId::generate(),
            productId: $productId,
            tenantId: $tenantId,
            customerId: null,
            customerName: $customerName,
            rating: Rating::fromInt(4),
        );
    }
}
