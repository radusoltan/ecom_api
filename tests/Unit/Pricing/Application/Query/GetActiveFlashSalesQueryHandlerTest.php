<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Pricing\Application\Query\GetActiveFlashSales\GetActiveFlashSalesQuery;
use App\Pricing\Application\Query\GetActiveFlashSales\GetActiveFlashSalesQueryHandler;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetActiveFlashSalesQueryHandler::class)]
final class GetActiveFlashSalesQueryHandlerTest extends TestCase
{
    private FlashSaleRepositoryInterface $repository;
    private GetActiveFlashSalesQueryHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(FlashSaleRepositoryInterface::class);
        $this->handler = new GetActiveFlashSalesQueryHandler($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsActiveFlashSalesForTenant(): void
    {
        $flashSale = $this->createMock(FlashSale::class);

        $this->repository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->with($this->tenantId)
            ->willReturn([$flashSale]);

        $query = new GetActiveFlashSalesQuery($this->tenantId);
        $result = ($this->handler)($query);

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame($flashSale, $result[0]);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoActiveFlashSales(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->with($this->tenantId)
            ->willReturn([]);

        $query = new GetActiveFlashSalesQuery($this->tenantId);
        $result = ($this->handler)($query);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function itReturnsMultipleActiveFlashSales(): void
    {
        $flashSale1 = $this->createMock(FlashSale::class);
        $flashSale2 = $this->createMock(FlashSale::class);
        $flashSale3 = $this->createMock(FlashSale::class);

        $this->repository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->with($this->tenantId)
            ->willReturn([$flashSale1, $flashSale2, $flashSale3]);

        $query = new GetActiveFlashSalesQuery($this->tenantId);
        $result = ($this->handler)($query);

        self::assertCount(3, $result);
        self::assertContainsOnlyInstancesOf(FlashSale::class, $result);
    }

    #[Test]
    public function itPassesTenantIdToRepository(): void
    {
        $differentTenantId = TenantId::generate();

        $this->repository
            ->expects(self::once())
            ->method('findActiveByTenant')
            ->with($differentTenantId)
            ->willReturn([]);

        $query = new GetActiveFlashSalesQuery($differentTenantId);
        ($this->handler)($query);
    }
}
