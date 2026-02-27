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
    }
}
