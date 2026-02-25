<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Domain\ValueObject;

use App\Returns\Domain\ValueObject\ReturnRequestId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ReturnRequestIdTest extends TestCase
{
    public function testGenerateCreatesValidUuid(): void
    {
        $id = ReturnRequestId::generate();

        $this->assertInstanceOf(ReturnRequestId::class, $id);
        $this->assertTrue(Uuid::isValid($id->toString()));
    }

    public function testGenerateCreatesUniqueIds(): void
    {
        $id1 = ReturnRequestId::generate();
        $id2 = ReturnRequestId::generate();

        $this->assertNotEquals($id1->toString(), $id2->toString());
    }

    public function testFromStringWithValidUuid(): void
    {
        $uuidString = (string) Uuid::v7();
        $id = ReturnRequestId::fromString($uuidString);

        $this->assertInstanceOf(ReturnRequestId::class, $id);
        $this->assertEquals($uuidString, $id->toString());
    }

    public function testFromStringThrowsExceptionForInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ReturnRequestId');

        ReturnRequestId::fromString('invalid-uuid');
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ReturnRequestId');

        ReturnRequestId::fromString('');
    }

    public function testFromStringThrowsExceptionForUlid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ReturnRequestId');

        // ULID is not a valid UUID
        ReturnRequestId::fromString('01HQZT9P5KZGN9FW2C3X8Y5HZE');
    }

    public function testToStringReturnsOriginalValue(): void
    {
        $uuidString = (string) Uuid::v7();
        $id = ReturnRequestId::fromString($uuidString);

        $this->assertEquals($uuidString, $id->toString());
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $uuidString = (string) Uuid::v7();
        $id1 = ReturnRequestId::fromString($uuidString);
        $id2 = ReturnRequestId::fromString($uuidString);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $id1 = ReturnRequestId::generate();
        $id2 = ReturnRequestId::generate();

        $this->assertFalse($id1->equals($id2));
    }

    public function testUuidV7PreservesTimeOrdering(): void
    {
        $id1 = ReturnRequestId::generate();
        usleep(1000); // Wait 1ms to ensure different timestamp
        $id2 = ReturnRequestId::generate();

        // UUID v7 should be time-ordered, so id1 < id2
        $this->assertLessThan($id2->toString(), $id1->toString());
    }

    public function testFromStringAcceptsLowercaseUuid(): void
    {
        $uuid = Uuid::v7();
        $lowercaseUuid = strtolower((string) $uuid);

        $id = ReturnRequestId::fromString($lowercaseUuid);

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

        ReturnRequestId::fromString('  550e8400-e29b-41d4-a716-446655440000  ');
    }

    public function testFromStringWithSpecialCharactersThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ReturnRequestId::fromString('not-a-valid-uuid-format!');
    }
}
