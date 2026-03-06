<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerPreferencesDTO;
use App\Customer\Application\Query\GetCustomerPreferences\GetCustomerPreferencesQuery;
use App\Customer\Application\Query\GetCustomerPreferences\GetCustomerPreferencesQueryHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetCustomerPreferencesQueryHandler::class)]
final class GetCustomerPreferencesQueryHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CustomerRepositoryInterface&MockObject $customerRepository;
    private GetCustomerPreferencesQueryHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new GetCustomerPreferencesQueryHandler($this->customerRepository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeCustomer(): Customer
    {
        $customer = Customer::register(
            CustomerId::generate(),
            TenantId::fromString(self::TENANT_ID),
            Email::fromString('pref@example.com'),
            'Bob',
            'Builder',
        );
        $customer->popEvents();

        return $customer;
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsCustomerPreferencesDTOWhenCustomerFound(): void
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

        $query = new GetCustomerPreferencesQuery(
            customerId: $customer->id()->toString(),
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($query);

        self::assertInstanceOf(CustomerPreferencesDTO::class, $result);
    }

    #[Test]
    public function itReturnsDefaultPreferencesForNewCustomer(): void
    {
        $customer = $this->makeCustomer();

        $this->customerRepository->method('findById')->willReturn($customer);

        $query = new GetCustomerPreferencesQuery(
            customerId: $customer->id()->toString(),
            tenantId: self::TENANT_ID,
        );
        $result = ($this->handler)($query);

        // CustomerPreferences::create() sets these defaults
        self::assertSame('en', $result->preferredLanguage);
        self::assertSame('EUR', $result->preferredCurrency);
        self::assertFalse($result->newsletterSubscribed);
        self::assertFalse($result->marketingEmailsAllowed);
    }

    #[Test]
    public function itMapsAllPreferenceFields(): void
    {
        $customer = $this->makeCustomer();

        $this->customerRepository->method('findById')->willReturn($customer);

        $query = new GetCustomerPreferencesQuery(
            customerId: $customer->id()->toString(),
            tenantId: self::TENANT_ID,
        );
        $result = ($this->handler)($query);

        self::assertIsBool($result->newsletterSubscribed);
        self::assertIsBool($result->marketingEmailsAllowed);
        self::assertIsBool($result->smsNotificationsAllowed);
        self::assertIsBool($result->orderNotificationsEnabled);
        self::assertIsBool($result->promotionNotificationsEnabled);
    }

    // -----------------------------------------------------------------------
    // Customer not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsInvalidArgumentExceptionWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();

        $this->customerRepository->method('findById')->willReturn(null);

        $query = new GetCustomerPreferencesQuery(
            customerId: $customerId->toString(),
            tenantId: self::TENANT_ID,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($customerId->toString());

        ($this->handler)($query);
    }

    #[Test]
    public function itDoesNotCallRepositoryMoreThanOnce(): void
    {
        $customer = $this->makeCustomer();

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        ($this->handler)(new GetCustomerPreferencesQuery(
            customerId: $customer->id()->toString(),
            tenantId: self::TENANT_ID,
        ));
    }
}
