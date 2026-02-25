<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Query\GetReviewById;
use App\Review\Application\Query\GetReviewByIdHandler;
use App\Review\Domain\Model\ProductReview;
use App\Review\Domain\Model\ReviewId;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use App\Review\Domain\ValueObject\Rating;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetReviewByIdHandler::class)]
final class GetReviewByIdHandlerTest extends TestCase
{
    private ProductReviewRepositoryInterface $repository;
    private GetReviewByIdHandler $handler;

    protected function setUp(): void
    {
        // ReviewId is defined in ProductReview.php, force autoload
        class_exists(ProductReview::class);

        $this->repository = $this->createMock(ProductReviewRepositoryInterface::class);
        $this->handler = new GetReviewByIdHandler($this->repository);
    }

    #[Test]
    public function handleReturnsReviewWhenFound(): void
    {
        $reviewId = ReviewId::generate();
        $review = ProductReview::submit(
            id: $reviewId,
            productId: ProductId::generate(),
            tenantId: TenantId::generate(),
            customerId: null,
            customerName: null,
            rating: Rating::fromInt(3),
        );

        $this->repository->method('findById')->with($reviewId)->willReturn($review);

        $result = ($this->handler)(new GetReviewById($reviewId));

        self::assertSame($review, $result);
    }

    #[Test]
    public function handleReturnsNullWhenNotFound(): void
    {
        $reviewId = ReviewId::generate();

        $this->repository->method('findById')->willReturn(null);

        $result = ($this->handler)(new GetReviewById($reviewId));

        self::assertNull($result);
    }
}
