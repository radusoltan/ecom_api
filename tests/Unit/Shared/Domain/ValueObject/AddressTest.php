<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Address::class)]
final class AddressTest extends TestCase
{
    // -------
    // create()
    // -------

    #[Test]
    public function itCreatesAddressWithValidData(): void
    {
        $address = Address::create(
            street: '123 Main St',
            city: 'Springfield',
            state: 'IL',
            postalCode: '62701',
            country: 'US',
        );

        self::assertSame('123 Main St', $address->street());
        self::assertSame('Springfield', $address->city());
        self::assertSame('IL', $address->state());
        self::assertSame('62701', $address->postalCode());
        self::assertSame('US', $address->country());
    }

    #[Test]
    public function itThrowsWhenStreetIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('street cannot be empty');

        Address::create(
            street: '',
            city: 'Springfield',
            state: 'IL',
            postalCode: '62701',
            country: 'US',
        );
    }

    #[Test]
    public function itThrowsWhenStreetIsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('street cannot be empty');

        Address::create(
            street: '   ',
            city: 'Springfield',
            state: 'IL',
            postalCode: '62701',
            country: 'US',
        );
    }

    #[Test]
    public function itThrowsWhenCityIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('city cannot be empty');

        Address::create(
            street: '123 Main St',
            city: '',
            state: 'IL',
            postalCode: '62701',
            country: 'US',
        );
    }

    #[Test]
    public function itThrowsWhenPostalCodeIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('postal code cannot be empty');

        Address::create(
            street: '123 Main St',
            city: 'Springfield',
            state: 'IL',
            postalCode: '',
            country: 'US',
        );
    }

    #[Test]
    public function itThrowsWhenCountryIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('country cannot be empty');

        Address::create(
            street: '123 Main St',
            city: 'Springfield',
            state: 'IL',
            postalCode: '62701',
            country: '',
        );
    }

    #[Test]
    public function itAllowsEmptyState(): void
    {
        $address = Address::create(
            street: '123 Main St',
            city: 'London',
            state: '',
            postalCode: 'SW1A 1AA',
            country: 'GB',
        );

        self::assertSame('', $address->state());
    }

    // -------
    // fromArray()
    // -------

    #[Test]
    public function itCreatesAddressFromArray(): void
    {
        $address = Address::fromArray([
            'street' => '10 Downing St',
            'city' => 'London',
            'state' => '',
            'postalCode' => 'SW1A 2AA',
            'country' => 'GB',
        ]);

        self::assertSame('10 Downing St', $address->street());
        self::assertSame('London', $address->city());
        self::assertSame('SW1A 2AA', $address->postalCode());
        self::assertSame('GB', $address->country());
    }

    #[Test]
    public function fromArrayUsesEmptyStringForMissingKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('street cannot be empty');

        Address::fromArray([
            'city' => 'London',
            'postalCode' => 'SW1A 2AA',
            'country' => 'GB',
        ]);
    }

    // -------
    // equals()
    // -------

    #[Test]
    public function itEqualsSameAddress(): void
    {
        $a1 = Address::create('123 Main St', 'Springfield', 'IL', '62701', 'US');
        $a2 = Address::create('123 Main St', 'Springfield', 'IL', '62701', 'US');

        self::assertTrue($a1->equals($a2));
    }

    #[Test]
    public function itDoesNotEqualDifferentStreet(): void
    {
        $a1 = Address::create('123 Main St', 'Springfield', 'IL', '62701', 'US');
        $a2 = Address::create('456 Oak Ave', 'Springfield', 'IL', '62701', 'US');

        self::assertFalse($a1->equals($a2));
    }

    #[Test]
    public function itDoesNotEqualDifferentCity(): void
    {
        $a1 = Address::create('123 Main St', 'Springfield', 'IL', '62701', 'US');
        $a2 = Address::create('123 Main St', 'Chicago', 'IL', '62701', 'US');

        self::assertFalse($a1->equals($a2));
    }

    #[Test]
    public function itDoesNotEqualDifferentPostalCode(): void
    {
        $a1 = Address::create('123 Main St', 'Springfield', 'IL', '62701', 'US');
        $a2 = Address::create('123 Main St', 'Springfield', 'IL', '99999', 'US');

        self::assertFalse($a1->equals($a2));
    }

    #[Test]
    public function itDoesNotEqualDifferentCountry(): void
    {
        $a1 = Address::create('123 Main St', 'Springfield', 'IL', '62701', 'US');
        $a2 = Address::create('123 Main St', 'Springfield', 'IL', '62701', 'CA');

        self::assertFalse($a1->equals($a2));
    }

    // -------
    // toString() / __toString()
    // -------

    #[Test]
    public function toStringFormatsCorrectly(): void
    {
        $address = Address::create('123 Main St', 'Springfield', 'IL', '62701', 'US');

        self::assertSame('123 Main St, Springfield, IL 62701, US', $address->toString());
    }

    #[Test]
    public function castToStringIsEquivalentToToString(): void
    {
        $address = Address::create('123 Main St', 'Springfield', 'IL', '62701', 'US');

        self::assertSame($address->toString(), (string) $address);
    }

    #[Test]
    public function toStringWithEmptyStateFormatsCorrectly(): void
    {
        $address = Address::create('10 Downing St', 'London', '', 'SW1A 2AA', 'GB');

        self::assertSame('10 Downing St, London,  SW1A 2AA, GB', $address->toString());
    }

    // -------
    // Accessors
    // -------

    #[Test]
    public function stateAccessorReturnsValue(): void
    {
        $address = Address::create('1 Rue de la Paix', 'Paris', 'Île-de-France', '75001', 'FR');

        self::assertSame('Île-de-France', $address->state());
    }
}
