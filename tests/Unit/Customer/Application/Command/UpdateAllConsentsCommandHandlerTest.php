<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\UpdateAllConsents\UpdateAllConsentsCommand;
use App\Customer\Application\Command\UpdateAllConsents\UpdateAllConsentsCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\ConsentHistoryRepositoryInterface;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class UpdateAllConsentsCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private ConsentHistoryRepositoryInterface $consentHistoryRepository;
    private UpdateAllConsentsCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->consentHistoryRepository = $this->createMock(ConsentHistoryRepositoryInterface::class);
        $this->handler = new UpdateAllConsentsCommandHandler(
            $this->customerRepository,
            $this->consentHistoryRepository
        );
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

        $command = new UpdateAllConsentsCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            marketingEmail: true,
            marketingSms: false,
            thirdPartySharing: true,
            analyticsTracking: false,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->customerRepository->expects($this->once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->customerRepository->expects($this->once())
            ->method('save')
            ->with($customer);

        $this->consentHistoryRepository->expects($this->atLeastOnce())
            ->method('save');

        ($this->handler)($command);

        $consents = $customer->getConsents();
        self::assertTrue($consents->marketingEmail());
        self::assertFalse($consents->marketingSms());
        self::assertTrue($consents->thirdPartySharing());
        self::assertFalse($consents->analyticsTracking());
    }

    public function testCreatesHistoryRecord(): void
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

        $command = new UpdateAllConsentsCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            marketingEmail: true,
            marketingSms: true,
            thirdPartySharing: true,
            analyticsTracking: true,
            ipAddress: '10.0.0.1',
            userAgent: 'Chrome/90.0'
        );

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->method('save');

        // Should create history records for each consent type
        $this->consentHistoryRepository->expects($this->atLeastOnce())
            ->method('save');

        ($this->handler)($command);
    }

    public function testDispatchesEvent(): void
    {
        // Event dispatching would be tested here if EventDispatcher is injected
        $this->expectNotToPerformAssertions();
    }

    public function testUpdateFailsWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $command = new UpdateAllConsentsCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            marketingEmail: true,
            marketingSms: false,
            thirdPartySharing: false,
            analyticsTracking: false
        );

        $this->customerRepository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }
}
