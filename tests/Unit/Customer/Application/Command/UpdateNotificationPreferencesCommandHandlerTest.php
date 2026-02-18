<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\UpdateNotificationPreferences\UpdateNotificationPreferencesCommand;
use App\Customer\Application\Command\UpdateNotificationPreferences\UpdateNotificationPreferencesCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class UpdateNotificationPreferencesCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private UpdateNotificationPreferencesCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new UpdateNotificationPreferencesCommandHandler($this->customerRepository);
    }

    public function testUpdateSuccess(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('customer@example.com'),
            firstName: 'John',
            lastName: 'Doe'
        );

        // Enable marketing email consent first (required for promotional offers)
        $customer->updateConsent(\App\Customer\Domain\ValueObject\ConsentType::MARKETING_EMAIL, true);

        $command = new UpdateNotificationPreferencesCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            orderUpdates: true,
            shippingUpdates: false,
            promotionalOffers: true,
            priceDropAlerts: true,
            backInStockAlerts: false,
            securityAlerts: true,
            newsletterWeekly: true,
            preferSms: false
        );

        $this->customerRepository->expects($this->once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->customerRepository->expects($this->once())
            ->method('save')
            ->with($customer);

        ($this->handler)($command);

        $prefs = $customer->getNotificationPreferences();
        self::assertTrue($prefs->orderUpdates());
        self::assertFalse($prefs->shippingUpdates());
        self::assertTrue($prefs->promotionalOffers());
        self::assertTrue($prefs->priceDropAlerts());
        self::assertFalse($prefs->backInStockAlerts());
        self::assertTrue($prefs->securityAlerts());
        self::assertTrue($prefs->newsletterWeekly());
        self::assertFalse($prefs->preferSms());
    }

    public function testCannotDisableOrderUpdates(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('customer@example.com'),
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->customerRepository->method('findById')->willReturn($customer);

        $command = new UpdateNotificationPreferencesCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            orderUpdates: false, // Invalid!
            shippingUpdates: true,
            promotionalOffers: false,
            priceDropAlerts: false,
            backInStockAlerts: false,
            securityAlerts: true,
            newsletterWeekly: false,
            preferSms: false
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order updates cannot be disabled');

        ($this->handler)($command);
    }

    public function testCannotDisableSecurityAlerts(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('customer@example.com'),
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->customerRepository->method('findById')->willReturn($customer);

        $command = new UpdateNotificationPreferencesCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            orderUpdates: true,
            shippingUpdates: true,
            promotionalOffers: false,
            priceDropAlerts: false,
            backInStockAlerts: false,
            securityAlerts: false, // Invalid!
            newsletterWeekly: false,
            preferSms: false
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Security alerts cannot be disabled');

        ($this->handler)($command);
    }

    public function testUpdateFailsWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $command = new UpdateNotificationPreferencesCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            orderUpdates: true,
            shippingUpdates: true,
            promotionalOffers: false,
            priceDropAlerts: false,
            backInStockAlerts: false,
            securityAlerts: true,
            newsletterWeekly: false,
            preferSms: false
        );

        $this->customerRepository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }
}
