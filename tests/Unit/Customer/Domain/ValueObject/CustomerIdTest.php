<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\CustomerId;
use PHPUnit\Framework\TestCase;

final class CustomerIdTest extends TestCase
{
    public function testGenerateCreatesValidUuidV4(): void
    {
        $customerId = CustomerId::generate();

        $uuid = $customerId->toString();

        // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    public function testGenerateCreatesUniqueIds(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; ++$i) {
            $ids[] = CustomerId::generate()->toString();
        }

        $uniqueIds = array_unique($ids);

        self::assertCount(100, $uniqueIds, 'All generated IDs should be unique');
    }

    public function testFromStringWithValidUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $customerId = CustomerId::fromString($uuid);

        self::assertEquals($uuid, $customerId->toString());
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CustomerId format:');

        CustomerId::fromString('not-a-valid-uuid');
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CustomerId cannot be empty');

        CustomerId::fromString('');
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $customerId1 = CustomerId::fromString($uuid);
        $customerId2 = CustomerId::fromString($uuid);

        self::assertTrue($customerId1->equals($customerId2));
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        $customerId1 = CustomerId::generate();
        $customerId2 = CustomerId::generate();

        self::assertFalse($customerId1->equals($customerId2));
    }

    public function testToStringReturnsOriginalUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $customerId = CustomerId::fromString($uuid);

        self::assertEquals($uuid, $customerId->toString());
        self::assertEquals($uuid, (string) $customerId);
    }
}
