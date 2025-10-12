<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\StockReservation;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class StockReservationTest extends TestCase
{
    public function testCreateReservation(): void
    {
        $reservationId = 'cart-123';
        $stockItemId = StockItemId::generate();
        $tenantId = TenantId::generate();
        $quantity = Quantity::fromInt(10);

        $reservation = StockReservation::create($reservationId, $stockItemId, $tenantId, $quantity);

        $this->assertEquals($reservationId, $reservation->reservationId());
        $this->assertTrue($reservation->stockItemId()->equals($stockItemId));
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
            TenantId::generate(),
            Quantity::fromInt(10)
        );

        $reservation->release();

        // Even after expiry time, released reservation should not be considered "expired"
        $afterExpiryTime = $reservation->expiresAt()->modify('+1 hour');
        $this->assertFalse($reservation->isExpired($afterExpiryTime));
    }
}
