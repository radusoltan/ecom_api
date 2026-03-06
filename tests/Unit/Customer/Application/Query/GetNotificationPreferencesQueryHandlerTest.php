<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\NotificationPreferencesDTO;
use App\Customer\Application\Query\GetNotificationPreferences\GetNotificationPreferencesQuery;
use App\Customer\Application\Query\GetNotificationPreferences\GetNotificationPreferencesQueryHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetNotificationPreferencesQueryHandler::class)]
final class GetNotificationPreferencesQueryHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CustomerRepositoryInterface&MockObject $customerRepository;
    private GetNotificationPreferencesQueryHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new GetNotificationPreferencesQueryHandler($this->customerRepository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeCustomer(): Customer
    {
        $customer = Customer::register(
            CustomerId::generate(),
            TenantId::fromString(self::TENANT_ID),
            Email::fromString('prefs@example.com'),
            'Alice',
            'Wonder',
        );
        $customer->popEvents();

        return $customer;
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsNotificationPreferencesDTOWhenCustomerFound(): void
    {
        $customer = $this->makeCustomer();

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::isInstanceOf(CustomerId::class),
                self::isInstanceOf(TenantId::class),
            )
            ->willReturn($customer);

        $query = new GetNotificationPreferencesQuery(
            customerId: $customer->id(),
            tenantId: TenantId::fromString(self::TENANT_ID),
        );

        $result = ($this->handler)($query);

        self::assertInstanceOf(NotificationPreferencesDTO::class, $result);
    }

    #[Test]
    public function itReturnsDefaultPreferencesForNewlyRegisteredCustomer(): void
    {
        $customer = $this->makeCustomer();

        $this->customerRepository->method('findById')->willReturn($customer);

        $query = new GetNotificationPreferencesQuery(
            customerId: $customer->id(),
            tenantId: TenantId::fromString(self::TENANT_ID),
        );
        $result = ($this->handler)($query);

        // Customer::register() sets NotificationPreferences::default() — all false by default
        self::assertFalse($result->promotionalOffers);
        self::assertFalse($result->newsletterWeekly);
    }

    #[Test]
    public function itIncludesUpdatedAtTimestampInDTO(): void
    {
        $customer = $this->makeCustomer();

        $this->customerRepository->method('findById')->willReturn($customer);

        $query = new GetNotificationPreferencesQuery(
            customerId: $customer->id(),
            tenantId: TenantId::fromString(self::TENANT_ID),
        );
        $result = ($this->handler)($query);

        // The handler passes customer->updatedAt() as the timestamp
        self::assertInstanceOf(\DateTimeImmutable::class, $result->lastUpdatedAt);
    }

    // -----------------------------------------------------------------------
    // Customer not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsDomainExceptionWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();

        $this->customerRepository->method('findById')->willReturn(null);

        $query = new GetNotificationPreferencesQuery(
            customerId: $customerId,
            tenantId: TenantId::fromString(self::TENANT_ID),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage($customerId->toString());

        ($this->handler)($query);
    }

    #[Test]
    public function itDoesNotCallRepositoryTwice(): void
    {
        $customer = $this->makeCustomer();

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $query = new GetNotificationPreferencesQuery(
            customerId: $customer->id(),
            tenantId: TenantId::fromString(self::TENANT_ID),
        );

        ($this->handler)($query);
    }
}
