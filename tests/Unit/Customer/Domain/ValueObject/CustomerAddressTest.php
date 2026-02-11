<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use PHPUnit\Framework\TestCase;

final class CustomerAddressTest extends TestCase
{
    public function testCreateWithValidAddress(): void
    {
        $id = CustomerAddressId::generate();
        $address = CustomerAddress::create(
            id: $id,
            street: '123 Main St',
            street2: 'Apt 4B',
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );

        $this->assertTrue($address->id->equals($id));
        $this->assertSame('123 Main St', $address->street);
        $this->assertSame('Apt 4B', $address->street2);
        $this->assertSame('New York', $address->city);
        $this->assertSame('NY', $address->state);
        $this->assertSame('10001', $address->postalCode);
        $this->assertSame('US', $address->country);
        $this->assertSame(AddressType::SHIPPING, $address->type);
        $this->assertFalse($address->isDefaultShipping);
        $this->assertFalse($address->isDefaultBilling);
    }

    public function testCreateWithAllFields(): void
    {
        $id = CustomerAddressId::generate();
        $address = CustomerAddress::create(
            id: $id,
            street: '456 Oak Avenue',
            street2: 'Suite 200',
            city: 'Los Angeles',
            state: 'CA',
            postalCode: '90001',
            country: 'us',
            type: AddressType::BOTH,
            isDefaultShipping: true,
            isDefaultBilling: true
        );

        $this->assertSame('456 Oak Avenue', $address->street);
        $this->assertSame('Suite 200', $address->street2);
        $this->assertSame('Los Angeles', $address->city);
        $this->assertSame('CA', $address->state);
        $this->assertSame('90001', $address->postalCode);
        $this->assertSame('US', $address->country); // Uppercased
        $this->assertSame(AddressType::BOTH, $address->type);
        $this->assertTrue($address->isDefaultShipping);
        $this->assertTrue($address->isDefaultBilling);
    }

    public function testCreateWithMinimalFields(): void
    {
        $id = CustomerAddressId::generate();
        $address = CustomerAddress::create(
            id: $id,
            street: '789 Elm St',
            street2: null,
            city: 'Chicago',
            state: null,
            postalCode: '60601',
            country: 'US',
            type: AddressType::BILLING
        );

        $this->assertSame('789 Elm St', $address->street);
        $this->assertNull($address->street2);
        $this->assertSame('Chicago', $address->city);
        $this->assertNull($address->state);
        $this->assertSame('60601', $address->postalCode);
        $this->assertSame('US', $address->country);
    }

    public function testCreateTrimsWhitespace(): void
    {
        $id = CustomerAddressId::generate();
        $address = CustomerAddress::create(
            id: $id,
            street: '  123 Main St  ',
            street2: '  Apt 4B  ',
            city: '  New York  ',
            state: '  NY  ',
            postalCode: '  10001  ',
            country: '  us  ',
            type: AddressType::SHIPPING
        );

        $this->assertSame('123 Main St', $address->street);
        $this->assertSame('Apt 4B', $address->street2);
        $this->assertSame('New York', $address->city);
        $this->assertSame('NY', $address->state);
        $this->assertSame('10001', $address->postalCode);
        $this->assertSame('US', $address->country);
    }

    public function testCreateWithInvalidStreetThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address street is required');

        CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );
    }

    public function testCreateWithInvalidCityThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address city is required');

        CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: '',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );
    }

    public function testCreateWithInvalidPostalCodeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address postal code is required');

        CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '',
            country: 'US',
            type: AddressType::SHIPPING
        );
    }

    public function testCreateWithInvalidCountryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address country must be a 2-letter ISO code');

        CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'USA', // Invalid: 3 letters
            type: AddressType::SHIPPING
        );
    }

    public function testCreateWithTooLongStreetThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address street cannot exceed 255 characters');

        CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: str_repeat('a', 256),
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );
    }

    public function testCreateWithTooLongStreet2ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address street2 cannot exceed 255 characters');

        CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: str_repeat('a', 256),
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );
    }

    public function testCreateWithTooLongCityThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address city cannot exceed 100 characters');

        CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: str_repeat('a', 101),
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );
    }

    public function testCreateWithTooLongStateThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address state cannot exceed 100 characters');

        CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: str_repeat('a', 101),
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );
    }

    public function testCreateWithTooLongPostalCodeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address postal code cannot exceed 20 characters');

        CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: str_repeat('1', 21),
            country: 'US',
            type: AddressType::SHIPPING
        );
    }

    public function testCreateWithNonAlphaCountryThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address country must contain only uppercase letters');

        CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'U1', // Invalid: contains number
            type: AddressType::SHIPPING
        );
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $id = CustomerAddressId::generate();
        $address1 = CustomerAddress::create(
            id: $id,
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );

        $address2 = CustomerAddress::create(
            id: $id,
            street: '456 Oak Ave',
            street2: null,
            city: 'Los Angeles',
            state: 'CA',
            postalCode: '90001',
            country: 'US',
            type: AddressType::BILLING
        );

        $this->assertTrue($address1->equals($address2));
    }

    public function testEqualsReturnsFalseForDifferentId(): void
    {
        $address1 = CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );

        $address2 = CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );

        $this->assertFalse($address1->equals($address2));
    }

    public function testToArray(): void
    {
        $id = CustomerAddressId::generate();
        $address = CustomerAddress::create(
            id: $id,
            street: '123 Main St',
            street2: 'Apt 4B',
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::BOTH,
            isDefaultShipping: true,
            isDefaultBilling: false
        );

        $array = $address->toArray();

        $this->assertIsArray($array);
        $this->assertSame($id->toString(), $array['id']);
        $this->assertSame('123 Main St', $array['street']);
        $this->assertSame('Apt 4B', $array['street2']);
        $this->assertSame('New York', $array['city']);
        $this->assertSame('NY', $array['state']);
        $this->assertSame('10001', $array['postalCode']);
        $this->assertSame('US', $array['country']);
        $this->assertSame('both', $array['type']);
        $this->assertTrue($array['isDefaultShipping']);
        $this->assertFalse($array['isDefaultBilling']);
    }

    public function testWithDefaultShipping(): void
    {
        $id = CustomerAddressId::generate();
        $address = CustomerAddress::create(
            id: $id,
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING,
            isDefaultShipping: false
        );

        $newAddress = $address->withDefaultShipping(true);

        $this->assertFalse($address->isDefaultShipping); // Original unchanged
        $this->assertTrue($newAddress->isDefaultShipping); // New has flag
        $this->assertTrue($address->id->equals($newAddress->id)); // Same ID
    }

    public function testWithDefaultBilling(): void
    {
        $id = CustomerAddressId::generate();
        $address = CustomerAddress::create(
            id: $id,
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::BILLING,
            isDefaultBilling: false
        );

        $newAddress = $address->withDefaultBilling(true);

        $this->assertFalse($address->isDefaultBilling); // Original unchanged
        $this->assertTrue($newAddress->isDefaultBilling); // New has flag
        $this->assertTrue($address->id->equals($newAddress->id)); // Same ID
    }

    public function testImmutability(): void
    {
        $address = CustomerAddress::create(
            id: CustomerAddressId::generate(),
            street: '123 Main St',
            street2: null,
            city: 'New York',
            state: 'NY',
            postalCode: '10001',
            country: 'US',
            type: AddressType::SHIPPING
        );

        // Readonly properties should not be modifiable
        $reflection = new \ReflectionClass($address);
        $this->assertTrue($reflection->isReadOnly());
    }
}
