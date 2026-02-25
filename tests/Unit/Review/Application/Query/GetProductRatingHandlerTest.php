<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Query\GetProductRating;
use App\Review\Application\Query\GetProductRatingHandler;
use App\Review\Application\Query\RatingDTO;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetProductRatingHandler::class)]
final class GetProductRatingHandlerTest extends TestCase
{
    private ProductReviewRepositoryInterface $repository;
    private GetProductRatingHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ProductReviewRepositoryInterface::class);
        $this->handler = new GetProductRatingHandler($this->repository);
    }

    #[Test]
    public function handleReturnsRatingDto(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $query = new GetProductRating($productId, $tenantId);

        $this->repository->method('getAverageRating')->willReturn(4.2);
        $this->repository->method('getReviewCount')->willReturn(15);

        $result = ($this->handler)($query);

        self::assertInstanceOf(RatingDTO::class, $result);
        self::assertSame($productId->toString(), $result->productId);
        self::assertSame(4.2, $result->averageRating);
        self::assertSame(15, $result->reviewCount);
    }

    #[Test]
    public function handleReturnsZeroAverageWhenNoReviews(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $query = new GetProductRating($productId, $tenantId);

        $this->repository->method('getAverageRating')->willReturn(null);
        $this->repository->method('getReviewCount')->willReturn(0);

        $result = ($this->handler)($query);

        self::assertSame(0.0, $result->averageRating);
        self::assertSame(0, $result->reviewCount);
    }
}
