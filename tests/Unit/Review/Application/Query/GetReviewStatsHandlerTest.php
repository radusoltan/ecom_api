<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Review\Application\Query\GetReviewStats;
use App\Review\Application\Query\GetReviewStatsHandler;
use App\Review\Domain\Repository\ProductReviewRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetReviewStatsHandler::class)]
final class GetReviewStatsHandlerTest extends TestCase
{
    private ProductReviewRepositoryInterface $repository;
    private GetReviewStatsHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ProductReviewRepositoryInterface::class);
        $this->handler = new GetReviewStatsHandler($this->repository);
    }

    #[Test]
    public function handleReturnsStatsArray(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $query = new GetReviewStats($productId, $tenantId);

        $this->repository->method('getAverageRating')->willReturn(4.5);
        $this->repository->method('getReviewCount')->willReturn(20);

        $result = ($this->handler)($query);

        self::assertIsArray($result);
        self::assertSame(4.5, $result['averageRating']);
        self::assertSame(20, $result['totalReviews']);
        self::assertSame($productId->toString(), $result['productId']);
    }

    #[Test]
    public function handleReturnsNullAverageWhenNoReviews(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $query = new GetReviewStats($productId, $tenantId);

        $this->repository->method('getAverageRating')->willReturn(null);
        $this->repository->method('getReviewCount')->willReturn(0);

        $result = ($this->handler)($query);

        self::assertNull($result['averageRating']);
        self::assertSame(0, $result['totalReviews']);
    }
}
