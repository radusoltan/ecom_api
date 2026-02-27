<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Domain\ValueObject;

use App\Shipping\Domain\ValueObject\TrackingNumber;
use PHPUnit\Framework\TestCase;

final class TrackingNumberTest extends TestCase
{
    public function testCreateValid(): void
    {
        $tracking = TrackingNumber::fromString('1Z999AA10123456784');
        $this->assertSame('1Z999AA10123456784', $tracking->toString());
    }

    public function testTrimsWhitespace(): void
    {
        $tracking = TrackingNumber::fromString('  ABC123  ');
        $this->assertSame('ABC123', $tracking->toString());
    }

    public function testRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tracking number cannot be empty');
        TrackingNumber::fromString('');
    }

    public function testRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TrackingNumber::fromString('   ');
    }

    public function testRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tracking number cannot exceed 100 characters');
        TrackingNumber::fromString(str_repeat('A', 101));
    }

    public function testEquals(): void
    {
        $t1 = TrackingNumber::fromString('ABC123');
        $t2 = TrackingNumber::fromString('ABC123');
        $t3 = TrackingNumber::fromString('XYZ789');

        $this->assertTrue($t1->equals($t2));
        $this->assertFalse($t1->equals($t3));
    }

    public function testToString(): void
    {
        $tracking = TrackingNumber::fromString('TRACK-001');
        $this->assertSame('TRACK-001', (string) $tracking);
    }
}
