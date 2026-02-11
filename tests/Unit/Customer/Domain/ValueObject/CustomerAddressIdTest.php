<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\CustomerAddressId;
use PHPUnit\Framework\TestCase;

final class CustomerAddressIdTest extends TestCase
{
    public function testGenerateCreatesValidId(): void
    {
        $id = CustomerAddressId::generate();

        $this->assertInstanceOf(CustomerAddressId::class, $id);
        $this->assertNotEmpty($id->toString());
    }

    public function testGenerateCreatesUniqueIds(): void
    {
        $id1 = CustomerAddressId::generate();
        $id2 = CustomerAddressId::generate();

        $this->assertNotEquals($id1->toString(), $id2->toString());
        $this->assertFalse($id1->equals($id2));
    }

    public function testFromStringCreatesValidId(): void
    {
        $uuid = '018c4e32-7f3e-7a1a-9f8e-0242ac120002';
        $id = CustomerAddressId::fromString($uuid);

        $this->assertInstanceOf(CustomerAddressId::class, $id);
        $this->assertSame($uuid, $id->toString());
    }

    public function testFromStringWithInvalidUuidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid customer address ID');

        CustomerAddressId::fromString('invalid-uuid');
    }

    public function testFromStringWithEmptyStringThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid customer address ID');

        CustomerAddressId::fromString('');
    }

    public function testToStringReturnsUuidString(): void
    {
        $uuid = '018c4e32-7f3e-7a1a-9f8e-0242ac120002';
        $id = CustomerAddressId::fromString($uuid);

        $this->assertSame($uuid, $id->toString());
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $uuid = '018c4e32-7f3e-7a1a-9f8e-0242ac120002';
        $id1 = CustomerAddressId::fromString($uuid);
        $id2 = CustomerAddressId::fromString($uuid);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentId(): void
    {
        $id1 = CustomerAddressId::fromString('018c4e32-7f3e-7a1a-9f8e-0242ac120002');
        $id2 = CustomerAddressId::fromString('018c4e32-7f3e-7a1a-9f8e-0242ac120003');

        $this->assertFalse($id1->equals($id2));
    }

    public function testMagicToStringReturnsUuidString(): void
    {
        $uuid = '018c4e32-7f3e-7a1a-9f8e-0242ac120002';
        $id = CustomerAddressId::fromString($uuid);

        $this->assertSame($uuid, (string) $id);
    }

    public function testIsValidUuidFormat(): void
    {
        $id = CustomerAddressId::generate();
        $uuidString = $id->toString();

        // UUID v7 format: xxxxxxxx-xxxx-7xxx-xxxx-xxxxxxxxxxxx
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuidString
        );
    }
}
