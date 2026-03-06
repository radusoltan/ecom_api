<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\UpdateConsent\UpdateConsentCommand;
use App\Customer\Application\Command\UpdateConsent\UpdateConsentCommandHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\ConsentHistoryRepositoryInterface;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateConsentCommandHandler::class)]
final class UpdateConsentCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private ConsentHistoryRepositoryInterface $consentHistoryRepository;
    private UpdateConsentCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->consentHistoryRepository = $this->createMock(ConsentHistoryRepositoryInterface::class);
        $this->handler = new UpdateConsentCommandHandler(
            $this->customerRepository,
            $this->consentHistoryRepository
        );
    }

    #[Test]
    public function itUpdatesConsentAndCreatesHistoryRecord(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('customer@example.com'),
            'John',
            'Doe'
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Customer::class));

        $this->consentHistoryRepository
            ->expects(self::once())
            ->method('save');

        $command = new UpdateConsentCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itWithdrawsConsent(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('customer@example.com'),
            'Jane',
            'Doe'
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save');

        $this->consentHistoryRepository
            ->expects(self::once())
            ->method('save');

        $command = new UpdateConsentCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            consentType: ConsentType::ANALYTICS_TRACKING,
            granted: false
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $this->consentHistoryRepository
            ->expects(self::never())
            ->method('save');

        $command = new UpdateConsentCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }
}
