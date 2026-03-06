<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\Persistence\Doctrine\Entity;

use App\Customer\Infrastructure\Persistence\Doctrine\Entity\CustomerAddressEntity;
use PHPUnit\Framework\TestCase;

final class CustomerAddressEntityTest extends TestCase
{
    public function testFromDomainModelRoundtrip(): void
    {
        $addressData = [
            'id' => 'addr-001',
            'street' => '123 Main St',
            'street2' => 'Apt 4B',
            'city' => 'Berlin',
            'state' => 'BE',
            'postalCode' => '10115',
            'country' => 'DE',
            'type' => 'shipping',
            'isDefaultShipping' => true,
            'isDefaultBilling' => false,
            'isDeleted' => false,
        ];

        $entity = CustomerAddressEntity::fromDomainModel($addressData, 'cust-001', 'tenant-001');

        self::assertSame('addr-001', $entity->getId());
        self::assertSame('cust-001', $entity->getCustomerId());
        self::assertSame('tenant-001', $entity->getTenantId());
        self::assertSame('123 Main St', $entity->getStreet());
        self::assertSame('Apt 4B', $entity->getStreet2());
        self::assertSame('Berlin', $entity->getCity());
        self::assertSame('BE', $entity->getState());
        self::assertSame('10115', $entity->getPostalCode());
        self::assertSame('DE', $entity->getCountry());
        self::assertSame('shipping', $entity->getType());
        self::assertTrue($entity->isDefaultShipping());
        self::assertFalse($entity->isDefaultBilling());
        self::assertFalse($entity->isDeleted());
        self::assertNull($entity->getCustomer());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());

        // Roundtrip
        $data = $entity->toDomainModel();
        self::assertSame('addr-001', $data['id']);
        self::assertSame('cust-001', $data['customerId']);
        self::assertSame('Berlin', $data['city']);
        self::assertTrue($data['isDefaultShipping']);
    }

    public function testUpdateFromDomainModel(): void
    {
        $entity = CustomerAddressEntity::fromDomainModel(
            ['id' => 'addr-002', 'street' => 'Old St', 'city' => 'Old City', 'postalCode' => '00000', 'country' => 'DE', 'type' => 'billing'],
            'cust-002', 'tenant-002',
        );

        $entity->updateFromDomainModel([
            'street' => 'New St',
            'city' => 'New City',
            'isDefaultBilling' => true,
        ]);

        self::assertSame('New St', $entity->getStreet());
        self::assertSame('New City', $entity->getCity());
        self::assertTrue($entity->isDefaultBilling());
    }

    public function testSoftDelete(): void
    {
        $entity = CustomerAddressEntity::fromDomainModel(
            ['id' => 'addr-003', 'street' => 'St', 'city' => 'C', 'postalCode' => '00000', 'country' => 'DE', 'type' => 'shipping'],
            'cust-003', 'tenant-003',
        );

        self::assertFalse($entity->isDeleted());
        $entity->softDelete();
        self::assertTrue($entity->isDeleted());
    }

    public function testFormattedAddressWithAllParts(): void
    {
        $entity = CustomerAddressEntity::fromDomainModel(
            ['id' => 'a1', 'street' => '123 Main', 'street2' => 'Suite 5', 'city' => 'Berlin', 'state' => 'BE', 'postalCode' => '10115', 'country' => 'DE', 'type' => 'shipping'],
            'c1', 't1',
        );

        self::assertSame('123 Main, Suite 5, Berlin, BE, 10115, DE', $entity->getFormattedAddress());
    }

    public function testFormattedAddressWithoutOptionalParts(): void
    {
        $entity = CustomerAddressEntity::fromDomainModel(
            ['id' => 'a2', 'street' => '456 Oak', 'city' => 'Munich', 'postalCode' => '80331', 'country' => 'DE', 'type' => 'billing'],
            'c2', 't2',
        );

        self::assertSame('456 Oak, Munich, 80331, DE', $entity->getFormattedAddress());
    }

    public function testSetters(): void
    {
        $entity = new CustomerAddressEntity();
        $entity->setId('addr-010');
        $entity->setCustomerId('cust-010');
        $entity->setTenantId('tenant-010');
        $entity->setStreet('100 New St');
        $entity->setStreet2('Floor 3');
        $entity->setCity('Hamburg');
        $entity->setState('HH');
        $entity->setPostalCode('20095');
        $entity->setCountry('DE');
        $entity->setType('billing');
        $entity->setIsDefaultShipping(false);
        $entity->setIsDefaultBilling(true);
        $entity->setIsDeleted(false);
        $entity->setCustomer(null);
        $entity->setCreatedAt(new \DateTimeImmutable());
        $entity->setUpdatedAt(new \DateTimeImmutable());

        self::assertSame('addr-010', $entity->getId());
        self::assertSame('cust-010', $entity->getCustomerId());
        self::assertSame('tenant-010', $entity->getTenantId());
        self::assertSame('100 New St', $entity->getStreet());
        self::assertSame('Floor 3', $entity->getStreet2());
        self::assertSame('Hamburg', $entity->getCity());
        self::assertSame('HH', $entity->getState());
        self::assertSame('20095', $entity->getPostalCode());
        self::assertSame('DE', $entity->getCountry());
        self::assertSame('billing', $entity->getType());
        self::assertFalse($entity->isDefaultShipping());
        self::assertTrue($entity->isDefaultBilling());
        self::assertFalse($entity->isDeleted());
    }
}
