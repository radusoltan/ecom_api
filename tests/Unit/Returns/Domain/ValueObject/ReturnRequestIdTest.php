<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Domain\ValueObject;

use App\Returns\Domain\ValueObject\ReturnRequestId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ReturnRequestIdTest extends TestCase
{
    public function testGenerateCreatesValidUlid(): void
    {
        $id = ReturnRequestId::generate();

        $this->assertInstanceOf(ReturnRequestId::class, $id);
        $this->assertTrue(Ulid::isValid($id->toString()));
    }

    public function testGenerateCreatesUniqueIds(): void
    {
        $id1 = ReturnRequestId::generate();
        $id2 = ReturnRequestId::generate();

        $this->assertNotEquals($id1->toString(), $id2->toString());
    }

    public function testFromStringWithValidUlid(): void
    {
        $ulidString = (string) new Ulid();
        $id = ReturnRequestId::fromString($ulidString);

        $this->assertInstanceOf(ReturnRequestId::class, $id);
        $this->assertEquals($ulidString, $id->toString());
    }

    public function testFromStringThrowsExceptionForInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ReturnRequestId');

        ReturnRequestId::fromString('invalid-ulid');
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ReturnRequestId');

        ReturnRequestId::fromString('');
    }

    public function testFromStringThrowsExceptionForUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ReturnRequestId');

        // UUID v4 is not a valid ULID
        ReturnRequestId::fromString('550e8400-e29b-41d4-a716-446655440000');
    }

    public function testToStringReturnsOriginalValue(): void
    {
        $ulidString = (string) new Ulid();
        $id = ReturnRequestId::fromString($ulidString);

        $this->assertEquals($ulidString, $id->toString());
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $ulidString = (string) new Ulid();
        $id1 = ReturnRequestId::fromString($ulidString);
        $id2 = ReturnRequestId::fromString($ulidString);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $id1 = ReturnRequestId::generate();
        $id2 = ReturnRequestId::generate();

        $this->assertFalse($id1->equals($id2));
    }

    public function testUlidFormatPreservesTimestamp(): void
    {
        $id1 = ReturnRequestId::generate();
        usleep(1000); // Wait 1ms to ensure different timestamp
        $id2 = ReturnRequestId::generate();

        // ULIDs should be lexicographically sortable, so id1 < id2
        $this->assertLessThan($id2->toString(), $id1->toString());
    }

    public function testFromStringAcceptsUppercaseUlid(): void
    {
        $ulid = new Ulid();
        $uppercaseUlid = strtoupper($ulid->toBase32());

        $id = ReturnRequestId::fromString($uppercaseUlid);

        $this->assertInstanceOf(ReturnRequestId::class, $id);
        $this->assertEquals($uppercaseUlid, $id->toString());
    }

    public function testFromStringAcceptsLowercaseUlid(): void
    {
        $ulid = new Ulid();
        $lowercaseUlid = strtolower($ulid->toBase32());

        $id = ReturnRequestId::fromString($lowercaseUlid);

        $this->assertInstanceOf(ReturnRequestId::class, $id);
    }

    public function testMultipleGenerationsAreUnique(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; ++$i) {
            $ids[] = ReturnRequestId::generate()->toString();
        }

        $uniqueIds = array_unique($ids);

        $this->assertCount(100, $uniqueIds, 'All generated IDs should be unique');
    }

    public function testFromStringWithWhitespaceThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReturnRequestId::fromString('  01HQZT9P5KZGN9FW2C3X8Y5HZE  ');
    }

    public function testFromStringWithSpecialCharactersThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReturnRequestId::fromString('01HQZT9P5K-ZGN9FW2C3X8Y5HZE');
    }
}
