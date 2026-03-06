<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Address::class)]
final class AddressTest extends TestCase
{
    // ---------------------------------------------------------------
    // Creation
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesShippingAddressWithAllFields(): void
    {
        $address = Address::create(
            street: '123 Rue de la Paix',
            city: 'Paris',
            postalCode: '75001',
            country: 'FR',
            state: 'Ile-de-France',
            type: Address::TYPE_SHIPPING,
            label: 'Home',
        );

        self::assertSame('123 Rue de la Paix', $address->street);
        self::assertSame('Paris', $address->city);
        self::assertSame('75001', $address->postalCode);
        self::assertSame('FR', $address->country);
        self::assertSame('Ile-de-France', $address->state);
        self::assertSame(Address::TYPE_SHIPPING, $address->type);
        self::assertSame('Home', $address->label);
    }

    #[Test]
    public function itCreatesAddressWithOptionalFieldsNull(): void
    {
        $address = Address::create(
            street: '10 High St',
            city: 'London',
            postalCode: 'SW1A 1AA',
            country: 'GB',
        );

        self::assertNull($address->state);
        self::assertNull($address->label);
        self::assertSame(Address::TYPE_SHIPPING, $address->type);
    }

    #[Test]
    public function itCreatesBillingAddress(): void
    {
        $address = Address::create(
            street: '5 Avenue Foch',
            city: 'Lyon',
            postalCode: '69001',
            country: 'FR',
            type: Address::TYPE_BILLING,
        );

        self::assertSame(Address::TYPE_BILLING, $address->type);
        self::assertTrue($address->isBilling());
        self::assertFalse($address->isShipping());
    }

    #[Test]
    public function itDefaultsToShippingType(): void
    {
        $address = Address::create(
            street: '1 Main St',
            city: 'Berlin',
            postalCode: '10115',
            country: 'DE',
        );

        self::assertTrue($address->isShipping());
        self::assertFalse($address->isBilling());
    }

    // ---------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenStreetIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Street address is required');

        Address::create(street: '   ', city: 'Paris', postalCode: '75001', country: 'FR');
    }

    #[Test]
    public function itThrowsWhenCityIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('City is required');

        Address::create(street: '10 Main St', city: '', postalCode: '75001', country: 'FR');
    }

    #[Test]
    public function itThrowsWhenPostalCodeIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Postal code is required');

        Address::create(street: '10 Main St', city: 'Paris', postalCode: '  ', country: 'FR');
    }

    #[Test]
    public function itThrowsWhenCountryIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Country is required');

        Address::create(street: '10 Main St', city: 'Paris', postalCode: '75001', country: '');
    }

    #[Test]
    public function itThrowsWhenTypeIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid address type: warehouse');

        Address::create(
            street: '10 Main St',
            city: 'Paris',
            postalCode: '75001',
            country: 'FR',
            type: 'warehouse',
        );
    }

    // ---------------------------------------------------------------
    // Equality
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsTrueForAddressesWithSameContent(): void
    {
        $a = Address::create('10 Main St', 'Paris', '75001', 'FR', 'IDF');
        $b = Address::create('10 Main St', 'Paris', '75001', 'FR', 'IDF');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itReturnsFalseForAddressesWithDifferentStreet(): void
    {
        $a = Address::create('10 Main St', 'Paris', '75001', 'FR');
        $b = Address::create('20 Main St', 'Paris', '75001', 'FR');

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itReturnsFalseForAddressesWithDifferentCity(): void
    {
        $a = Address::create('10 Main St', 'Paris', '75001', 'FR');
        $b = Address::create('10 Main St', 'Lyon', '75001', 'FR');

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itReturnsFalseForAddressesWithDifferentCountry(): void
    {
        $a = Address::create('10 Main St', 'Paris', '75001', 'FR');
        $b = Address::create('10 Main St', 'Paris', '75001', 'DE');

        self::assertFalse($a->equals($b));
    }

    // ---------------------------------------------------------------
    // toArray
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsArrayRepresentation(): void
    {
        $address = Address::create(
            street: '5 Oak Ave',
            city: 'Rome',
            postalCode: '00100',
            country: 'IT',
            state: 'Lazio',
            type: Address::TYPE_BILLING,
            label: 'Office',
        );

        $array = $address->toArray();

        self::assertSame('5 Oak Ave', $array['street']);
        self::assertSame('Rome', $array['city']);
        self::assertSame('00100', $array['postalCode']);
        self::assertSame('IT', $array['country']);
        self::assertSame('Lazio', $array['state']);
        self::assertSame(Address::TYPE_BILLING, $array['type']);
        self::assertSame('Office', $array['label']);
    }

    #[Test]
    public function itIncludesNullsForOptionalFieldsInArray(): void
    {
        $address = Address::create('1 Elm St', 'Madrid', '28001', 'ES');

        $array = $address->toArray();

        self::assertNull($array['state']);
        self::assertNull($array['label']);
    }

    // ---------------------------------------------------------------
    // Constants
    // ---------------------------------------------------------------

    #[Test]
    public function itDefinesExpectedTypeConstants(): void
    {
        self::assertSame('billing', Address::TYPE_BILLING);
        self::assertSame('shipping', Address::TYPE_SHIPPING);
    }
}
