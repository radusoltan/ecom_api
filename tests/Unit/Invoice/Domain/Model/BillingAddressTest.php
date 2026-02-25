<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Domain\Model;

use App\Invoice\Domain\Model\BillingAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BillingAddress::class)]
final class BillingAddressTest extends TestCase
{
    #[Test]
    public function createWithValidDataSucceeds(): void
    {
        $address = BillingAddress::create(
            'John Doe',
            '123 Main Street',
            'Suite 100',
            'New York',
            '10001',
            'US',
            'US123456789'
        );

        self::assertSame('John Doe', $address->name());
        self::assertSame('123 Main Street', $address->addressLine1());
        self::assertSame('Suite 100', $address->addressLine2());
        self::assertSame('New York', $address->city());
        self::assertSame('10001', $address->postalCode());
        self::assertSame('US', $address->country());
        self::assertSame('US123456789', $address->vatNumber());
    }

    #[Test]
    public function createTrimsWhitespace(): void
    {
        $address = BillingAddress::create(
            '  John Doe  ',
            '  123 Main Street  ',
            null,
            '  Berlin  ',
            ' 10115 ',
            ' de ',
        );

        self::assertSame('John Doe', $address->name());
        self::assertSame('123 Main Street', $address->addressLine1());
        self::assertSame('Berlin', $address->city());
        self::assertSame('10115', $address->postalCode());
        self::assertSame('DE', $address->country());
    }

    #[Test]
    public function createUppercasesCountry(): void
    {
        $address = BillingAddress::create('Name', '12345 Street', null, 'City', '12345', 'gb');
        self::assertSame('GB', $address->country());
    }

    #[Test]
    public function createWithoutVatNumber(): void
    {
        $address = BillingAddress::create('Name', '12345 Street', null, 'City', '12345', 'US');
        self::assertNull($address->vatNumber());
    }

    #[Test]
    public function createWithTooShortNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billing name');

        BillingAddress::create('J', '123 Main Street', null, 'City', '12345', 'US');
    }

    #[Test]
    public function createWithTooShortAddressThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address line 1');

        BillingAddress::create('John', '123', null, 'City', '12345', 'US');
    }

    #[Test]
    public function createWithTooShortCityThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('City');

        BillingAddress::create('John', '12345 Street', null, 'C', '12345', 'US');
    }

    #[Test]
    public function createWithTooShortPostalCodeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Postal code');

        BillingAddress::create('John', '12345 Street', null, 'City', '12', 'US');
    }

    #[Test]
    public function createWithInvalidCountryCodeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ISO 3166-1');

        BillingAddress::create('John', '12345 Street', null, 'City', '12345', 'USA');
    }

    #[Test]
    public function toArrayReturnsAllFields(): void
    {
        $address = BillingAddress::create('John', '12345 Street', 'Apt 2', 'City', '12345', 'US', 'VAT123');

        $array = $address->toArray();

        self::assertSame('John', $array['name']);
        self::assertSame('12345 Street', $array['addressLine1']);
        self::assertSame('Apt 2', $array['addressLine2']);
        self::assertSame('City', $array['city']);
        self::assertSame('12345', $array['postalCode']);
        self::assertSame('US', $array['country']);
        self::assertSame('VAT123', $array['vatNumber']);
    }

    #[Test]
    public function formatReturnsMultiLineString(): void
    {
        $address = BillingAddress::create('John Doe', '123 Main Street', null, 'New York', '10001', 'US');

        $formatted = $address->format();

        self::assertStringContainsString('John Doe', $formatted);
        self::assertStringContainsString('123 Main Street', $formatted);
        self::assertStringContainsString('10001, New York', $formatted);
        self::assertStringContainsString('US', $formatted);
    }

    #[Test]
    public function formatIncludesVatNumberWhenPresent(): void
    {
        $address = BillingAddress::create('Corp', '12345 Street', null, 'Berlin', '10115', 'DE', 'DE123456789');

        self::assertStringContainsString('VAT: DE123456789', $address->format());
    }

    #[Test]
    public function equalsReturnsTrueForIdenticalAddresses(): void
    {
        $a = BillingAddress::create('John', '12345 Street', null, 'City', '12345', 'US');
        $b = BillingAddress::create('John', '12345 Street', null, 'City', '12345', 'US');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentAddresses(): void
    {
        $a = BillingAddress::create('John', '12345 Street', null, 'City', '12345', 'US');
        $b = BillingAddress::create('Jane', '12345 Street', null, 'City', '12345', 'US');

        self::assertFalse($a->equals($b));
    }
}
