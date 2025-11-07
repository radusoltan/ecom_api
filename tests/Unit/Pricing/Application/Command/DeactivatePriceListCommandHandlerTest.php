<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Command;

use App\Pricing\Application\Command\DeactivatePriceList\DeactivatePriceListCommand;
use App\Pricing\Application\Command\DeactivatePriceList\DeactivatePriceListCommandHandler;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DeactivatePriceListCommandHandlerTest extends TestCase
{
    private PriceListRepositoryInterface $repository;
    private PerformanceProfiler $profiler;
    private LoggerInterface $logger;
    private DeactivatePriceListCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PriceListRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->profiler = new PerformanceProfiler($this->logger);

        $this->handler = new DeactivatePriceListCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger
        );
    }

    public function testHandleDeactivatesPriceList(): void
    {
        $priceListId = PriceListId::generate();
        $priceList = PriceList::create(
            $priceListId,
            TenantId::generate(),
            PriceListName::fromString('Test Price List')
        );
        $priceList->activate();  // Must be active before deactivating

        $command = new DeactivatePriceListCommand($priceListId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($priceListId)
            ->willReturn($priceList);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PriceList $savedPriceList) {
                return !$savedPriceList->isActive();
            }));

        ($this->handler)($command);
    }

    public function testHandleThrowsExceptionWhenPriceListNotFound(): void
    {
        $priceListId = PriceListId::generate();
        $command = new DeactivatePriceListCommand($priceListId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($priceListId)
            ->willReturn(null);

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('PriceList with ID %s not found', $priceListId->toString()));

        ($this->handler)($command);
    }

    public function testHandleThrowsExceptionWhenAlreadyInactive(): void
    {
        $priceListId = PriceListId::generate();
        $priceList = PriceList::create(
            $priceListId,
            TenantId::generate(),
            PriceListName::fromString('Already Inactive Price List')
        );
        // Don't activate - created as inactive by default

        $command = new DeactivatePriceListCommand($priceListId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($priceListId)
            ->willReturn($priceList);

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PriceList is already inactive');

        ($this->handler)($command);
    }
}
