<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\DTO\PriceHistoryDTO;
use App\Pricing\Application\Query\GetPriceHistory\GetPriceHistoryQuery;
use App\Pricing\Application\Query\GetPriceHistory\GetPriceHistoryQueryHandler;
use App\Pricing\Domain\Repository\PriceHistoryRepositoryInterface;
use App\Pricing\Domain\ValueObject\PriceChange;
use App\Pricing\Domain\ValueObject\PriceChangeSource;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetPriceHistoryQueryHandler::class)]
final class GetPriceHistoryQueryHandlerTest extends TestCase
{
    private PriceHistoryRepositoryInterface $repository;
    private GetPriceHistoryQueryHandler $handler;
    private string $tenantIdString;
    private string $productIdString;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PriceHistoryRepositoryInterface::class);
        $this->handler = new GetPriceHistoryQueryHandler($this->repository);
        $this->tenantIdString = '00000000-0000-4000-8000-000000000001';
        $this->productIdString = '00000000-0000-4000-8000-000000000002';
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsPriceHistoryDTOsForProduct(): void
    {
        $productId = ProductId::fromString($this->productIdString);
        $tenantId = TenantId::fromString($this->tenantIdString);

        $priceChange = PriceChange::create(
            productId: $productId,
            oldPrice: Money::fromScalars(1000, 'USD'),
            newPrice: Money::fromScalars(900, 'USD'),
            reason: 'Sale',
            source: PriceChangeSource::MANUAL,
        );

        $this->repository
            ->expects(self::once())
            ->method('findByProductId')
            ->with(
                self::callback(fn ($pid) => $pid->toString() === $this->productIdString),
                self::callback(fn ($tid) => $tid->toString() === $this->tenantIdString),
                50,
                0,
            )
            ->willReturn([$priceChange]);

        $query = new GetPriceHistoryQuery($this->productIdString, $this->tenantIdString);
        $result = ($this->handler)($query);

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertContainsOnlyInstancesOf(PriceHistoryDTO::class, $result);
        self::assertSame($this->productIdString, $result[0]->productId);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoPriceHistory(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByProductId')
            ->willReturn([]);

        $query = new GetPriceHistoryQuery($this->productIdString, $this->tenantIdString);
        $result = ($this->handler)($query);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function itForwardsPaginationParametersToRepository(): void
    {
        $limit = 10;
        $offset = 20;

        $this->repository
            ->expects(self::once())
            ->method('findByProductId')
            ->with(
                self::anything(),
                self::anything(),
                $limit,
                $offset,
            )
            ->willReturn([]);

        $query = new GetPriceHistoryQuery($this->productIdString, $this->tenantIdString, $limit, $offset);
        ($this->handler)($query);
    }

    #[Test]
    public function itMapsMultiplePriceChangesToDTOs(): void
    {
        $productId = ProductId::fromString($this->productIdString);

        $changes = [];
        foreach ([900, 800, 700] as $amount) {
            $changes[] = PriceChange::create(
                productId: $productId,
                oldPrice: Money::fromScalars(1000, 'USD'),
                newPrice: Money::fromScalars($amount, 'USD'),
                reason: 'Discount',
                source: PriceChangeSource::PROMOTION,
            );
        }

        $this->repository->method('findByProductId')->willReturn($changes);

        $query = new GetPriceHistoryQuery($this->productIdString, $this->tenantIdString);
        $result = ($this->handler)($query);

        self::assertCount(3, $result);
        foreach ($result as $dto) {
            self::assertInstanceOf(PriceHistoryDTO::class, $dto);
            self::assertSame($this->productIdString, $dto->productId);
        }
    }
}
