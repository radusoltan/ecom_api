<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\Model;

use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\Reservation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reservation::class)]
final class ReservationTest extends TestCase
{
    // ---------------------------------------------------------------
    // Factory creation
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesReservationWithGeneratedTimestamps(): void
    {
        $before = new \DateTimeImmutable();

        $reservation = Reservation::create('cart-abc', Quantity::fromInt(5));

        $after = new \DateTimeImmutable();

        self::assertSame('cart-abc', $reservation->reservationId());
        self::assertSame(5, $reservation->quantity()->value());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $reservation->reservedAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $reservation->reservedAt()->getTimestamp());
    }

    #[Test]
    public function itCreatesReservationWithExplicitReservedAt(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');

        $reservation = Reservation::create('cart-xyz', Quantity::fromInt(10), $reservedAt);

        self::assertSame($reservedAt, $reservation->reservedAt());
    }

    #[Test]
    public function itSetsExpiryToExactly15MinutesAfterReservedAt(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');

        $reservation = Reservation::create('cart-xyz', Quantity::fromInt(10), $reservedAt);

        $expectedExpiry = new \DateTimeImmutable('2025-06-01 12:15:00');
        self::assertSame($expectedExpiry->getTimestamp(), $reservation->expiresAt()->getTimestamp());
    }

    #[Test]
    public function itStoresReservationId(): void
    {
        $reservation = Reservation::create('session-999', Quantity::fromInt(1));

        self::assertSame('session-999', $reservation->reservationId());
    }

    #[Test]
    public function itStoresQuantity(): void
    {
        $qty = Quantity::fromInt(42);

        $reservation = Reservation::create('cart-test', $qty);

        self::assertTrue($qty->equals($reservation->quantity()));
    }

    // ---------------------------------------------------------------
    // isExpired
    // ---------------------------------------------------------------

    #[Test]
    public function itIsNotExpiredImmediatelyAfterCreation(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');
        $reservation = Reservation::create('cart-1', Quantity::fromInt(3), $reservedAt);

        $now = new \DateTimeImmutable('2025-06-01 12:00:01');

        self::assertFalse($reservation->isExpired($now));
    }

    #[Test]
    public function itIsNotExpiredJustBeforeExpiryTime(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');
        $reservation = Reservation::create('cart-1', Quantity::fromInt(3), $reservedAt);

        $oneSecondBefore = new \DateTimeImmutable('2025-06-01 12:14:59');

        self::assertFalse($reservation->isExpired($oneSecondBefore));
    }

    #[Test]
    public function itIsExpiredAtExactExpiryTime(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');
        $reservation = Reservation::create('cart-1', Quantity::fromInt(3), $reservedAt);

        $exactExpiry = new \DateTimeImmutable('2025-06-01 12:15:00');

        self::assertTrue($reservation->isExpired($exactExpiry));
    }

    #[Test]
    public function itIsExpiredAfterExpiryTime(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');
        $reservation = Reservation::create('cart-1', Quantity::fromInt(3), $reservedAt);

        $afterExpiry = new \DateTimeImmutable('2025-06-01 12:30:00');

        self::assertTrue($reservation->isExpired($afterExpiry));
    }

    #[Test]
    public function itUsesCurrentTimeWhenNowIsOmitted(): void
    {
        $reservedAt = new \DateTimeImmutable('2000-01-01 00:00:00'); // Very old
        $reservation = Reservation::create('cart-old', Quantity::fromInt(1), $reservedAt);

        // Without passing $now, it defaults to new \DateTimeImmutable() — which is years after expiry
        self::assertTrue($reservation->isExpired());
    }

    #[Test]
    public function itIsNotExpiredWhenNowIsNullAndJustCreated(): void
    {
        $reservation = Reservation::create('cart-fresh', Quantity::fromInt(1));

        // Should not be expired immediately — no $now argument means 'now'
        self::assertFalse($reservation->isExpired());
    }

    // ---------------------------------------------------------------
    // equals
    // ---------------------------------------------------------------

    #[Test]
    public function itEqualsAnotherReservationWithSameId(): void
    {
        $r1 = Reservation::create('cart-same', Quantity::fromInt(5));
        $r2 = Reservation::create('cart-same', Quantity::fromInt(99)); // different quantity, same ID

        self::assertTrue($r1->equals($r2));
    }

    #[Test]
    public function itDoesNotEqualAnotherReservationWithDifferentId(): void
    {
        $r1 = Reservation::create('cart-A', Quantity::fromInt(5));
        $r2 = Reservation::create('cart-B', Quantity::fromInt(5));

        self::assertFalse($r1->equals($r2));
    }

    #[Test]
    public function itEqualsItself(): void
    {
        $r = Reservation::create('cart-self', Quantity::fromInt(7));

        self::assertTrue($r->equals($r));
    }

    // ---------------------------------------------------------------
    // extend
    // ---------------------------------------------------------------

    #[Test]
    public function itExtendsSetsNewExpiryTo15MinutesFromNow(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');
        $reservation = Reservation::create('cart-ext', Quantity::fromInt(4), $reservedAt);

        $extendedAt = new \DateTimeImmutable('2025-06-01 12:10:00');
        $extended = $reservation->extend($extendedAt);

        $expectedExpiry = new \DateTimeImmutable('2025-06-01 12:25:00');
        self::assertSame($expectedExpiry->getTimestamp(), $extended->expiresAt()->getTimestamp());
    }

    #[Test]
    public function itExtendPreservesOriginalReservedAt(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');
        $reservation = Reservation::create('cart-ext', Quantity::fromInt(4), $reservedAt);

        $extended = $reservation->extend(new \DateTimeImmutable('2025-06-01 12:10:00'));

        self::assertSame($reservedAt, $extended->reservedAt());
    }

    #[Test]
    public function itExtendPreservesReservationId(): void
    {
        $reservation = Reservation::create('cart-keep-id', Quantity::fromInt(2));
        $extended = $reservation->extend(new \DateTimeImmutable('+5 minutes'));

        self::assertSame('cart-keep-id', $extended->reservationId());
    }

    #[Test]
    public function itExtendPreservesQuantity(): void
    {
        $qty = Quantity::fromInt(15);
        $reservation = Reservation::create('cart-qty', $qty);
        $extended = $reservation->extend(new \DateTimeImmutable('+5 minutes'));

        self::assertTrue($qty->equals($extended->quantity()));
    }

    #[Test]
    public function itExtendReturnsNewInstanceNotMutatingOriginal(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');
        $reservation = Reservation::create('cart-immutable', Quantity::fromInt(1), $reservedAt);
        $originalExpiry = $reservation->expiresAt();

        $reservation->extend(new \DateTimeImmutable('2025-06-01 12:10:00'));

        // Original is unchanged since Reservation is readonly
        self::assertSame($originalExpiry->getTimestamp(), $reservation->expiresAt()->getTimestamp());
    }

    #[Test]
    public function itExtendUsesCurrentTimeWhenNowIsOmitted(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');
        $reservation = Reservation::create('cart-no-arg', Quantity::fromInt(1), $reservedAt);

        $before = new \DateTimeImmutable();
        $extended = $reservation->extend();
        $after = new \DateTimeImmutable();

        // New expiry should be ~15 minutes from "now"
        $minExpected = $before->getTimestamp() + (15 * 60);
        $maxExpected = $after->getTimestamp() + (15 * 60);
        self::assertGreaterThanOrEqual($minExpected, $extended->expiresAt()->getTimestamp());
        self::assertLessThanOrEqual($maxExpected, $extended->expiresAt()->getTimestamp());
    }

    #[Test]
    public function itExtendMakesExpiredReservationActiveAgain(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');
        $reservation = Reservation::create('cart-revive', Quantity::fromInt(1), $reservedAt);

        // Reservation expired at 12:15
        $expiredNow = new \DateTimeImmutable('2025-06-01 12:20:00');
        self::assertTrue($reservation->isExpired($expiredNow));

        // Extend from the future
        $extendedAt = new \DateTimeImmutable('2025-06-01 12:20:00');
        $extended = $reservation->extend($extendedAt);

        // New expiry is 12:35, still active at 12:20
        self::assertFalse($extended->isExpired($expiredNow));
    }

    #[Test]
    public function itCanBeExtendedMultipleTimes(): void
    {
        $reservedAt = new \DateTimeImmutable('2025-06-01 12:00:00');
        $r = Reservation::create('cart-multi', Quantity::fromInt(1), $reservedAt);

        $r2 = $r->extend(new \DateTimeImmutable('2025-06-01 12:10:00'));
        $r3 = $r2->extend(new \DateTimeImmutable('2025-06-01 12:22:00'));
        $r4 = $r3->extend(new \DateTimeImmutable('2025-06-01 12:35:00'));

        $expectedFinalExpiry = new \DateTimeImmutable('2025-06-01 12:50:00');
        self::assertSame($expectedFinalExpiry->getTimestamp(), $r4->expiresAt()->getTimestamp());
    }

    // ---------------------------------------------------------------
    // Zero quantity edge case
    // ---------------------------------------------------------------

    #[Test]
    public function itAcceptsZeroQuantity(): void
    {
        $reservation = Reservation::create('cart-zero', Quantity::zero());

        self::assertSame(0, $reservation->quantity()->value());
        self::assertTrue($reservation->quantity()->isZero());
    }

    // ---------------------------------------------------------------
    // Large quantity edge case
    // ---------------------------------------------------------------

    #[Test]
    public function itAcceptsMaxQuantity(): void
    {
        $reservation = Reservation::create('cart-max', Quantity::fromInt(999999));

        self::assertSame(999999, $reservation->quantity()->value());
    }
}
