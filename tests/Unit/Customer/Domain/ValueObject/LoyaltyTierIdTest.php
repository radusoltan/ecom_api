<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\LoyaltyTierId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class LoyaltyTierIdTest extends TestCase
{
    public function testGenerate(): void
    {
        $id = LoyaltyTierId::generate();

        $this->assertInstanceOf(LoyaltyTierId::class, $id);
        $this->assertTrue(Uuid::isValid($id->toString()));
    }

    public function testFromString(): void
    {
        $uuid = Uuid::v7()->toRfc4122();
        $id = LoyaltyTierId::fromString($uuid);

        $this->assertSame($uuid, $id->toString());
    }

    public function testFromStringInvalidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid loyalty tier ID: "invalid-uuid"');

        LoyaltyTierId::fromString('invalid-uuid');
    }

    public function testToString(): void
    {
        $uuid = Uuid::v7()->toRfc4122();
        $id = LoyaltyTierId::fromString($uuid);

        $this->assertSame($uuid, $id->toString());
        $this->assertSame($uuid, (string) $id);
    }

    public function testEquals(): void
    {
        $uuid = Uuid::v7()->toRfc4122();
        $id1 = LoyaltyTierId::fromString($uuid);
        $id2 = LoyaltyTierId::fromString($uuid);

        $this->assertTrue($id1->equals($id2));
    }

    public function testNotEquals(): void
    {
        $id1 = LoyaltyTierId::generate();
        $id2 = LoyaltyTierId::generate();

        $this->assertFalse($id1->equals($id2));
    }
}
