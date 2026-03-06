<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Pricing\Application\Query\GetUpcomingFlashSales\GetUpcomingFlashSalesQuery;
use App\Pricing\Application\Query\GetUpcomingFlashSales\GetUpcomingFlashSalesQueryHandler;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetUpcomingFlashSalesQueryHandler::class)]
final class GetUpcomingFlashSalesQueryHandlerTest extends TestCase
{
    private FlashSaleRepositoryInterface $repository;
    private GetUpcomingFlashSalesQueryHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(FlashSaleRepositoryInterface::class);
        $this->handler = new GetUpcomingFlashSalesQueryHandler($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsUpcomingFlashSalesForTenant(): void
    {
        $flashSale = $this->createMock(FlashSale::class);

        $this->repository
            ->expects(self::once())
            ->method('findUpcoming')
            ->with($this->tenantId)
            ->willReturn([$flashSale]);

        $query = new GetUpcomingFlashSalesQuery($this->tenantId);
        $result = ($this->handler)($query);

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame($flashSale, $result[0]);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoUpcomingFlashSales(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findUpcoming')
            ->with($this->tenantId)
            ->willReturn([]);

        $query = new GetUpcomingFlashSalesQuery($this->tenantId);
        $result = ($this->handler)($query);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function itReturnsMultipleUpcomingFlashSales(): void
    {
        $flashSales = [
            $this->createMock(FlashSale::class),
            $this->createMock(FlashSale::class),
        ];

        $this->repository
            ->method('findUpcoming')
            ->willReturn($flashSales);

        $query = new GetUpcomingFlashSalesQuery($this->tenantId);
        $result = ($this->handler)($query);

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(FlashSale::class, $result);
    }
}
