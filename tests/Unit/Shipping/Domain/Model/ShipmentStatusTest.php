<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Domain\Model;

use App\Shipping\Domain\Model\ShipmentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShipmentStatusTest extends TestCase
{
    #[DataProvider('validStatusProvider')]
    public function testFromStringValid(string $value): void
    {
        $status = ShipmentStatus::fromString($value);
        $this->assertSame($value, $status->value());
    }

    /** @return iterable<string, array{string}> */
    public static function validStatusProvider(): iterable
    {
        yield 'pending' => ['pending'];
        yield 'dispatched' => ['dispatched'];
        yield 'in_transit' => ['in_transit'];
        yield 'delivered' => ['delivered'];
        yield 'cancelled' => ['cancelled'];
        yield 'failed' => ['failed'];
    }

    public function testRejectsInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ShipmentStatus::fromString('invalid');
    }

    public function testValidTransitionPendingToDispatched(): void
    {
        $this->assertTrue(ShipmentStatus::pending()->canTransitionTo(ShipmentStatus::dispatched()));
    }

    public function testValidTransitionPendingToCancelled(): void
    {
        $this->assertTrue(ShipmentStatus::pending()->canTransitionTo(ShipmentStatus::cancelled()));
    }

    public function testValidTransitionDispatchedToInTransit(): void
    {
        $this->assertTrue(ShipmentStatus::dispatched()->canTransitionTo(ShipmentStatus::inTransit()));
    }

    public function testValidTransitionDispatchedToDelivered(): void
    {
        $this->assertTrue(ShipmentStatus::dispatched()->canTransitionTo(ShipmentStatus::delivered()));
    }

    public function testValidTransitionInTransitToDelivered(): void
    {
        $this->assertTrue(ShipmentStatus::inTransit()->canTransitionTo(ShipmentStatus::delivered()));
    }

    public function testInvalidTransitionDeliveredToPending(): void
    {
        $this->assertFalse(ShipmentStatus::delivered()->canTransitionTo(ShipmentStatus::pending()));
    }

    public function testInvalidTransitionCancelledToDispatched(): void
    {
        $this->assertFalse(ShipmentStatus::cancelled()->canTransitionTo(ShipmentStatus::dispatched()));
    }

    public function testInvalidTransitionPendingToInTransit(): void
    {
        $this->assertFalse(ShipmentStatus::pending()->canTransitionTo(ShipmentStatus::inTransit()));
    }

    public function testIsFinal(): void
    {
        $this->assertTrue(ShipmentStatus::delivered()->isFinal());
        $this->assertTrue(ShipmentStatus::cancelled()->isFinal());
        $this->assertTrue(ShipmentStatus::failed()->isFinal());
        $this->assertFalse(ShipmentStatus::pending()->isFinal());
        $this->assertFalse(ShipmentStatus::dispatched()->isFinal());
        $this->assertFalse(ShipmentStatus::inTransit()->isFinal());
    }

    public function testEquals(): void
    {
        $this->assertTrue(ShipmentStatus::pending()->equals(ShipmentStatus::pending()));
        $this->assertFalse(ShipmentStatus::pending()->equals(ShipmentStatus::dispatched()));
    }

    public function testFactoryMethods(): void
    {
        $this->assertSame('pending', ShipmentStatus::pending()->value());
        $this->assertSame('dispatched', ShipmentStatus::dispatched()->value());
        $this->assertSame('in_transit', ShipmentStatus::inTransit()->value());
        $this->assertSame('delivered', ShipmentStatus::delivered()->value());
        $this->assertSame('cancelled', ShipmentStatus::cancelled()->value());
        $this->assertSame('failed', ShipmentStatus::failed()->value());
    }

    public function testToString(): void
    {
        $this->assertSame('pending', (string) ShipmentStatus::pending());
        $this->assertSame('dispatched', (string) ShipmentStatus::dispatched());
        $this->assertSame('in_transit', (string) ShipmentStatus::inTransit());
        $this->assertSame('delivered', (string) ShipmentStatus::delivered());
        $this->assertSame('cancelled', (string) ShipmentStatus::cancelled());
        $this->assertSame('failed', (string) ShipmentStatus::failed());
    }

    public function testIsPending(): void
    {
        $this->assertTrue(ShipmentStatus::pending()->isPending());
        $this->assertFalse(ShipmentStatus::dispatched()->isPending());
        $this->assertFalse(ShipmentStatus::inTransit()->isPending());
        $this->assertFalse(ShipmentStatus::delivered()->isPending());
        $this->assertFalse(ShipmentStatus::cancelled()->isPending());
        $this->assertFalse(ShipmentStatus::failed()->isPending());
    }

    public function testIsDispatched(): void
    {
        $this->assertFalse(ShipmentStatus::pending()->isDispatched());
        $this->assertTrue(ShipmentStatus::dispatched()->isDispatched());
        $this->assertFalse(ShipmentStatus::inTransit()->isDispatched());
        $this->assertFalse(ShipmentStatus::delivered()->isDispatched());
        $this->assertFalse(ShipmentStatus::cancelled()->isDispatched());
        $this->assertFalse(ShipmentStatus::failed()->isDispatched());
    }

    public function testIsInTransit(): void
    {
        $this->assertFalse(ShipmentStatus::pending()->isInTransit());
        $this->assertFalse(ShipmentStatus::dispatched()->isInTransit());
        $this->assertTrue(ShipmentStatus::inTransit()->isInTransit());
        $this->assertFalse(ShipmentStatus::delivered()->isInTransit());
        $this->assertFalse(ShipmentStatus::cancelled()->isInTransit());
        $this->assertFalse(ShipmentStatus::failed()->isInTransit());
    }

    public function testIsDelivered(): void
    {
        $this->assertFalse(ShipmentStatus::pending()->isDelivered());
        $this->assertFalse(ShipmentStatus::dispatched()->isDelivered());
        $this->assertFalse(ShipmentStatus::inTransit()->isDelivered());
        $this->assertTrue(ShipmentStatus::delivered()->isDelivered());
        $this->assertFalse(ShipmentStatus::cancelled()->isDelivered());
        $this->assertFalse(ShipmentStatus::failed()->isDelivered());
    }

    public function testIsCancelled(): void
    {
        $this->assertFalse(ShipmentStatus::pending()->isCancelled());
        $this->assertFalse(ShipmentStatus::dispatched()->isCancelled());
        $this->assertFalse(ShipmentStatus::inTransit()->isCancelled());
        $this->assertFalse(ShipmentStatus::delivered()->isCancelled());
        $this->assertTrue(ShipmentStatus::cancelled()->isCancelled());
        $this->assertFalse(ShipmentStatus::failed()->isCancelled());
    }

    public function testIsFailed(): void
    {
        $this->assertFalse(ShipmentStatus::pending()->isFailed());
        $this->assertFalse(ShipmentStatus::dispatched()->isFailed());
        $this->assertFalse(ShipmentStatus::inTransit()->isFailed());
        $this->assertFalse(ShipmentStatus::delivered()->isFailed());
        $this->assertFalse(ShipmentStatus::cancelled()->isFailed());
        $this->assertTrue(ShipmentStatus::failed()->isFailed());
    }

    public function testValidTransitionDispatchedToFailed(): void
    {
        $this->assertTrue(ShipmentStatus::dispatched()->canTransitionTo(ShipmentStatus::failed()));
    }

    public function testValidTransitionInTransitToFailed(): void
    {
        $this->assertTrue(ShipmentStatus::inTransit()->canTransitionTo(ShipmentStatus::failed()));
    }

    public function testInvalidTransitionPendingToDelivered(): void
    {
        $this->assertFalse(ShipmentStatus::pending()->canTransitionTo(ShipmentStatus::delivered()));
    }

    public function testInvalidTransitionFailedToPending(): void
    {
        $this->assertFalse(ShipmentStatus::failed()->canTransitionTo(ShipmentStatus::pending()));
    }

    public function testInvalidTransitionInTransitToCancelled(): void
    {
        $this->assertFalse(ShipmentStatus::inTransit()->canTransitionTo(ShipmentStatus::cancelled()));
    }

    public function testFromStringReturnsCorrectValue(): void
    {
        $this->assertSame(ShipmentStatus::PENDING, ShipmentStatus::fromString(ShipmentStatus::PENDING)->value());
        $this->assertSame(ShipmentStatus::DISPATCHED, ShipmentStatus::fromString(ShipmentStatus::DISPATCHED)->value());
        $this->assertSame(ShipmentStatus::IN_TRANSIT, ShipmentStatus::fromString(ShipmentStatus::IN_TRANSIT)->value());
        $this->assertSame(ShipmentStatus::DELIVERED, ShipmentStatus::fromString(ShipmentStatus::DELIVERED)->value());
        $this->assertSame(ShipmentStatus::CANCELLED, ShipmentStatus::fromString(ShipmentStatus::CANCELLED)->value());
        $this->assertSame(ShipmentStatus::FAILED, ShipmentStatus::fromString(ShipmentStatus::FAILED)->value());
    }

    public function testRejectsInvalidStatusWithHelpfulMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid shipment status/');

        ShipmentStatus::fromString('unknown_status');
    }
}
