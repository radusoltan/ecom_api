<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Domain\Model;

use App\Shipping\Domain\Model\ShipmentId;
use PHPUnit\Framework\TestCase;

final class ShipmentIdTest extends TestCase
{
    public function testGenerate(): void
    {
        $id = ShipmentId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->toString()
        );
    }

    public function testFromString(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id = ShipmentId::fromString($uuid);

        $this->assertSame($uuid, $id->toString());
    }

    public function testRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ShipmentId::fromString('');
    }

    public function testRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ShipmentId::fromString('not-a-uuid');
    }

    public function testEquals(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id1 = ShipmentId::fromString($uuid);
        $id2 = ShipmentId::fromString($uuid);

        $this->assertTrue($id1->equals($id2));
    }

    public function testNotEquals(): void
    {
        $id1 = ShipmentId::generate();
        $id2 = ShipmentId::generate();

        $this->assertFalse($id1->equals($id2));
    }

    public function testToString(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id = ShipmentId::fromString($uuid);

        $this->assertSame($uuid, (string) $id);
    }
}
