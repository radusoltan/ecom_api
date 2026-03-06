<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command\ReleaseExpiredReservations;

use App\Inventory\Application\Command\ReleaseExpiredReservations\ReleaseExpiredReservationsCommand;
use App\Inventory\Application\Command\ReleaseExpiredReservations\ReleaseExpiredReservationsHandler;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\StockReservation;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\StockReservationRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\CoversClass(ReleaseExpiredReservationsHandler::class)]
final class ReleaseExpiredReservationsHandlerTest extends TestCase
{
    private StockReservationRepositoryInterface $reservationRepository;
    private StockItemRepositoryInterface $stockItemRepository;
    private LoggerInterface $logger;
    private ReleaseExpiredReservationsHandler $handler;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(StockReservationRepositoryInterface::class);
        $this->stockItemRepository = $this->createMock(StockItemRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ReleaseExpiredReservationsHandler(
            $this->reservationRepository,
            $this->stockItemRepository,
            $this->logger,
        );
    }

    // ---------------------------------------------------------------
    // No expired reservations
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itDoesNothingWhenNoExpiredReservations(): void
    {
        $this->reservationRepository
            ->method('findExpiredReservations')
            ->willReturn([]);

        $this->stockItemRepository->expects(self::never())->method('findById');
        $this->stockItemRepository->expects(self::never())->method('save');
        $this->reservationRepository->expects(self::never())->method('save');

        ($this->handler)(new ReleaseExpiredReservationsCommand());
    }

    // ---------------------------------------------------------------
    // Happy path – one expired reservation released
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReleasesExpiredReservationsSuccessfully(): void
    {
        $stockItemId = StockItemId::generate();
        $reservationId = 'res-abc-123';
        $quantity = Quantity::fromInt(5);
        $expiresAt = new \DateTimeImmutable('-10 minutes');

        $reservation = $this->createMock(StockReservation::class);
        $reservation->method('stockItemId')->willReturn($stockItemId);
        $reservation->method('reservationId')->willReturn($reservationId);
        $reservation->method('quantity')->willReturn($quantity);
        $reservation->method('expiresAt')->willReturn($expiresAt);

        $stockItem = $this->createMock(StockItem::class);
        $stockItem
            ->expects(self::once())
            ->method('release')
            ->with(
                $quantity,
                $reservationId,
                self::stringContains($reservationId)
            );

        $this->reservationRepository
            ->method('findExpiredReservations')
            ->willReturn([$reservation]);

        $this->stockItemRepository
            ->method('findById')
            ->with($stockItemId)
            ->willReturn($stockItem);

        $this->stockItemRepository
            ->expects(self::once())
            ->method('save')
            ->with($stockItem);

        $reservation
            ->expects(self::once())
            ->method('release');

        $this->reservationRepository
            ->expects(self::once())
            ->method('save')
            ->with($reservation);

        $this->logger
            ->expects(self::atLeastOnce())
            ->method('info');

        ($this->handler)(new ReleaseExpiredReservationsCommand());
    }

    // ---------------------------------------------------------------
    // Stock item not found → warning, continue
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itLogsWarningAndSkipsWhenStockItemNotFound(): void
    {
        $stockItemId = StockItemId::generate();
        $reservationId = 'res-xyz-999';

        $reservation = $this->createMock(StockReservation::class);
        $reservation->method('stockItemId')->willReturn($stockItemId);
        $reservation->method('reservationId')->willReturn($reservationId);

        $this->reservationRepository
            ->method('findExpiredReservations')
            ->willReturn([$reservation]);

        $this->stockItemRepository
            ->method('findById')
            ->willReturn(null);

        $this->stockItemRepository->expects(self::never())->method('save');
        $reservation->expects(self::never())->method('release');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Stock item not found for expired reservation',
                self::arrayHasKey('reservationId')
            );

        ($this->handler)(new ReleaseExpiredReservationsCommand());
    }

    // ---------------------------------------------------------------
    // Exception during release → error log, continue
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itLogsErrorAndContinuesWhenExceptionOccursDuringRelease(): void
    {
        $stockItemId = StockItemId::generate();
        $reservationId = 'res-err-001';
        $quantity = Quantity::fromInt(3);
        $expiresAt = new \DateTimeImmutable('-5 minutes');

        $reservation = $this->createMock(StockReservation::class);
        $reservation->method('stockItemId')->willReturn($stockItemId);
        $reservation->method('reservationId')->willReturn($reservationId);
        $reservation->method('quantity')->willReturn($quantity);
        $reservation->method('expiresAt')->willReturn($expiresAt);

        $stockItem = $this->createMock(StockItem::class);
        $stockItem
            ->method('release')
            ->willThrowException(new \DomainException('Cannot release more than reserved'));

        $this->reservationRepository
            ->method('findExpiredReservations')
            ->willReturn([$reservation]);

        $this->stockItemRepository
            ->method('findById')
            ->willReturn($stockItem);

        $this->stockItemRepository->expects(self::never())->method('save');

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to release expired reservation',
                self::arrayHasKey('error')
            );

        ($this->handler)(new ReleaseExpiredReservationsCommand());
    }

    // ---------------------------------------------------------------
    // Multiple reservations – mixed results
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itProcessesMultipleReservationsAndLogsCompletionSummary(): void
    {
        $stockItemId1 = StockItemId::generate();
        $stockItemId2 = StockItemId::generate();
        $quantity = Quantity::fromInt(2);
        $expiresAt = new \DateTimeImmutable('-1 minute');

        $reservation1 = $this->createMock(StockReservation::class);
        $reservation1->method('stockItemId')->willReturn($stockItemId1);
        $reservation1->method('reservationId')->willReturn('res-1');
        $reservation1->method('quantity')->willReturn($quantity);
        $reservation1->method('expiresAt')->willReturn($expiresAt);

        $reservation2 = $this->createMock(StockReservation::class);
        $reservation2->method('stockItemId')->willReturn($stockItemId2);
        $reservation2->method('reservationId')->willReturn('res-2');
        $reservation2->method('quantity')->willReturn($quantity);
        $reservation2->method('expiresAt')->willReturn($expiresAt);

        $stockItem1 = $this->createMock(StockItem::class);
        $stockItem2 = $this->createMock(StockItem::class);
        // reservation2's stock item throws
        $stockItem2
            ->method('release')
            ->willThrowException(new \RuntimeException('oops'));

        $this->reservationRepository
            ->method('findExpiredReservations')
            ->willReturn([$reservation1, $reservation2]);

        $this->stockItemRepository
            ->method('findById')
            ->willReturnMap([
                [$stockItemId1, $stockItem1],
                [$stockItemId2, $stockItem2],
            ]);

        // Summary log at end: releasedCount=1, errors=1
        $this->logger
            ->expects(self::atLeastOnce())
            ->method('info')
            ->with(
                self::logicalOr(
                    'Expired reservation released',
                    'Expired reservations release completed'
                ),
                self::isType('array')
            );

        ($this->handler)(new ReleaseExpiredReservationsCommand());
    }

    // ---------------------------------------------------------------
    // Command accepts custom 'now' date
    // ---------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function itPassesCustomNowDateToRepository(): void
    {
        $now = new \DateTimeImmutable('2025-12-31 23:59:59');

        $this->reservationRepository
            ->expects(self::once())
            ->method('findExpiredReservations')
            ->with($now)
            ->willReturn([]);

        ($this->handler)(new ReleaseExpiredReservationsCommand($now));
    }
}
