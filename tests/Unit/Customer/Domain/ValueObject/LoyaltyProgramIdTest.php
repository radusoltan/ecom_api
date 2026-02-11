<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class LoyaltyProgramIdTest extends TestCase
{
    public function testGenerate(): void
    {
        $id = LoyaltyProgramId::generate();

        $this->assertInstanceOf(LoyaltyProgramId::class, $id);
        $this->assertTrue(Uuid::isValid($id->toString()));
    }

    public function testFromString(): void
    {
        $uuid = Uuid::v7()->toRfc4122();
        $id = LoyaltyProgramId::fromString($uuid);

        $this->assertSame($uuid, $id->toString());
    }

    public function testFromStringInvalidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid loyalty program ID: "invalid-uuid"');

        LoyaltyProgramId::fromString('invalid-uuid');
    }

    public function testToString(): void
    {
        $uuid = Uuid::v7()->toRfc4122();
        $id = LoyaltyProgramId::fromString($uuid);

        $this->assertSame($uuid, $id->toString());
        $this->assertSame($uuid, (string) $id);
    }

    public function testEquals(): void
    {
        $uuid = Uuid::v7()->toRfc4122();
        $id1 = LoyaltyProgramId::fromString($uuid);
        $id2 = LoyaltyProgramId::fromString($uuid);

        $this->assertTrue($id1->equals($id2));
    }

    public function testNotEquals(): void
    {
        $id1 = LoyaltyProgramId::generate();
        $id2 = LoyaltyProgramId::generate();

        $this->assertFalse($id1->equals($id2));
    }
}
