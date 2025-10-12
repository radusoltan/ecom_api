<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerEntity;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CustomerEntityTest extends TestCase
{
    public function testFromDomainModelConvertsAllFields(): void
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

        $entity = CustomerEntity::fromDomainModel($customer);

        self::assertEquals($customerId->toString(), $entity->getId());
        self::assertEquals($tenantId->toString(), $entity->getTenantId());
        self::assertEquals('john.doe@example.com', $entity->getEmail());
        self::assertEquals('John', $entity->getFirstName());
        self::assertEquals('Doe', $entity->getLastName());
        self::assertEquals('+12345678901', $entity->getPhoneNumber());
        self::assertEquals('regular', $entity->getSegment());
        self::assertEquals(0, $entity->getLoyaltyPoints());
        self::assertTrue($entity->isActive());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());
    }

    public function testToDomainModelConvertsBackToAggregate(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $originalCustomer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('john.doe@example.com'),
            'John',
            'Doe',
            '+12345678901'
        );

        $entity = CustomerEntity::fromDomainModel($originalCustomer);
        $reconstructedCustomer = $entity->toDomainModel();

        self::assertEquals($originalCustomer->id()->toString(), $reconstructedCustomer->id()->toString());
        self::assertEquals($originalCustomer->tenantId()->toString(), $reconstructedCustomer->tenantId()->toString());
        self::assertEquals($originalCustomer->email()->toString(), $reconstructedCustomer->email()->toString());
        self::assertEquals($originalCustomer->firstName(), $reconstructedCustomer->firstName());
        self::assertEquals($originalCustomer->lastName(), $reconstructedCustomer->lastName());
        self::assertEquals($originalCustomer->phoneNumber(), $reconstructedCustomer->phoneNumber());
        self::assertEquals($originalCustomer->segment()->toString(), $reconstructedCustomer->segment()->toString());
        self::assertEquals($originalCustomer->loyaltyPoints(), $reconstructedCustomer->loyaltyPoints());
        self::assertEquals($originalCustomer->isActive(), $reconstructedCustomer->isActive());
    }

    public function testUpdateFromDomainModelUpdatesFields(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('john.doe@example.com'),
            'John',
            'Doe'
        );

        $entity = CustomerEntity::fromDomainModel($customer);

        // Modify customer
        $customer->updateProfile('Jane', 'Smith', '+19876543210');
        $customer->changeSegment(CustomerSegment::vip());
        $customer->awardLoyaltyPoints(100, 'Test');
        $customer->deactivate();

        // Update entity
        $entity->updateFromDomainModel($customer);

        // Verify updates (but not id, tenantId, email which should never change)
        self::assertEquals($customerId->toString(), $entity->getId()); // Unchanged
        self::assertEquals($tenantId->toString(), $entity->getTenantId()); // Unchanged
        self::assertEquals('john.doe@example.com', $entity->getEmail()); // Unchanged
        self::assertEquals('Jane', $entity->getFirstName());
        self::assertEquals('Smith', $entity->getLastName());
        self::assertEquals('+19876543210', $entity->getPhoneNumber());
        self::assertEquals('vip', $entity->getSegment());
        self::assertEquals(100, $entity->getLoyaltyPoints());
        self::assertFalse($entity->isActive());
    }

    public function testRoundTripConversionPreservesData(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('test@example.com'),
            'Test',
            'User',
            '+11234567890'
        );

        // Modify state
        $customer->changeSegment(CustomerSegment::premium());
        $customer->awardLoyaltyPoints(250, 'Test bonus');

        // Convert to entity and back
        $entity = CustomerEntity::fromDomainModel($customer);
        $reconstructed = $entity->toDomainModel();

        // Verify all fields preserved
        self::assertEquals($customer->id()->toString(), $reconstructed->id()->toString());
        self::assertEquals($customer->tenantId()->toString(), $reconstructed->tenantId()->toString());
        self::assertEquals($customer->email()->toString(), $reconstructed->email()->toString());
        self::assertEquals($customer->firstName(), $reconstructed->firstName());
        self::assertEquals($customer->lastName(), $reconstructed->lastName());
        self::assertEquals($customer->phoneNumber(), $reconstructed->phoneNumber());
        self::assertEquals($customer->segment()->toString(), $reconstructed->segment()->toString());
        self::assertEquals($customer->loyaltyPoints(), $reconstructed->loyaltyPoints());
        self::assertEquals($customer->isActive(), $reconstructed->isActive());
        self::assertEquals(
            $customer->createdAt()->format('Y-m-d H:i:s'),
            $reconstructed->createdAt()->format('Y-m-d H:i:s')
        );
    }

    public function testGettersReturnCorrectValues(): void
    {
        $customer = Customer::register(
            CustomerId::generate(),
            TenantId::generate(),
            Email::fromString('getter@example.com'),
            'Getter',
            'Test'
        );

        $entity = CustomerEntity::fromDomainModel($customer);

        // Test all getters
        self::assertIsString($entity->getId());
        self::assertIsString($entity->getTenantId());
        self::assertIsString($entity->getEmail());
        self::assertIsString($entity->getFirstName());
        self::assertIsString($entity->getLastName());
        self::assertNull($entity->getPhoneNumber()); // Not provided
        self::assertIsString($entity->getSegment());
        self::assertIsInt($entity->getLoyaltyPoints());
        self::assertIsBool($entity->isActive());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());
    }
}
