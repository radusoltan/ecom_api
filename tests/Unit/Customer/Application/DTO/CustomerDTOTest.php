<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\DTO;

use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CustomerDTOTest extends TestCase
{
    public function testFromDomainConvertsAllFields(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('john.doe@example.com'),
            'John',
            'Doe',
            '+12345678901'
        );

        $dto = CustomerDTO::fromDomain($customer);

        self::assertEquals($customerId->toString(), $dto->id);
        self::assertEquals($tenantId->toString(), $dto->tenantId);
        self::assertEquals('john.doe@example.com', $dto->email);
        self::assertEquals('John', $dto->firstName);
        self::assertEquals('Doe', $dto->lastName);
        self::assertEquals('John Doe', $dto->fullName);
        self::assertEquals('+12345678901', $dto->phoneNumber);
        self::assertEquals('regular', $dto->segment);
        self::assertEquals(0, $dto->loyaltyPoints);
        self::assertTrue($dto->isActive);
        self::assertNotEmpty($dto->createdAt);
        self::assertNotEmpty($dto->updatedAt);
    }

    public function testFromDomainWithNullPhoneNumber(): void
    {
        $customer = Customer::register(
            CustomerId::generate(),
            TenantId::generate(),
            Email::fromString('jane@example.com'),
            'Jane',
            'Smith'
            // No phone number
        );

        $dto = CustomerDTO::fromDomain($customer);

        self::assertNull($dto->phoneNumber);
    }

    public function testFromDomainWithVipSegment(): void
    {
        $customer = Customer::register(
            CustomerId::generate(),
            TenantId::generate(),
            Email::fromString('vip@example.com'),
            'VIP',
            'Customer'
        );

        $customer->changeSegment(CustomerSegment::vip());

        $dto = CustomerDTO::fromDomain($customer);

        self::assertEquals('vip', $dto->segment);
    }

    public function testFromDomainWithPremiumSegment(): void
    {
        $customer = Customer::register(
            CustomerId::generate(),
            TenantId::generate(),
            Email::fromString('premium@example.com'),
            'Premium',
            'Customer'
        );

        $customer->changeSegment(CustomerSegment::premium());

        $dto = CustomerDTO::fromDomain($customer);

        self::assertEquals('premium', $dto->segment);
    }

    public function testFromDomainWithLoyaltyPoints(): void
    {
        $customer = Customer::register(
            CustomerId::generate(),
            TenantId::generate(),
            Email::fromString('loyal@example.com'),
            'Loyal',
            'Customer'
        );

        $customer->awardLoyaltyPoints(500, 'Test reward');

        $dto = CustomerDTO::fromDomain($customer);

        self::assertEquals(500, $dto->loyaltyPoints);
    }

    public function testFromDomainWithInactiveStatus(): void
    {
        $customer = Customer::register(
            CustomerId::generate(),
            TenantId::generate(),
            Email::fromString('inactive@example.com'),
            'Inactive',
            'Customer'
        );

        $customer->deactivate();

        $dto = CustomerDTO::fromDomain($customer);

        self::assertFalse($dto->isActive);
    }

    public function testDateTimeFormatting(): void
    {
        $customer = Customer::register(
            CustomerId::generate(),
            TenantId::generate(),
            Email::fromString('test@example.com'),
            'Test',
            'User'
        );

        $dto = CustomerDTO::fromDomain($customer);

        // Check format: Y-m-d H:i:s
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $dto->createdAt
        );
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $dto->updatedAt
        );
    }
}
