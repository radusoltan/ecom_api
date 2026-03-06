<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Infrastructure\Service;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Port\PersonalDataContributorInterface;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Privacy\Infrastructure\Service\PersonalDataInventory;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('privacy')]
final class PersonalDataInventoryTest extends TestCase
{
    private ConsentRepositoryInterface&MockObject $consentRepository;
    private LoggerInterface&MockObject $logger;

    private const CUSTOMER_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->consentRepository = $this->createMock(ConsentRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testExportCustomerDataCollectsFromAllContributors(): void
    {
        $contributor1 = $this->createMock(PersonalDataContributorInterface::class);
        $contributor1->method('contextName')->willReturn('customer');
        $contributor1->method('collectPersonalData')->willReturn([
            'id' => self::CUSTOMER_ID,
            'email' => 'john@example.com',
            'firstName' => 'John',
        ]);

        $contributor2 = $this->createMock(PersonalDataContributorInterface::class);
        $contributor2->method('contextName')->willReturn('orders');
        $contributor2->method('collectPersonalData')->willReturn([
            ['id' => 'order-1', 'status' => 'delivered'],
        ]);

        $this->consentRepository
            ->method('findByCustomerId')
            ->willReturn([]);

        $inventory = new PersonalDataInventory(
            $this->consentRepository,
            [$contributor1, $contributor2],
            $this->logger,
        );

        $result = $inventory->exportCustomerData([
            'customerId' => self::CUSTOMER_ID,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertArrayHasKey('customer', $result);
        self::assertArrayHasKey('orders', $result);
        self::assertSame(self::CUSTOMER_ID, $result['customer']['id']);
    }

    public function testExportCustomerDataEnrichesContextWithEmail(): void
    {
        // Customer contributor returns email - it should be passed to subsequent contributors
        $contributor1 = $this->createMock(PersonalDataContributorInterface::class);
        $contributor1->method('contextName')->willReturn('customer');
        $contributor1->method('collectPersonalData')->willReturn([
            'id' => self::CUSTOMER_ID,
            'email' => 'john@example.com',
        ]);

        $capturedContext = null;
        $contributor2 = $this->createMock(PersonalDataContributorInterface::class);
        $contributor2->method('contextName')->willReturn('orders');
        $contributor2
            ->method('collectPersonalData')
            ->willReturnCallback(function (array $context) use (&$capturedContext) {
                $capturedContext = $context;

                return [['id' => 'order-1']];
            });

        $this->consentRepository
            ->method('findByCustomerId')
            ->willReturn([]);

        $inventory = new PersonalDataInventory(
            $this->consentRepository,
            [$contributor1, $contributor2],
            $this->logger,
        );

        $inventory->exportCustomerData([
            'customerId' => self::CUSTOMER_ID,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertNotNull($capturedContext);
        self::assertArrayHasKey('email', $capturedContext);
        self::assertSame('john@example.com', $capturedContext['email']);
    }

    public function testExportCustomerDataIncludesConsentsAndMetadata(): void
    {
        $consent = $this->buildConsent(
            purpose: ConsentPurpose::marketing(),
            isGranted: true,
        );

        $this->consentRepository
            ->expects(self::once())
            ->method('findByCustomerId')
            ->with(self::callback(fn (CustomerId $id) => self::CUSTOMER_ID === $id->toString()))
            ->willReturn([$consent]);

        $inventory = new PersonalDataInventory(
            $this->consentRepository,
            [],
            $this->logger,
        );

        $result = $inventory->exportCustomerData([
            'customerId' => self::CUSTOMER_ID,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertArrayHasKey('consents', $result);
        self::assertArrayHasKey('metadata', $result);
        self::assertCount(1, $result['consents']);

        $consentData = $result['consents'][0];
        self::assertArrayHasKey('id', $consentData);
        self::assertArrayHasKey('purpose', $consentData);
        self::assertArrayHasKey('isGranted', $consentData);
        self::assertArrayHasKey('grantedAt', $consentData);
        self::assertArrayHasKey('withdrawnAt', $consentData);
        self::assertArrayHasKey('consentVersion', $consentData);
        self::assertArrayHasKey('ipAddress', $consentData);
        self::assertSame('marketing', $consentData['purpose']);
        self::assertTrue($consentData['isGranted']);
        self::assertNull($consentData['withdrawnAt']);

        self::assertArrayHasKey('exportDate', $result['metadata']);
        self::assertArrayHasKey('dataCategories', $result['metadata']);
        self::assertArrayHasKey('retentionPolicies', $result['metadata']);
        self::assertArrayHasKey('format', $result['metadata']);
        self::assertArrayHasKey('version', $result['metadata']);
    }

    public function testExportCustomerDataReturnsErrorWhenMissingCustomerId(): void
    {
        $inventory = new PersonalDataInventory(
            $this->consentRepository,
            [],
            $this->logger,
        );

        $result = $inventory->exportCustomerData([
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertArrayHasKey('error', $result);
        self::assertSame('Customer ID is required', $result['error']);
    }

    public function testAnonymizeCustomerDataIsNoOp(): void
    {
        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('deprecated'),
                self::arrayHasKey('customerId'),
            );

        $inventory = new PersonalDataInventory(
            $this->consentRepository,
            [],
            $this->logger,
        );

        // Should not throw any exception
        $inventory->anonymizeCustomerData([
            'customerId' => self::CUSTOMER_ID,
        ]);
    }

    public function testCanDeleteReturnsFalseIfAnyContributorSaysNo(): void
    {
        $contributor1 = $this->createMock(PersonalDataContributorInterface::class);
        $contributor1->method('canDeleteData')->willReturn(true);

        $contributor2 = $this->createMock(PersonalDataContributorInterface::class);
        $contributor2->method('canDeleteData')->willReturn(false);

        $contributor3 = $this->createMock(PersonalDataContributorInterface::class);
        // contributor3 should not be called once contributor2 returns false
        $contributor3->expects(self::never())->method('canDeleteData');

        $inventory = new PersonalDataInventory(
            $this->consentRepository,
            [$contributor1, $contributor2, $contributor3],
            $this->logger,
        );

        $result = $inventory->canDeleteCustomerData([
            'customerId' => self::CUSTOMER_ID,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertFalse($result);
    }

    public function testCanDeleteReturnsTrueIfAllContributorsSayYes(): void
    {
        $contributor1 = $this->createMock(PersonalDataContributorInterface::class);
        $contributor1->method('canDeleteData')->willReturn(true);

        $contributor2 = $this->createMock(PersonalDataContributorInterface::class);
        $contributor2->method('canDeleteData')->willReturn(true);

        $inventory = new PersonalDataInventory(
            $this->consentRepository,
            [$contributor1, $contributor2],
            $this->logger,
        );

        $result = $inventory->canDeleteCustomerData([
            'customerId' => self::CUSTOMER_ID,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertTrue($result);
    }

    public function testCanDeleteReturnsTrueWithNoContributors(): void
    {
        $inventory = new PersonalDataInventory(
            $this->consentRepository,
            [],
            $this->logger,
        );

        self::assertTrue($inventory->canDeleteCustomerData([
            'customerId' => self::CUSTOMER_ID,
        ]));
    }

    public function testExportDoesNotIncludeEmptyContributorData(): void
    {
        $contributor = $this->createMock(PersonalDataContributorInterface::class);
        $contributor->method('contextName')->willReturn('orders');
        $contributor->method('collectPersonalData')->willReturn([]);

        $this->consentRepository
            ->method('findByCustomerId')
            ->willReturn([]);

        $inventory = new PersonalDataInventory(
            $this->consentRepository,
            [$contributor],
            $this->logger,
        );

        $result = $inventory->exportCustomerData([
            'customerId' => self::CUSTOMER_ID,
            'tenantId' => self::TENANT_ID,
        ]);

        // Empty contributor result is not added to context data
        self::assertArrayNotHasKey('orders', $result);
        // But consents and metadata are always present
        self::assertArrayHasKey('consents', $result);
        self::assertArrayHasKey('metadata', $result);
    }

    public function testGetDataCategoriesReturnsAllExpectedCategories(): void
    {
        $inventory = new PersonalDataInventory(
            $this->consentRepository,
            [],
            $this->logger,
        );

        $categories = $inventory->getDataCategories();

        self::assertArrayHasKey('identity', $categories);
        self::assertArrayHasKey('contact', $categories);
        self::assertArrayHasKey('transaction', $categories);
        self::assertArrayHasKey('behavioral', $categories);
        self::assertArrayHasKey('consent', $categories);
        self::assertArrayHasKey('authentication', $categories);

        foreach ($categories as $key => $cat) {
            self::assertArrayHasKey('category', $cat, "Category '{$key}' missing 'category' field");
            self::assertArrayHasKey('description', $cat, "Category '{$key}' missing 'description' field");
            self::assertArrayHasKey('retention_period', $cat, "Category '{$key}' missing 'retention_period' field");
            self::assertArrayHasKey('lawful_basis', $cat, "Category '{$key}' missing 'lawful_basis' field");
            self::assertArrayHasKey('contexts', $cat, "Category '{$key}' missing 'contexts' field");
        }
    }

    public function testGetProcessingPurposesReturnsAllExpectedPurposes(): void
    {
        $inventory = new PersonalDataInventory(
            $this->consentRepository,
            [],
            $this->logger,
        );

        $purposes = $inventory->getProcessingPurposes();

        self::assertArrayHasKey('contract_performance', $purposes);
        self::assertArrayHasKey('legal_obligation', $purposes);
        self::assertArrayHasKey('marketing', $purposes);
        self::assertArrayHasKey('analytics', $purposes);
        self::assertArrayHasKey('profiling', $purposes);
        self::assertArrayHasKey('legitimate_interest', $purposes);

        // Marketing, analytics, profiling require consent
        self::assertTrue($purposes['marketing']['requires_consent']);
        self::assertTrue($purposes['analytics']['requires_consent']);
        self::assertTrue($purposes['profiling']['requires_consent']);

        // Contract performance, legal obligation, legitimate interest do NOT require consent
        self::assertFalse($purposes['contract_performance']['requires_consent']);
        self::assertFalse($purposes['legal_obligation']['requires_consent']);
        self::assertFalse($purposes['legitimate_interest']['requires_consent']);
    }

    private function buildConsent(ConsentPurpose $purpose, bool $isGranted): Consent
    {
        $consentText = 'I consent to the processing of my personal data for the purpose of '
            .'marketing communications as described in the privacy policy.';

        return Consent::reconstituteFromPersistence(
            id: ConsentId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerId: CustomerId::fromString(self::CUSTOMER_ID),
            purpose: $purpose,
            isGranted: $isGranted,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0 (Test)',
            consentText: $consentText,
            consentVersion: 'v1.0.0',
            grantedAt: $isGranted ? new \DateTimeImmutable() : null,
            withdrawnAt: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
