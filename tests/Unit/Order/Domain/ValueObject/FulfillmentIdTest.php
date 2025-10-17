<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\ValueObject;

use App\Order\Domain\ValueObject\FulfillmentId;
use PHPUnit\Framework\TestCase;

final class FulfillmentIdTest extends TestCase
{
    public function testCanGenerateNewId(): void
    {
        $id = FulfillmentId::generate();

        $this->assertInstanceOf(FulfillmentId::class, $id);
        $this->assertNotEmpty($id->toString());
        $this->assertSame(26, strlen($id->toString())); // ULID length
    }

    public function testCanCreateFromString(): void
    {
        $ulidString = '01HQXYZ1230000000000000000';
        $id = FulfillmentId::fromString($ulidString);

        $this->assertSame($ulidString, $id->toString());
    }

    public function testThrowsExceptionForInvalidUlid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FulfillmentId::fromString('invalid-ulid');
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $ulidString = '01HQXYZ1230000000000000000';
        $id1 = FulfillmentId::fromString($ulidString);
        $id2 = FulfillmentId::fromString($ulidString);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentId(): void
    {
        $id1 = FulfillmentId::generate();
        $id2 = FulfillmentId::generate();

        $this->assertFalse($id1->equals($id2));
    }

    public function testGeneratedIdsAreUnique(): void
    {
        $id1 = FulfillmentId::generate();
        $id2 = FulfillmentId::generate();

        $this->assertNotEquals($id1->toString(), $id2->toString());
    }
}
