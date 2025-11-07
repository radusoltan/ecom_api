<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Query;

use App\Pricing\Application\DTO\PriceListDTO;
use App\Pricing\Application\Query\GetPriceListById\GetPriceListByIdQuery;
use App\Pricing\Application\Query\GetPriceListById\GetPriceListByIdQueryHandler;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class GetPriceListByIdQueryHandlerTest extends TestCase
{
    private PriceListRepositoryInterface $repository;
    private PerformanceProfiler $profiler;
    private LoggerInterface $logger;
    private GetPriceListByIdQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PriceListRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->profiler = new PerformanceProfiler($this->logger);

        $this->handler = new GetPriceListByIdQueryHandler(
            $this->repository,
            $this->profiler,
            $this->logger
        );
    }

    public function testHandleReturnsDTOWhenPriceListExists(): void
    {
        $priceListId = PriceListId::generate();
        $priceList = PriceList::create(
            $priceListId,
            TenantId::generate(),
            PriceListName::fromString('Test Price List')
        );

        $query = new GetPriceListByIdQuery($priceListId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($priceListId)
            ->willReturn($priceList);

        $result = ($this->handler)($query);

        $this->assertInstanceOf(PriceListDTO::class, $result);
        $this->assertSame($priceListId->toString(), $result->id);
        $this->assertSame('Test Price List', $result->name);
        $this->assertSame(100, $result->priority);
        $this->assertFalse($result->isActive);
    }

    public function testHandleReturnsNullWhenPriceListNotFound(): void
    {
        $priceListId = PriceListId::generate();
        $query = new GetPriceListByIdQuery($priceListId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($priceListId)
            ->willReturn(null);

        $result = ($this->handler)($query);

        $this->assertNull($result);
    }

    public function testHandleConvertsActivePriceList(): void
    {
        $priceListId = PriceListId::generate();
        $priceList = PriceList::create(
            $priceListId,
            TenantId::generate(),
            PriceListName::fromString('Active Price List')
        );
        $priceList->activate();

        $query = new GetPriceListByIdQuery($priceListId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($priceListId)
            ->willReturn($priceList);

        $result = ($this->handler)($query);

        $this->assertNotNull($result);
        $this->assertTrue($result->isActive);
    }
}
