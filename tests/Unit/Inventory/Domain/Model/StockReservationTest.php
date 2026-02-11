<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\StockReservation;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class StockReservationTest extends TestCase
{
    public function testCreateReservation(): void
    {
        $reservationId = 'cart-123';
        $stockItemId = StockItemId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::generate();
        $quantity = Quantity::fromInt(10);

        $reservation = StockReservation::create($reservationId, $stockItemId, $warehouseId, $tenantId, $quantity);

        $this->assertEquals($reservationId, $reservation->reservationId());
        $this->assertTrue($reservation->stockItemId()->equals($stockItemId));
        $this->assertTrue($reservation->warehouseId()->equals($warehouseId));
        $this->assertTrue($reservation->tenantId()->equals($tenantId));
        $this->assertEquals(10, $reservation->quantity()->value());
        $this->assertFalse($reservation->isReleased());
        $this->assertNull($reservation->releasedAt());
    }

    public function testReservationExpiresAfter15Minutes(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        // Check immediately - should not be expired
        $this->assertFalse($reservation->isExpired($reservation->reservedAt()));

        // Check 14 minutes later - should not be expired
        $fourteenMinutesLater = $reservation->reservedAt()->modify('+14 minutes');
        $this->assertFalse($reservation->isExpired($fourteenMinutesLater));

        // Check 15 minutes later - should be expired
        $fifteenMinutesLater = $reservation->reservedAt()->modify('+15 minutes');
        $this->assertTrue($reservation->isExpired($fifteenMinutesLater));

        // Check 16 minutes later - should still be expired
        $sixteenMinutesLater = $reservation->reservedAt()->modify('+16 minutes');
        $this->assertTrue($reservation->isExpired($sixteenMinutesLater));
    }

    public function testExtendReservation(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $originalExpiresAt = $reservation->expiresAt();

        // Wait 10 minutes and extend
        $tenMinutesLater = $reservation->reservedAt()->modify('+10 minutes');
        $reservation->extend($tenMinutesLater);

        // New expiry should be 15 minutes from extension time
        $expectedNewExpiry = $tenMinutesLater->modify('+15 minutes');
        $this->assertEquals(
            $expectedNewExpiry->getTimestamp(),
            $reservation->expiresAt()->getTimestamp()
        );

        // Should not be expired at the old expiry time
        $this->assertFalse($reservation->isExpired($originalExpiresAt));

        // Should be expired at the new expiry time
        $this->assertTrue($reservation->isExpired($expectedNewExpiry));
    }

    public function testCannotExtendReleasedReservation(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $reservation->release();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot extend released reservation');

        $reservation->extend();
    }

    public function testReleaseReservation(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $this->assertFalse($reservation->isReleased());
        $this->assertNull($reservation->releasedAt());

        $reservation->release();

        $this->assertTrue($reservation->isReleased());
        $this->assertInstanceOf(\DateTimeImmutable::class, $reservation->releasedAt());
    }

    public function testCannotReleaseAlreadyReleasedReservation(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $reservation->release();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Reservation already released');

        $reservation->release();
    }

    public function testReleasedReservationIsNotConsideredExpired(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $reservation->release();

        // Even after expiry time, released reservation should not be considered "expired"
        $afterExpiryTime = $reservation->expiresAt()->modify('+1 hour');
        $this->assertFalse($reservation->isExpired($afterExpiryTime));
    }

    public function testWarehouseIdIsStored(): void
    {
        $warehouseId = WarehouseId::generate();
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            $warehouseId,
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $this->assertTrue($reservation->warehouseId()->equals($warehouseId));
    }

    public function testReconstituteFromPersistenceIncludesWarehouseId(): void
    {
        $reservationId = 'cart-123';
        $stockItemId = StockItemId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::generate();
        $quantity = Quantity::fromInt(10);
        $reservedAt = new \DateTimeImmutable('2025-01-01 12:00:00');
        $expiresAt = new \DateTimeImmutable('2025-01-01 12:15:00');

        $reservation = StockReservation::reconstituteFromPersistence(
            $reservationId,
            $stockItemId,
            $warehouseId,
            $tenantId,
            $quantity,
            $reservedAt,
            $expiresAt,
            false,
            null
        );

        $this->assertEquals($reservationId, $reservation->reservationId());
        $this->assertTrue($reservation->stockItemId()->equals($stockItemId));
        $this->assertTrue($reservation->warehouseId()->equals($warehouseId));
        $this->assertTrue($reservation->tenantId()->equals($tenantId));
        $this->assertEquals(10, $reservation->quantity()->value());
        $this->assertSame($reservedAt, $reservation->reservedAt());
        $this->assertSame($expiresAt, $reservation->expiresAt());
        $this->assertFalse($reservation->isReleased());
        $this->assertNull($reservation->releasedAt());
    }

    public function testCreateReservationWithZeroQuantity(): void
    {
        $reservation = StockReservation::create(
            'cart-zero',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::zero()
        );

        $this->assertEquals(0, $reservation->quantity()->value());
        $this->assertTrue($reservation->quantity()->isZero());
    }

    public function testIsExpiredExactlyAt15Minutes(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $exactExpiryTime = $reservation->reservedAt()->modify('+15 minutes');

        $this->assertTrue($reservation->isExpired($exactExpiryTime));
    }

    public function testIsExpiredOneSecondBeforeExpiry(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $oneSecondBeforeExpiry = $reservation->expiresAt()->modify('-1 second');

        $this->assertFalse($reservation->isExpired($oneSecondBeforeExpiry));
    }

    public function testIsExpiredOneSecondAfterExpiry(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $oneSecondAfterExpiry = $reservation->expiresAt()->modify('+1 second');

        $this->assertTrue($reservation->isExpired($oneSecondAfterExpiry));
    }

    public function testIsExpiredWithoutProvidingNow(): void
    {
        $reservation = StockReservation::create(
            'cart-old',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        // Immediately after creation, should not be expired
        $this->assertFalse($reservation->isExpired());
    }

    public function testExtendReservationMultipleTimes(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $firstExtension = $reservation->reservedAt()->modify('+10 minutes');
        $reservation->extend($firstExtension);
        $firstExpiry = $reservation->expiresAt();

        $secondExtension = $firstExtension->modify('+10 minutes');
        $reservation->extend($secondExtension);
        $secondExpiry = $reservation->expiresAt();

        $this->assertGreaterThan($firstExpiry->getTimestamp(), $secondExpiry->getTimestamp());
        $this->assertEquals(
            $secondExtension->modify('+15 minutes')->getTimestamp(),
            $secondExpiry->getTimestamp()
        );
    }

    public function testExtendReservationWithoutProvidingNow(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $originalExpiresAt = $reservation->expiresAt();

        // Sleep for 1 second to ensure time has passed
        sleep(1);

        // Extend without providing time (uses current time)
        $reservation->extend();

        // New expiry should be later than original
        $this->assertGreaterThan(
            $originalExpiresAt->getTimestamp(),
            $reservation->expiresAt()->getTimestamp()
        );
    }

    public function testExtendReservationAfterPartialTimeout(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        // Wait 12 minutes (3 minutes before expiry)
        $twelveMinutesLater = $reservation->reservedAt()->modify('+12 minutes');
        $this->assertFalse($reservation->isExpired($twelveMinutesLater));

        $reservation->extend($twelveMinutesLater);

        // Should have 15 more minutes from the 12-minute mark
        $expectedNewExpiry = $twelveMinutesLater->modify('+15 minutes');
        $this->assertEquals(
            $expectedNewExpiry->getTimestamp(),
            $reservation->expiresAt()->getTimestamp()
        );
    }

    public function testReleaseReservationImmediatelyAfterCreation(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $reservation->release();

        $this->assertTrue($reservation->isReleased());
        $this->assertNotNull($reservation->releasedAt());
    }

    public function testReleaseReservationJustBeforeExpiry(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        // Just before expiry
        $beforeExpiry = $reservation->expiresAt()->modify('-1 second');
        $this->assertFalse($reservation->isExpired($beforeExpiry));

        $reservation->release();

        $this->assertTrue($reservation->isReleased());
        $this->assertFalse($reservation->isExpired($beforeExpiry));
    }

    public function testReleasedReservationRemainsNotExpiredEvenAfterLongTime(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $reservation->release();

        // Check long after original expiry time
        $longAfterExpiry = $reservation->expiresAt()->modify('+1 day');
        $this->assertFalse($reservation->isExpired($longAfterExpiry));
    }

    public function testReconstituteReleasedReservation(): void
    {
        $reservationId = 'cart-released';
        $stockItemId = StockItemId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::generate();
        $quantity = Quantity::fromInt(10);
        $reservedAt = new \DateTimeImmutable('2025-01-01 12:00:00');
        $expiresAt = new \DateTimeImmutable('2025-01-01 12:15:00');
        $releasedAt = new \DateTimeImmutable('2025-01-01 12:10:00');

        $reservation = StockReservation::reconstituteFromPersistence(
            $reservationId,
            $stockItemId,
            $warehouseId,
            $tenantId,
            $quantity,
            $reservedAt,
            $expiresAt,
            true,
            $releasedAt
        );

        $this->assertTrue($reservation->isReleased());
        $this->assertSame($releasedAt, $reservation->releasedAt());
        $this->assertFalse($reservation->isExpired());
    }

    public function testReconstituteExpiredButNotReleasedReservation(): void
    {
        $reservationId = 'cart-expired';
        $stockItemId = StockItemId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::generate();
        $quantity = Quantity::fromInt(10);
        $reservedAt = new \DateTimeImmutable('2025-01-01 12:00:00');
        $expiresAt = new \DateTimeImmutable('2025-01-01 12:15:00');

        $reservation = StockReservation::reconstituteFromPersistence(
            $reservationId,
            $stockItemId,
            $warehouseId,
            $tenantId,
            $quantity,
            $reservedAt,
            $expiresAt,
            false,
            null
        );

        $this->assertFalse($reservation->isReleased());
        $this->assertNull($reservation->releasedAt());

        // Check if it's expired relative to a time after expiresAt
        $afterExpiry = new \DateTimeImmutable('2025-01-01 12:20:00');
        $this->assertTrue($reservation->isExpired($afterExpiry));
    }

    public function testCreateReservationWithLargeQuantity(): void
    {
        $largeQuantity = Quantity::fromInt(999999);

        $reservation = StockReservation::create(
            'cart-large',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            $largeQuantity
        );

        $this->assertEquals(999999, $reservation->quantity()->value());
    }

    public function testReservationExpiryTimeIsExactly15MinutesAfterCreation(): void
    {
        $reservation = StockReservation::create(
            'cart-123',
            StockItemId::generate(),
            WarehouseId::generate(),
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $expectedExpiryTime = $reservation->reservedAt()->modify('+15 minutes');

        $this->assertEquals(
            $expectedExpiryTime->getTimestamp(),
            $reservation->expiresAt()->getTimestamp()
        );
    }
}
