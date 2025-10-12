<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Pricing\Application\DTO\PriceListDTO;
use App\Pricing\Application\Query\GetActivePriceLists\GetActivePriceListsQuery;
use App\Pricing\Application\Query\GetActivePriceLists\GetActivePriceListsQueryHandler;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Performance\PerformanceProfiler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class GetActivePriceListsQueryHandlerTest extends TestCase
{
    private PriceListRepositoryInterface $repository;
    private PerformanceProfiler $profiler;
    private LoggerInterface $logger;
    private GetActivePriceListsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PriceListRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->profiler = new PerformanceProfiler($this->logger);

        $this->handler = new GetActivePriceListsQueryHandler(
            $this->repository,
            $this->profiler,
            $this->logger
        );
    }

    public function testHandleReturnsArrayOfDTOs(): void
    {
        $tenantId = TenantId::generate();

        $priceList1 = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Price List 1')
        );
        $priceList1->activate();

        $priceList2 = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Price List 2')
        );
        $priceList2->activate();

        $query = new GetActivePriceListsQuery($tenantId);

        $this->repository->expects($this->once())
            ->method('findValidForTenant')
            ->with($tenantId)
            ->willReturn([$priceList1, $priceList2]);

        $result = ($this->handler)($query);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(PriceListDTO::class, $result);
        $this->assertSame('Price List 1', $result[0]->name);
        $this->assertSame('Price List 2', $result[1]->name);
    }

    public function testHandleReturnsEmptyArrayWhenNoPriceLists(): void
    {
        $tenantId = TenantId::generate();
        $query = new GetActivePriceListsQuery($tenantId);

        $this->repository->expects($this->once())
            ->method('findValidForTenant')
            ->with($tenantId)
            ->willReturn([]);

        $result = ($this->handler)($query);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testHandleConvertsAllPriceListsToDTO(): void
    {
        $tenantId = TenantId::generate();

        $priceList1 = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('High Priority'),
            500
        );
        $priceList1->activate();

        $priceList2 = PriceList::create(
            PriceListId::generate(),
            $tenantId,
            PriceListName::fromString('Low Priority'),
            100
        );
        $priceList2->activate();

        $query = new GetActivePriceListsQuery($tenantId);

        $this->repository->expects($this->once())
            ->method('findValidForTenant')
            ->with($tenantId)
            ->willReturn([$priceList1, $priceList2]);

        $result = ($this->handler)($query);

        $this->assertCount(2, $result);
        $this->assertSame(500, $result[0]->priority);
        $this->assertSame(100, $result[1]->priority);
        $this->assertTrue($result[0]->isActive);
        $this->assertTrue($result[1]->isActive);
    }
}
