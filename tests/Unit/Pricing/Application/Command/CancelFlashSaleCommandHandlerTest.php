<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Application\Command\CancelFlashSale\CancelFlashSaleCommand;
use App\Pricing\Application\Command\CancelFlashSale\CancelFlashSaleCommandHandler;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\FlashSale;
use App\Pricing\Domain\Model\FlashSaleId;
use App\Pricing\Domain\Repository\FlashSaleRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(CancelFlashSaleCommandHandler::class)]
final class CancelFlashSaleCommandHandlerTest extends TestCase
{
    private FlashSaleRepositoryInterface $repository;
    private CancelFlashSaleCommandHandler $handler;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(FlashSaleRepositoryInterface::class);
        $this->handler = new CancelFlashSaleCommandHandler($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // Success paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itCancelsScheduledFlashSaleAndSaves(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $flashSale = $this->buildScheduledFlashSale($flashSaleId);

        $this->repository
            ->method('findById')
            ->with($flashSaleId, $this->tenantId)
            ->willReturn($flashSale);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (FlashSale $fs): bool {
                self::assertTrue($fs->isCancelled());

                return true;
            }));

        ($this->handler)(new CancelFlashSaleCommand($flashSaleId, $this->tenantId));
    }

    #[Test]
    public function itCallsRepositoryFindByIdWithCorrectArguments(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $flashSale = $this->buildScheduledFlashSale($flashSaleId);

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(fn (FlashSaleId $id) => $id->equals($flashSaleId)),
                self::callback(fn (TenantId $tid) => $tid->equals($this->tenantId))
            )
            ->willReturn($flashSale);

        $this->repository->method('save');

        ($this->handler)(new CancelFlashSaleCommand($flashSaleId, $this->tenantId));
    }

    // -----------------------------------------------------------------------
    // Failure paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsNotFoundExceptionWhenFlashSaleDoesNotExist(): void
    {
        $flashSaleId = FlashSaleId::generate();

        $this->repository
            ->method('findById')
            ->willReturn(null);

        $this->repository->expects(self::never())->method('save');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Flash sale not found');

        ($this->handler)(new CancelFlashSaleCommand($flashSaleId, $this->tenantId));
    }

    #[Test]
    public function itThrowsBadRequestWhenFlashSaleIsAlreadyActive(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $flashSale = $this->buildScheduledFlashSale($flashSaleId);
        $flashSale->activate(); // SCHEDULED -> ACTIVE, cannot be cancelled

        $this->repository->method('findById')->willReturn($flashSale);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(BadRequestHttpException::class);

        ($this->handler)(new CancelFlashSaleCommand($flashSaleId, $this->tenantId));
    }

    #[Test]
    public function itThrowsBadRequestWhenFlashSaleIsAlreadyEnded(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $flashSale = $this->buildScheduledFlashSale($flashSaleId);
        $flashSale->activate();
        $flashSale->end(); // ACTIVE -> ENDED, cannot be cancelled

        $this->repository->method('findById')->willReturn($flashSale);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(BadRequestHttpException::class);

        ($this->handler)(new CancelFlashSaleCommand($flashSaleId, $this->tenantId));
    }

    #[Test]
    public function itThrowsBadRequestWhenFlashSaleIsAlreadyCancelled(): void
    {
        $flashSaleId = FlashSaleId::generate();
        $flashSale = $this->buildScheduledFlashSale($flashSaleId);
        $flashSale->cancel(); // SCHEDULED -> CANCELLED

        // Try to cancel an already-cancelled flash sale
        $this->repository->method('findById')->willReturn($flashSale);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(BadRequestHttpException::class);

        ($this->handler)(new CancelFlashSaleCommand($flashSaleId, $this->tenantId));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildScheduledFlashSale(FlashSaleId $id): FlashSale
    {
        $start = (new \DateTimeImmutable())->modify('+1 hour');
        $end = (new \DateTimeImmutable())->modify('+3 hours');

        return FlashSale::create(
            id: $id,
            tenantId: $this->tenantId,
            name: 'Test Flash Sale',
            productIds: [ProductId::fromString('00000000-0000-4000-8000-000000000010')],
            discount: Discount::percentage(20.0),
            startTime: $start,
            endTime: $end,
        );
    }
}
