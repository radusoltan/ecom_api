<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\UpdateAllConsents\UpdateAllConsentsCommand;
use App\Customer\Application\Command\UpdateAllConsents\UpdateAllConsentsCommandHandler;
use App\Customer\Domain\Model\ConsentHistory;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\ConsentHistoryRepositoryInterface;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateAllConsentsCommandHandler::class)]
final class UpdateAllConsentsCommandHandlerTest extends TestCase
{
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private ConsentHistoryRepositoryInterface&MockObject $consentHistoryRepository;
    private UpdateAllConsentsCommandHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->consentHistoryRepository = $this->createMock(ConsentHistoryRepositoryInterface::class);
        $this->handler = new UpdateAllConsentsCommandHandler(
            $this->customerRepository,
            $this->consentHistoryRepository,
        );
    }

    // -----------------------------------------------------------------------
    // Happy path: all consents updated and persisted
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesAllConsentsOnCustomer(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with($customer);

        $this->consentHistoryRepository
            ->expects(self::atLeastOnce())
            ->method('save')
            ->with(self::isInstanceOf(ConsentHistory::class));

        ($this->handler)(new UpdateAllConsentsCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            marketingEmail: true,
            marketingSms: false,
            thirdPartySharing: true,
            analyticsTracking: false,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
        ));

        $consents = $customer->getConsents();
        self::assertTrue($consents->marketingEmail());
        self::assertFalse($consents->marketingSms());
        self::assertTrue($consents->thirdPartySharing());
        self::assertFalse($consents->analyticsTracking());
    }

    // -----------------------------------------------------------------------
    // History record created only for changed consents
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesHistoryOnlyForChangedConsents(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        // Customer starts with all consents false. Only marketingEmail changes.
        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->method('save');

        // Only 1 consent changes, so only 1 history record expected
        $this->consentHistoryRepository
            ->expects(self::once())
            ->method('save');

        ($this->handler)(new UpdateAllConsentsCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            marketingEmail: true,  // changed from false
            marketingSms: false,   // no change
            thirdPartySharing: false, // no change
            analyticsTracking: false, // no change
        ));
    }

    // -----------------------------------------------------------------------
    // No history records when nothing changes
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesNoHistoryWhenNothingChanges(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->expects(self::once())->method('save');

        // All consents remain false — no history
        $this->consentHistoryRepository->expects(self::never())->method('save');

        ($this->handler)(new UpdateAllConsentsCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            marketingEmail: false,
            marketingSms: false,
            thirdPartySharing: false,
            analyticsTracking: false,
        ));
    }

    // -----------------------------------------------------------------------
    // IP address and user agent propagate to history
    // -----------------------------------------------------------------------

    #[Test]
    public function itPassesIpAndUserAgentToHistoryRecord(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $customer = $this->buildCustomer($customerId, $tenantId);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->method('save');

        $capturedHistory = null;
        $this->consentHistoryRepository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (ConsentHistory $h) use (&$capturedHistory): void {
                $capturedHistory = $h;
            });

        ($this->handler)(new UpdateAllConsentsCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            marketingEmail: true,
            marketingSms: false,
            thirdPartySharing: false,
            analyticsTracking: false,
            ipAddress: '10.0.0.1',
            userAgent: 'Chrome/120',
        ));

        self::assertNotNull($capturedHistory);
        self::assertSame('10.0.0.1', $capturedHistory->ipAddress());
        self::assertSame('Chrome/120', $capturedHistory->userAgent());
    }

    // -----------------------------------------------------------------------
    // Exception: customer not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerNotFound(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->customerRepository->expects(self::never())->method('save');
        $this->consentHistoryRepository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)(new UpdateAllConsentsCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            marketingEmail: true,
            marketingSms: false,
            thirdPartySharing: false,
            analyticsTracking: false,
        ));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildCustomer(CustomerId $customerId, TenantId $tenantId): Customer
    {
        $customer = Customer::register(
            $customerId,
            $tenantId,
            Email::fromString('customer@example.com'),
            'John',
            'Doe',
        );
        $customer->popEvents();

        return $customer;
    }
}
