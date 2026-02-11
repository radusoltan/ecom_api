<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\AddressType;
use PHPUnit\Framework\TestCase;

final class AddressTypeTest extends TestCase
{
    public function testShippingType(): void
    {
        $type = AddressType::SHIPPING;

        $this->assertSame('shipping', $type->value);
        $this->assertSame('shipping', $type->toString());
        $this->assertTrue($type->isShipping());
        $this->assertFalse($type->isBilling());
    }

    public function testBillingType(): void
    {
        $type = AddressType::BILLING;

        $this->assertSame('billing', $type->value);
        $this->assertSame('billing', $type->toString());
        $this->assertFalse($type->isShipping());
        $this->assertTrue($type->isBilling());
    }

    public function testBothType(): void
    {
        $type = AddressType::BOTH;

        $this->assertSame('both', $type->value);
        $this->assertSame('both', $type->toString());
        $this->assertTrue($type->isShipping());
        $this->assertTrue($type->isBilling());
    }

    public function testToString(): void
    {
        $this->assertSame('shipping', AddressType::SHIPPING->toString());
        $this->assertSame('billing', AddressType::BILLING->toString());
        $this->assertSame('both', AddressType::BOTH->toString());
    }

    public function testFromStringWithShipping(): void
    {
        $type = AddressType::fromString('shipping');

        $this->assertSame(AddressType::SHIPPING, $type);
    }

    public function testFromStringWithBilling(): void
    {
        $type = AddressType::fromString('billing');

        $this->assertSame(AddressType::BILLING, $type);
    }

    public function testFromStringWithBoth(): void
    {
        $type = AddressType::fromString('both');

        $this->assertSame(AddressType::BOTH, $type);
    }

    public function testFromStringCaseInsensitive(): void
    {
        $this->assertSame(AddressType::SHIPPING, AddressType::fromString('SHIPPING'));
        $this->assertSame(AddressType::BILLING, AddressType::fromString('BILLING'));
        $this->assertSame(AddressType::BOTH, AddressType::fromString('BOTH'));
        $this->assertSame(AddressType::SHIPPING, AddressType::fromString('Shipping'));
    }

    public function testFromStringInvalidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid address type: "invalid". Allowed: shipping, billing, both');

        AddressType::fromString('invalid');
    }

    public function testFromStringEmptyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid address type');

        AddressType::fromString('');
    }
}
