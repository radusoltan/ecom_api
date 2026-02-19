<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\EventSubscriber;

use App\Customer\Application\EventSubscriber\PrivacyConsentSyncSubscriber;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\ConsentGranted;
use App\Privacy\Domain\Event\ConsentWithdrawn;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('customer')]
final class PrivacyConsentSyncSubscriberTest extends TestCase
{
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private LoggerInterface&MockObject $logger;
    private PrivacyConsentSyncSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new PrivacyConsentSyncSubscriber(
            $this->customerRepository,
            $this->logger,
        );
    }

    public function testOnConsentGrantedMapsMarketingPurpose(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('test@example.com'),
            firstName: 'Jane',
            lastName: 'Doe',
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with($customer);

        $event = new ConsentGranted(
            consentId: ConsentId::generate(),
            customerId: $customerId,
            purpose: ConsentPurpose::marketing(),
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onConsentGranted($event);

        $consents = $customer->getConsents();
        self::assertTrue($consents->marketingEmail());
        self::assertTrue($consents->marketingSms());
    }

    public function testOnConsentGrantedMapsAnalyticsPurpose(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('analytics@example.com'),
            firstName: 'John',
            lastName: 'Smith',
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with($customer);

        $event = new ConsentGranted(
            consentId: ConsentId::generate(),
            customerId: $customerId,
            purpose: ConsentPurpose::analytics(),
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onConsentGranted($event);

        $consents = $customer->getConsents();
        self::assertTrue($consents->analyticsTracking());
    }

    public function testOnConsentGrantedMapsThirdPartySharingPurpose(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('sharing@example.com'),
            firstName: 'Alice',
            lastName: 'Brown',
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with($customer);

        $event = new ConsentGranted(
            consentId: ConsentId::generate(),
            customerId: $customerId,
            purpose: ConsentPurpose::thirdPartySharing(),
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onConsentGranted($event);

        $consents = $customer->getConsents();
        self::assertTrue($consents->thirdPartySharing());
    }

    public function testOnConsentWithdrawnSetsConsentFalse(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $customer = Customer::register(
            id: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('withdrawn@example.com'),
            firstName: 'Bob',
            lastName: 'Green',
        );

        // First grant marketing consent so that withdrawing it has a visible effect
        $customer->updateConsent(ConsentType::MARKETING_EMAIL, true);
        $customer->updateConsent(ConsentType::MARKETING_SMS, true);

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with($customer);

        $event = new ConsentWithdrawn(
            consentId: ConsentId::generate(),
            customerId: $customerId,
            purpose: ConsentPurpose::marketing(),
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onConsentWithdrawn($event);

        $consents = $customer->getConsents();
        self::assertFalse($consents->marketingEmail());
        self::assertFalse($consents->marketingSms());
    }

    public function testUnknownPurposeIsIgnored(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        // Repository must never be called when purpose has no mapping
        $this->customerRepository
            ->expects(self::never())
            ->method('findById');

        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Privacy consent purpose has no Customer equivalent',
                self::arrayHasKey('purpose'),
            );

        $event = new ConsentGranted(
            consentId: ConsentId::generate(),
            customerId: $customerId,
            purpose: ConsentPurpose::profiling(),
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onConsentGranted($event);
    }

    public function testCustomerNotFoundLogsWarning(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn(null);

        $this->customerRepository
            ->expects(self::never())
            ->method('save');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Customer not found for consent sync',
                self::arrayHasKey('customerId'),
            );

        $event = new ConsentGranted(
            consentId: ConsentId::generate(),
            customerId: $customerId,
            purpose: ConsentPurpose::marketing(),
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onConsentGranted($event);
    }
}
