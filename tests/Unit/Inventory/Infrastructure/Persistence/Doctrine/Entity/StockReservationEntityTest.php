<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\Persistence\Doctrine\Entity;

use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\StockReservation;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockReservationEntity;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class StockReservationEntityTest extends TestCase
{
    private const RESERVATION_ID = 'res-uuid-001';
    private const STOCK_ITEM_ID = '01900000-0000-7000-8000-000000000001';
    private const WAREHOUSE_ID = '00000000-0000-4000-8000-000000000003';
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private function makeReservation(
        bool $isReleased = false,
        ?\DateTimeImmutable $releasedAt = null,
    ): StockReservation {
        $reservedAt = new \DateTimeImmutable('2024-01-15T10:00:00+00:00');
        $expiresAt = new \DateTimeImmutable('2024-01-15T10:15:00+00:00');

        return StockReservation::reconstituteFromPersistence(
            self::RESERVATION_ID,
            StockItemId::fromString(self::STOCK_ITEM_ID),
            WarehouseId::fromString(self::WAREHOUSE_ID),
            TenantId::fromString(self::TENANT_ID),
            Quantity::fromInt(5),
            $reservedAt,
            $expiresAt,
            $isReleased,
            $releasedAt,
        );
    }

    public function testFromDomainModelRoundtrip(): void
    {
        $reservation = $this->makeReservation();

        $entity = StockReservationEntity::fromDomainModel($reservation);

        self::assertSame(self::RESERVATION_ID, $entity->getId());
    }

    public function testToDomainModelRestoresAllFields(): void
    {
        $reservation = $this->makeReservation();

        $entity = StockReservationEntity::fromDomainModel($reservation);
        $restored = $entity->toDomainModel();

        self::assertSame(self::RESERVATION_ID, $restored->reservationId());
        self::assertSame(self::STOCK_ITEM_ID, $restored->stockItemId()->toString());
        self::assertSame(self::WAREHOUSE_ID, $restored->warehouseId()->toString());
        self::assertSame(self::TENANT_ID, $restored->tenantId()->toString());
        self::assertSame(5, $restored->quantity()->value());
        self::assertSame('2024-01-15T10:00:00+00:00', $restored->reservedAt()->format(\DateTimeInterface::ATOM));
        self::assertSame('2024-01-15T10:15:00+00:00', $restored->expiresAt()->format(\DateTimeInterface::ATOM));
        self::assertFalse($restored->isReleased());
        self::assertNull($restored->releasedAt());
    }

    public function testFromDomainModelWithReleasedReservation(): void
    {
        $releasedAt = new \DateTimeImmutable('2024-01-15T10:10:00+00:00');
        $reservation = $this->makeReservation(isReleased: true, releasedAt: $releasedAt);

        $entity = StockReservationEntity::fromDomainModel($reservation);
        $restored = $entity->toDomainModel();

        self::assertTrue($restored->isReleased());
        self::assertSame('2024-01-15T10:10:00+00:00', $restored->releasedAt()->format(\DateTimeInterface::ATOM));
    }

    public function testToDomainModelWithNullReleasedAt(): void
    {
        $reservation = $this->makeReservation(isReleased: false, releasedAt: null);

        $entity = StockReservationEntity::fromDomainModel($reservation);
        $restored = $entity->toDomainModel();

        self::assertNull($restored->releasedAt());
        self::assertFalse($restored->isReleased());
    }

    public function testUpdateFromDomainModelChangesMutableFields(): void
    {
        $original = $this->makeReservation(isReleased: false);
        $entity = StockReservationEntity::fromDomainModel($original);

        $releasedAt = new \DateTimeImmutable('2024-01-15T10:12:00+00:00');
        $newExpiresAt = new \DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $updated = StockReservation::reconstituteFromPersistence(
            self::RESERVATION_ID,
            StockItemId::fromString(self::STOCK_ITEM_ID),
            WarehouseId::fromString(self::WAREHOUSE_ID),
            TenantId::fromString(self::TENANT_ID),
            Quantity::fromInt(5),
            new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
            $newExpiresAt,
            true,
            $releasedAt,
        );

        $entity->updateFromDomainModel($updated);
        $restored = $entity->toDomainModel();

        self::assertTrue($restored->isReleased());
        self::assertSame('2024-01-15T10:12:00+00:00', $restored->releasedAt()->format(\DateTimeInterface::ATOM));
        self::assertSame('2024-01-15T10:30:00+00:00', $restored->expiresAt()->format(\DateTimeInterface::ATOM));
    }

    public function testIdGetterReturnsReservationId(): void
    {
        $reservation = $this->makeReservation();
        $entity = StockReservationEntity::fromDomainModel($reservation);

        self::assertSame(self::RESERVATION_ID, $entity->getId());
    }

    public function testFromDomainModelWithLargeQuantity(): void
    {
        $reservation = StockReservation::reconstituteFromPersistence(
            'res-large-qty',
            StockItemId::fromString(self::STOCK_ITEM_ID),
            WarehouseId::fromString(self::WAREHOUSE_ID),
            TenantId::fromString(self::TENANT_ID),
            Quantity::fromInt(999999),
            new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            new \DateTimeImmutable('2024-01-01T00:15:00+00:00'),
            false,
            null,
        );

        $entity = StockReservationEntity::fromDomainModel($reservation);
        $restored = $entity->toDomainModel();

        self::assertSame(999999, $restored->quantity()->value());
    }
}
