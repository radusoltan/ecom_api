<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Command;

use App\Pricing\Application\Command\CreatePriceList\CreatePriceListCommand;
use App\Pricing\Application\Command\CreatePriceList\CreatePriceListCommandHandler;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CreatePriceListCommandHandlerTest extends TestCase
{
    private PriceListRepositoryInterface $repository;
    private PerformanceProfiler $profiler;
    private LoggerInterface $logger;
    private CreatePriceListCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PriceListRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->profiler = new PerformanceProfiler($this->logger);

        $this->handler = new CreatePriceListCommandHandler(
            $this->repository,
            $this->profiler,
            $this->logger
        );
    }

    public function testHandleCreatesAndSavesPriceList(): void
    {
        $priceListId = PriceListId::generate();
        $tenantId = TenantId::generate();
        $name = PriceListName::fromString('Test Price List');

        $command = new CreatePriceListCommand(
            $priceListId,
            $tenantId,
            $name
        );

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PriceList $priceList) use ($priceListId, $tenantId, $name) {
                return $priceList->id()->equals($priceListId)
                    && $priceList->tenantId()->equals($tenantId)
                    && $priceList->name()->equals($name)
                    && 100 === $priceList->priority()  // Default priority
                    && !$priceList->isActive();  // Created inactive
            }));

        ($this->handler)($command);
    }

    public function testHandleCreatesWithCustomPriority(): void
    {
        $command = new CreatePriceListCommand(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('High Priority Price List'),
            500  // Custom priority
        );

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PriceList $priceList) {
                return 500 === $priceList->priority();
            }));

        ($this->handler)($command);
    }

    public function testHandleCreatesWithValidityDates(): void
    {
        $validFrom = new \DateTimeImmutable('2025-06-01');
        $validTo = new \DateTimeImmutable('2025-08-31');

        $command = new CreatePriceListCommand(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Summer Sale'),
            100,
            $validFrom,
            $validTo
        );

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PriceList $priceList) use ($validFrom, $validTo) {
                return $priceList->validFrom() == $validFrom
                    && $priceList->validTo() == $validTo;
            }));

        ($this->handler)($command);
    }

    public function testHandleCreatesWithNullDates(): void
    {
        $command = new CreatePriceListCommand(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Permanent Price List'),
            100,
            null,
            null
        );

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (PriceList $priceList) {
                return null === $priceList->validFrom()
                    && null === $priceList->validTo();
            }));

        ($this->handler)($command);
    }
}
