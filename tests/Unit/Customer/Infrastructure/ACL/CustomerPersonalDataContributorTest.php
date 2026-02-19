<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Infrastructure\ACL;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerConsent;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerPreferences;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Customer\Domain\ValueObject\NotificationPreferences;
use App\Customer\Infrastructure\ACL\CustomerPersonalDataContributor;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('customer')]
final class CustomerPersonalDataContributorTest extends TestCase
{
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private CustomerPersonalDataContributor $contributor;

    private const CUSTOMER_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->contributor = new CustomerPersonalDataContributor($this->customerRepository);
    }

    public function testContextNameReturnsCustomer(): void
    {
        self::assertSame('customer', $this->contributor->contextName());
    }

    public function testCollectsPersonalDataForExistingCustomer(): void
    {
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $customer = Customer::reconstituteFromPersistence(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('john.doe@example.com'),
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: '+12345678901',
            segment: CustomerSegment::regular(),
            loyaltyPoints: 150,
            isActive: true,
            preferences: CustomerPreferences::create(),
            consents: CustomerConsent::default(),
            notificationPreferences: NotificationPreferences::default(),
            createdAt: new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            updatedAt: new \DateTimeImmutable('2025-06-01T12:00:00+00:00'),
            addresses: [],
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(fn (CustomerId $id) => $id->toString() === self::CUSTOMER_ID),
                self::callback(fn (TenantId $tid) => $tid->toString() === self::TENANT_ID),
            )
            ->willReturn($customer);

        $result = $this->contributor->collectPersonalData([
            'customerId' => self::CUSTOMER_ID,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertSame(self::CUSTOMER_ID, $result['id']);
        self::assertSame('john.doe@example.com', $result['email']);
        self::assertSame('John', $result['firstName']);
        self::assertSame('Doe', $result['lastName']);
        self::assertSame('+12345678901', $result['phoneNumber']);
        self::assertSame('regular', $result['segment']);
        self::assertSame(150, $result['loyaltyPoints']);
        self::assertTrue($result['isActive']);
        self::assertIsArray($result['addresses']);
        self::assertIsArray($result['preferences']);
        self::assertArrayHasKey('id', $result);
        self::assertArrayHasKey('email', $result);
        self::assertArrayHasKey('firstName', $result);
        self::assertArrayHasKey('lastName', $result);
        self::assertArrayHasKey('phoneNumber', $result);
        self::assertArrayHasKey('segment', $result);
        self::assertArrayHasKey('loyaltyPoints', $result);
        self::assertArrayHasKey('isActive', $result);
        self::assertArrayHasKey('addresses', $result);
        self::assertArrayHasKey('preferences', $result);
        self::assertArrayHasKey('createdAt', $result);
        self::assertArrayHasKey('updatedAt', $result);
    }

    public function testReturnsEmptyArrayWhenCustomerNotFound(): void
    {
        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $result = $this->contributor->collectPersonalData([
            'customerId' => self::CUSTOMER_ID,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertSame([], $result);
    }

    public function testReturnsEmptyArrayWhenMissingCustomerId(): void
    {
        $this->customerRepository
            ->expects(self::never())
            ->method('findById');

        $result = $this->contributor->collectPersonalData([
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertSame([], $result);
    }

    public function testReturnsEmptyArrayWhenMissingTenantId(): void
    {
        $this->customerRepository
            ->expects(self::never())
            ->method('findById');

        $result = $this->contributor->collectPersonalData([
            'customerId' => self::CUSTOMER_ID,
        ]);

        self::assertSame([], $result);
    }

    public function testReturnsEmptyArrayWhenBothContextValuesAreMissing(): void
    {
        $this->customerRepository
            ->expects(self::never())
            ->method('findById');

        $result = $this->contributor->collectPersonalData([]);

        self::assertSame([], $result);
    }

    public function testCanAlwaysDeleteData(): void
    {
        $result = $this->contributor->canDeleteData([
            'customerId' => self::CUSTOMER_ID,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertTrue($result);
    }

    public function testCanDeleteDataWithEmptyContext(): void
    {
        self::assertTrue($this->contributor->canDeleteData([]));
    }
}
