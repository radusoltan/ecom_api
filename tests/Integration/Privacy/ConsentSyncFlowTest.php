<?php

declare(strict_types=1);

namespace App\Tests\Integration\Privacy;

use App\Customer\Application\EventSubscriber\PrivacyConsentSyncSubscriber;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Event\ConsentGranted;
use App\Privacy\Domain\Event\ConsentWithdrawn;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Integration tests for the Privacy->Customer consent sync flow.
 *
 * Verifies end-to-end event chain:
 * Consent::grant() -> ConsentGranted event -> PrivacyConsentSyncSubscriber -> Customer::updateConsent()
 * Consent::withdraw() -> ConsentWithdrawn event -> PrivacyConsentSyncSubscriber -> Customer::updateConsent()
 */
final class ConsentSyncFlowTest extends TestCase
{
    private const DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const DEFAULT_CUSTOMER_ID = 'a0000000-0000-4000-8000-000000000001';

    private const CONSENT_TEXT = 'I consent to receive marketing communications about products, offers, and updates from this platform as described in the privacy policy.';
    private const CONSENT_VERSION = 'v1.0.0';
    private const IP_ADDRESS = '192.168.1.1';
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64)';

    private CustomerRepositoryInterface&MockObject $customerRepository;
    private PrivacyConsentSyncSubscriber $subscriber;
    private TenantId $tenantId;
    private CustomerId $customerId;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);

        $this->subscriber = new PrivacyConsentSyncSubscriber(
            customerRepository: $this->customerRepository,
            logger: new NullLogger(),
        );

        $this->tenantId = TenantId::fromString(self::DEFAULT_TENANT_ID);
        $this->customerId = CustomerId::fromString(self::DEFAULT_CUSTOMER_ID);
    }

    public function testGrantConsentFlowSyncsToCustomer(): void
    {
        $consent = Consent::grant(
            id: ConsentId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::marketing(),
            ipAddress: self::IP_ADDRESS,
            userAgent: self::USER_AGENT,
            consentText: self::CONSENT_TEXT,
            consentVersion: self::CONSENT_VERSION,
        );

        $events = $consent->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsentGranted::class, $events[0]);

        $customer = $this->createRealCustomer();
        self::assertFalse($customer->getConsents()->marketingEmail());
        self::assertFalse($customer->getConsents()->marketingSms());

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->expects(self::once())->method('save');

        $this->subscriber->onConsentGranted($events[0]);

        self::assertTrue($customer->getConsents()->marketingEmail());
        self::assertTrue($customer->getConsents()->marketingSms());
    }

    public function testWithdrawConsentFlowSyncsToCustomer(): void
    {
        $consent = Consent::grant(
            id: ConsentId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::analytics(),
            ipAddress: self::IP_ADDRESS,
            userAgent: self::USER_AGENT,
            consentText: self::CONSENT_TEXT,
            consentVersion: self::CONSENT_VERSION,
        );
        $consent->popEvents();

        $consent->withdraw();
        $events = $consent->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ConsentWithdrawn::class, $events[0]);

        // Pre-set analytics tracking to true
        $customer = $this->createRealCustomer();
        $customer->updateConsent(ConsentType::ANALYTICS_TRACKING, true);
        self::assertTrue($customer->getConsents()->analyticsTracking());

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->expects(self::once())->method('save');

        $this->subscriber->onConsentWithdrawn($events[0]);

        self::assertFalse($customer->getConsents()->analyticsTracking());
    }

    public function testGrantConsentFlowDoesNothingWhenCustomerNotFound(): void
    {
        $consent = Consent::grant(
            id: ConsentId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::marketing(),
            ipAddress: self::IP_ADDRESS,
            userAgent: self::USER_AGENT,
            consentText: self::CONSENT_TEXT,
            consentVersion: self::CONSENT_VERSION,
        );
        $events = $consent->popEvents();

        $this->customerRepository->method('findById')->willReturn(null);
        $this->customerRepository->expects(self::never())->method('save');

        $this->subscriber->onConsentGranted($events[0]);
    }

    public function testMultiplePurposesMappedCorrectly(): void
    {
        $mappingCases = [
            'marketing' => ['marketingEmail' => true, 'marketingSms' => true],
            'analytics' => ['analyticsTracking' => true],
            'third_party_sharing' => ['thirdPartySharing' => true],
        ];

        foreach ($mappingCases as $purpose => $expectedFlags) {
            $consent = Consent::grant(
                id: ConsentId::generate(),
                tenantId: $this->tenantId,
                customerId: $this->customerId,
                purpose: ConsentPurpose::fromString($purpose),
                ipAddress: self::IP_ADDRESS,
                userAgent: self::USER_AGENT,
                consentText: self::CONSENT_TEXT,
                consentVersion: self::CONSENT_VERSION,
            );
            $events = $consent->popEvents();

            $customer = $this->createRealCustomer();
            $repo = $this->createMock(CustomerRepositoryInterface::class);
            $repo->method('findById')->willReturn($customer);
            $repo->expects(self::once())->method('save');

            $subscriber = new PrivacyConsentSyncSubscriber(
                customerRepository: $repo,
                logger: new NullLogger(),
            );
            $subscriber->onConsentGranted($events[0]);

            $consents = $customer->getConsents();
            foreach ($expectedFlags as $flag => $expected) {
                self::assertSame(
                    $expected,
                    $consents->$flag(),
                    sprintf('Expected %s=%s for purpose "%s"', $flag, $expected ? 'true' : 'false', $purpose)
                );
            }
        }
    }

    public function testUnmappedPurposesAreIgnored(): void
    {
        foreach (['profiling', 'necessary', 'legal'] as $purpose) {
            $consent = Consent::grant(
                id: ConsentId::generate(),
                tenantId: $this->tenantId,
                customerId: $this->customerId,
                purpose: ConsentPurpose::fromString($purpose),
                ipAddress: self::IP_ADDRESS,
                userAgent: self::USER_AGENT,
                consentText: self::CONSENT_TEXT,
                consentVersion: self::CONSENT_VERSION,
            );
            $events = $consent->popEvents();

            $repo = $this->createMock(CustomerRepositoryInterface::class);
            $repo->expects(self::never())->method('findById');
            $repo->expects(self::never())->method('save');

            $subscriber = new PrivacyConsentSyncSubscriber(
                customerRepository: $repo,
                logger: new NullLogger(),
            );
            $subscriber->onConsentGranted($events[0]);
        }
    }

    public function testWithdrawMarketingClearsBothFlags(): void
    {
        $consent = Consent::grant(
            id: ConsentId::generate(),
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::marketing(),
            ipAddress: self::IP_ADDRESS,
            userAgent: self::USER_AGENT,
            consentText: self::CONSENT_TEXT,
            consentVersion: self::CONSENT_VERSION,
        );
        $consent->popEvents();
        $consent->withdraw();
        $events = $consent->popEvents();

        $customer = $this->createRealCustomer();
        $customer->updateConsent(ConsentType::MARKETING_EMAIL, true);
        $customer->updateConsent(ConsentType::MARKETING_SMS, true);
        self::assertTrue($customer->getConsents()->marketingEmail());
        self::assertTrue($customer->getConsents()->marketingSms());

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->customerRepository->expects(self::once())->method('save');

        $this->subscriber->onConsentWithdrawn($events[0]);

        self::assertFalse($customer->getConsents()->marketingEmail());
        self::assertFalse($customer->getConsents()->marketingSms());
    }

    public function testGrantedEventCarriesCorrectMetadata(): void
    {
        $consentId = ConsentId::generate();

        $consent = Consent::grant(
            id: $consentId,
            tenantId: $this->tenantId,
            customerId: $this->customerId,
            purpose: ConsentPurpose::analytics(),
            ipAddress: self::IP_ADDRESS,
            userAgent: self::USER_AGENT,
            consentText: self::CONSENT_TEXT,
            consentVersion: self::CONSENT_VERSION,
        );

        $events = $consent->popEvents();
        $event = $events[0];

        self::assertInstanceOf(ConsentGranted::class, $event);
        self::assertTrue($event->consentId->equals($consentId));
        self::assertSame(self::DEFAULT_CUSTOMER_ID, $event->customerId->toString());
        self::assertSame(self::DEFAULT_TENANT_ID, $event->tenantId->toString());
        self::assertSame('analytics', $event->purpose->value());
        self::assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn);
    }

    private function createRealCustomer(): Customer
    {
        return Customer::register(
            id: $this->customerId,
            tenantId: $this->tenantId,
            email: Email::fromString('test@example.com'),
            firstName: 'Test',
            lastName: 'Customer',
        );
    }
}
