<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\DTO;

use App\Customer\Application\DTO\ConsentHistoryDTO;
use App\Customer\Application\DTO\CustomerConsentsDTO;
use App\Customer\Application\DTO\CustomerPreferencesDTO;
use App\Customer\Application\DTO\DataExportStatusDTO;
use App\Customer\Application\DTO\DeletionRequestStatusDTO;
use App\Customer\Application\DTO\ImportRowError;
use App\Customer\Application\DTO\LoyaltyPointTransactionDTO;
use App\Customer\Application\DTO\LoyaltyProgramDTO;
use App\Customer\Application\DTO\LoyaltyTierDTO;
use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Model\LoyaltyPointTransaction;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Model\LoyaltyTier;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerPreferences;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Customer\Domain\ValueObject\TransactionType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsentHistoryDTO::class)]
#[CoversClass(CustomerConsentsDTO::class)]
#[CoversClass(CustomerPreferencesDTO::class)]
#[CoversClass(DataExportStatusDTO::class)]
#[CoversClass(DeletionRequestStatusDTO::class)]
#[CoversClass(ImportRowError::class)]
#[CoversClass(LoyaltyPointTransactionDTO::class)]
#[CoversClass(LoyaltyProgramDTO::class)]
#[CoversClass(LoyaltyTierDTO::class)]
final class CustomerDTOsAdditionalTest extends TestCase
{
    // -----------------------------------------------------------------------
    // ConsentHistoryDTO
    // -----------------------------------------------------------------------

    #[Test]
    public function consentHistoryDtoHoldsAllFields(): void
    {
        $createdAt = new \DateTimeImmutable('2025-03-01T10:00:00+00:00');

        $dto = new ConsentHistoryDTO(
            id: 'history-1',
            consentType: 'marketing_email',
            consentTypeLabel: 'Marketing Email',
            granted: true,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
            createdAt: $createdAt,
        );

        self::assertSame('history-1', $dto->id);
        self::assertSame('marketing_email', $dto->consentType);
        self::assertSame('Marketing Email', $dto->consentTypeLabel);
        self::assertTrue($dto->granted);
        self::assertSame('192.168.1.1', $dto->ipAddress);
        self::assertSame('Mozilla/5.0', $dto->userAgent);
        self::assertSame($createdAt, $dto->createdAt);
    }

    #[Test]
    public function consentHistoryDtoSupportsNullOptionals(): void
    {
        $dto = new ConsentHistoryDTO(
            id: 'history-2',
            consentType: 'analytics',
            consentTypeLabel: 'Analytics Tracking',
            granted: false,
            ipAddress: null,
            userAgent: null,
            createdAt: new \DateTimeImmutable(),
        );

        self::assertNull($dto->ipAddress);
        self::assertNull($dto->userAgent);
        self::assertFalse($dto->granted);
    }

    // -----------------------------------------------------------------------
    // CustomerConsentsDTO
    // -----------------------------------------------------------------------

    #[Test]
    public function customerConsentsDtoHoldsAllConsents(): void
    {
        $updatedAt = new \DateTimeImmutable('2025-01-15T08:30:00+00:00');

        $dto = new CustomerConsentsDTO(
            customerId: 'cust-abc',
            marketingEmail: true,
            marketingSms: false,
            thirdPartySharing: false,
            analyticsTracking: true,
            lastUpdatedAt: $updatedAt,
        );

        self::assertSame('cust-abc', $dto->customerId);
        self::assertTrue($dto->marketingEmail);
        self::assertFalse($dto->marketingSms);
        self::assertFalse($dto->thirdPartySharing);
        self::assertTrue($dto->analyticsTracking);
        self::assertSame($updatedAt, $dto->lastUpdatedAt);
    }

    #[Test]
    public function customerConsentsDtoWithAllConsentsGranted(): void
    {
        $dto = new CustomerConsentsDTO(
            customerId: 'cust-1',
            marketingEmail: true,
            marketingSms: true,
            thirdPartySharing: true,
            analyticsTracking: true,
            lastUpdatedAt: new \DateTimeImmutable(),
        );

        self::assertTrue($dto->marketingEmail);
        self::assertTrue($dto->marketingSms);
        self::assertTrue($dto->thirdPartySharing);
        self::assertTrue($dto->analyticsTracking);
    }

    #[Test]
    public function customerConsentsDtoWithAllConsentsRevoked(): void
    {
        $dto = new CustomerConsentsDTO(
            customerId: 'cust-2',
            marketingEmail: false,
            marketingSms: false,
            thirdPartySharing: false,
            analyticsTracking: false,
            lastUpdatedAt: new \DateTimeImmutable(),
        );

        self::assertFalse($dto->marketingEmail);
        self::assertFalse($dto->marketingSms);
        self::assertFalse($dto->thirdPartySharing);
        self::assertFalse($dto->analyticsTracking);
    }

    // -----------------------------------------------------------------------
    // CustomerPreferencesDTO
    // -----------------------------------------------------------------------

    #[Test]
    public function customerPreferencesDtoHoldsAllFields(): void
    {
        $dto = new CustomerPreferencesDTO(
            preferredLanguage: 'fr',
            preferredCurrency: 'EUR',
            newsletterSubscribed: true,
            marketingEmailsAllowed: false,
            smsNotificationsAllowed: true,
            orderNotificationsEnabled: true,
            promotionNotificationsEnabled: false,
        );

        self::assertSame('fr', $dto->preferredLanguage);
        self::assertSame('EUR', $dto->preferredCurrency);
        self::assertTrue($dto->newsletterSubscribed);
        self::assertFalse($dto->marketingEmailsAllowed);
        self::assertTrue($dto->smsNotificationsAllowed);
        self::assertTrue($dto->orderNotificationsEnabled);
        self::assertFalse($dto->promotionNotificationsEnabled);
    }

    #[Test]
    public function customerPreferencesDtoFromPreferences(): void
    {
        $preferences = CustomerPreferences::create(
            preferredLanguage: 'de',
            preferredCurrency: 'EUR',
            newsletterSubscribed: false,
            marketingEmailsAllowed: false,
            smsNotificationsAllowed: false,
            orderNotificationsEnabled: true,
            promotionNotificationsEnabled: false,
        );

        $dto = CustomerPreferencesDTO::fromPreferences($preferences);

        self::assertSame('de', $dto->preferredLanguage);
        self::assertSame('EUR', $dto->preferredCurrency);
        self::assertFalse($dto->newsletterSubscribed);
        self::assertFalse($dto->marketingEmailsAllowed);
        self::assertFalse($dto->smsNotificationsAllowed);
        self::assertTrue($dto->orderNotificationsEnabled);
        self::assertFalse($dto->promotionNotificationsEnabled);
    }

    #[Test]
    public function customerPreferencesDtoFromPreferencesWithAllOptedIn(): void
    {
        $preferences = CustomerPreferences::create(
            preferredLanguage: 'en',
            preferredCurrency: 'USD',
            newsletterSubscribed: true,
            marketingEmailsAllowed: true,
            smsNotificationsAllowed: true,
            orderNotificationsEnabled: true,
            promotionNotificationsEnabled: true,
        );

        $dto = CustomerPreferencesDTO::fromPreferences($preferences);

        self::assertSame('en', $dto->preferredLanguage);
        self::assertSame('USD', $dto->preferredCurrency);
        self::assertTrue($dto->newsletterSubscribed);
        self::assertTrue($dto->marketingEmailsAllowed);
        self::assertTrue($dto->smsNotificationsAllowed);
        self::assertTrue($dto->orderNotificationsEnabled);
        self::assertTrue($dto->promotionNotificationsEnabled);
    }

    // -----------------------------------------------------------------------
    // DataExportStatusDTO
    // -----------------------------------------------------------------------

    #[Test]
    public function dataExportStatusDtoWithAllFields(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $completedAt = new \DateTimeImmutable('2025-01-01T01:00:00+00:00');
        $expiresAt = new \DateTimeImmutable('2025-01-08T01:00:00+00:00');

        $dto = new DataExportStatusDTO(
            id: 'export-1',
            status: 'completed',
            expiresAt: $expiresAt,
            createdAt: $createdAt,
            completedAt: $completedAt,
            isDownloadable: true,
        );

        self::assertSame('export-1', $dto->id);
        self::assertSame('completed', $dto->status);
        self::assertSame($expiresAt, $dto->expiresAt);
        self::assertSame($createdAt, $dto->createdAt);
        self::assertSame($completedAt, $dto->completedAt);
        self::assertTrue($dto->isDownloadable);
        self::assertNull($dto->errorMessage);
    }

    #[Test]
    public function dataExportStatusDtoWithNullOptionals(): void
    {
        $dto = new DataExportStatusDTO(
            id: 'export-2',
            status: 'pending',
            expiresAt: null,
            createdAt: new \DateTimeImmutable(),
            completedAt: null,
            isDownloadable: false,
        );

        self::assertNull($dto->expiresAt);
        self::assertNull($dto->completedAt);
        self::assertFalse($dto->isDownloadable);
        self::assertNull($dto->errorMessage);
    }

    #[Test]
    public function dataExportStatusDtoWithErrorMessage(): void
    {
        $dto = new DataExportStatusDTO(
            id: 'export-3',
            status: 'failed',
            expiresAt: null,
            createdAt: new \DateTimeImmutable(),
            completedAt: null,
            isDownloadable: false,
            errorMessage: 'Export processing failed due to timeout',
        );

        self::assertSame('failed', $dto->status);
        self::assertSame('Export processing failed due to timeout', $dto->errorMessage);
        self::assertFalse($dto->isDownloadable);
    }

    // -----------------------------------------------------------------------
    // DeletionRequestStatusDTO
    // -----------------------------------------------------------------------

    #[Test]
    public function deletionRequestStatusDtoHoldsAllFields(): void
    {
        $dto = new DeletionRequestStatusDTO(
            id: 'del-1',
            customerId: 'cust-1',
            status: 'pending',
            statusLabel: 'Pending Confirmation',
            reason: 'No longer needed',
            holdReason: null,
            scheduledFor: '2025-04-01T00:00:00+00:00',
            confirmedAt: null,
            completedAt: null,
            createdAt: '2025-03-01T00:00:00+00:00',
            canBeCancelled: true,
            isOnHold: false,
            canBeExecuted: false,
        );

        self::assertSame('del-1', $dto->id);
        self::assertSame('cust-1', $dto->customerId);
        self::assertSame('pending', $dto->status);
        self::assertSame('Pending Confirmation', $dto->statusLabel);
        self::assertSame('No longer needed', $dto->reason);
        self::assertNull($dto->holdReason);
        self::assertTrue($dto->canBeCancelled);
        self::assertFalse($dto->isOnHold);
        self::assertFalse($dto->canBeExecuted);
    }

    #[Test]
    public function deletionRequestStatusDtoFromDomainModel(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $requestId = DeletionRequestId::generate();

        $request = DeletionRequest::create(
            id: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            reason: 'GDPR erasure request',
        );

        $dto = DeletionRequestStatusDTO::fromDomainModel($request);

        self::assertSame($requestId->toString(), $dto->id);
        self::assertSame($customerId->toString(), $dto->customerId);
        self::assertSame('pending', $dto->status);
        self::assertSame('Pending Confirmation', $dto->statusLabel);
        self::assertSame('GDPR erasure request', $dto->reason);
        self::assertNull($dto->holdReason);
        self::assertNull($dto->confirmedAt);
        self::assertNull($dto->completedAt);
        self::assertTrue($dto->canBeCancelled);
        self::assertFalse($dto->isOnHold);
        self::assertFalse($dto->canBeExecuted);
    }

    #[Test]
    public function deletionRequestStatusDtoToArray(): void
    {
        $dto = new DeletionRequestStatusDTO(
            id: 'del-2',
            customerId: 'cust-2',
            status: 'confirmed',
            statusLabel: 'Confirmed (Scheduled)',
            reason: 'Account closure',
            holdReason: null,
            scheduledFor: '2025-05-01T00:00:00+00:00',
            confirmedAt: '2025-04-01T12:00:00+00:00',
            completedAt: null,
            createdAt: '2025-04-01T10:00:00+00:00',
            canBeCancelled: true,
            isOnHold: false,
            canBeExecuted: false,
        );

        $array = $dto->toArray();

        self::assertSame('del-2', $array['id']);
        self::assertSame('cust-2', $array['customerId']);
        self::assertSame('confirmed', $array['status']);
        self::assertSame('Confirmed (Scheduled)', $array['statusLabel']);
        self::assertSame('Account closure', $array['reason']);
        self::assertNull($array['holdReason']);
        self::assertSame('2025-05-01T00:00:00+00:00', $array['scheduledFor']);
        self::assertSame('2025-04-01T12:00:00+00:00', $array['confirmedAt']);
        self::assertNull($array['completedAt']);
        self::assertTrue($array['canBeCancelled']);
        self::assertFalse($array['isOnHold']);
        self::assertFalse($array['canBeExecuted']);
    }

    // -----------------------------------------------------------------------
    // ImportRowError
    // -----------------------------------------------------------------------

    #[Test]
    public function importRowErrorHoldsFields(): void
    {
        $error = new ImportRowError(
            row: 5,
            field: 'email',
            message: 'Invalid email format',
            value: 'not-an-email',
        );

        self::assertSame(5, $error->row);
        self::assertSame('email', $error->field);
        self::assertSame('Invalid email format', $error->message);
        self::assertSame('not-an-email', $error->value);
    }

    #[Test]
    public function importRowErrorToStringFormatsCorrectly(): void
    {
        $error = new ImportRowError(
            row: 10,
            field: 'firstName',
            message: 'Field is required',
            value: null,
        );

        $string = $error->toString();

        self::assertStringContainsString('Row 10', $string);
        self::assertStringContainsString('firstName', $string);
        self::assertStringContainsString('Field is required', $string);
        self::assertStringContainsString('null', $string);
    }

    #[Test]
    public function importRowErrorToStringWithStringValue(): void
    {
        $error = new ImportRowError(row: 3, field: 'phone', message: 'Too short', value: '123');

        self::assertStringContainsString('123', $error->toString());
    }

    #[Test]
    public function importRowErrorToStringWithBooleanValue(): void
    {
        $trueError = new ImportRowError(row: 1, field: 'active', message: 'Unexpected', value: true);
        $falseError = new ImportRowError(row: 2, field: 'active', message: 'Unexpected', value: false);

        self::assertStringContainsString('true', $trueError->toString());
        self::assertStringContainsString('false', $falseError->toString());
    }

    #[Test]
    public function importRowErrorToStringWithArrayValue(): void
    {
        $error = new ImportRowError(row: 7, field: 'tags', message: 'Invalid', value: ['a', 'b']);

        $string = $error->toString();

        self::assertStringContainsString('Row 7', $string);
        // JSON-encoded array should appear in output
        self::assertStringContainsString('"a"', $string);
    }

    #[Test]
    public function importRowErrorToStringWithIntegerValue(): void
    {
        $error = new ImportRowError(row: 2, field: 'age', message: 'Out of range', value: 200);

        self::assertStringContainsString('200', $error->toString());
    }

    // -----------------------------------------------------------------------
    // LoyaltyPointTransactionDTO
    // -----------------------------------------------------------------------

    #[Test]
    public function loyaltyPointTransactionDtoHoldsAllFields(): void
    {
        $dto = new LoyaltyPointTransactionDTO(
            id: 'txn-1',
            customerId: 'cust-1',
            tenantId: '00000000-0000-4000-8000-000000000001',
            type: 'earned',
            typeLabel: 'Points Earned',
            points: 500,
            balanceAfter: 1500,
            reason: 'Points earned from order',
            orderId: 'order-123',
            expiresAt: null,
            createdAt: '2025-03-01T10:00:00+00:00',
            isDebit: false,
            isCredit: true,
        );

        self::assertSame('txn-1', $dto->id);
        self::assertSame('cust-1', $dto->customerId);
        self::assertSame('earned', $dto->type);
        self::assertSame('Points Earned', $dto->typeLabel);
        self::assertSame(500, $dto->points);
        self::assertSame(1500, $dto->balanceAfter);
        self::assertSame('order-123', $dto->orderId);
        self::assertNull($dto->expiresAt);
        self::assertFalse($dto->isDebit);
        self::assertTrue($dto->isCredit);
    }

    #[Test]
    public function loyaltyPointTransactionDtoFromDomainModel(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $transaction = LoyaltyPointTransaction::create(
            customerId: $customerId,
            tenantId: $tenantId,
            type: TransactionType::EARNED,
            points: 250,
            balanceAfter: 750,
            reason: 'Earned from purchase',
            orderId: 'order-456',
        );

        $dto = LoyaltyPointTransactionDTO::fromDomainModel($transaction);

        self::assertSame($customerId->toString(), $dto->customerId);
        self::assertSame($tenantId->toString(), $dto->tenantId);
        self::assertSame('earned', $dto->type);
        self::assertSame('Points Earned', $dto->typeLabel);
        self::assertSame(250, $dto->points);
        self::assertSame(750, $dto->balanceAfter);
        self::assertSame('Earned from purchase', $dto->reason);
        self::assertSame('order-456', $dto->orderId);
        self::assertNull($dto->expiresAt);
        self::assertFalse($dto->isDebit);
        self::assertTrue($dto->isCredit);
    }

    #[Test]
    public function loyaltyPointTransactionDtoFromDomainModelWithDebitType(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $transaction = LoyaltyPointTransaction::create(
            customerId: $customerId,
            tenantId: $tenantId,
            type: TransactionType::REDEEMED,
            points: -100,
            balanceAfter: 400,
            reason: 'Redeemed for discount',
        );

        $dto = LoyaltyPointTransactionDTO::fromDomainModel($transaction);

        self::assertSame('redeemed', $dto->type);
        self::assertSame('Points Redeemed', $dto->typeLabel);
        self::assertSame(-100, $dto->points);
        self::assertTrue($dto->isDebit);
        self::assertFalse($dto->isCredit);
    }

    #[Test]
    public function loyaltyPointTransactionDtoFromDomainModelWithExpiry(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $expiresAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');

        $transaction = LoyaltyPointTransaction::create(
            customerId: $customerId,
            tenantId: $tenantId,
            type: TransactionType::BONUS,
            points: 1000,
            balanceAfter: 2000,
            reason: 'Birthday bonus',
            expiresAt: $expiresAt,
        );

        $dto = LoyaltyPointTransactionDTO::fromDomainModel($transaction);

        self::assertNotNull($dto->expiresAt);
        self::assertStringContainsString('2026-01-01', $dto->expiresAt);
        self::assertTrue($dto->isCredit);
        self::assertFalse($dto->isDebit);
    }

    #[Test]
    public function loyaltyPointTransactionDtoToArray(): void
    {
        $dto = new LoyaltyPointTransactionDTO(
            id: 'txn-arr',
            customerId: 'cust-arr',
            tenantId: 't-1',
            type: 'adjustment',
            typeLabel: 'Manual Adjustment',
            points: 50,
            balanceAfter: 550,
            reason: 'Manual correction',
            orderId: null,
            expiresAt: null,
            createdAt: '2025-02-15T09:00:00+00:00',
            isDebit: false,
            isCredit: true,
        );

        $array = $dto->toArray();

        self::assertSame('txn-arr', $array['id']);
        self::assertSame('cust-arr', $array['customerId']);
        self::assertSame('adjustment', $array['type']);
        self::assertSame('Manual Adjustment', $array['typeLabel']);
        self::assertSame(50, $array['points']);
        self::assertSame(550, $array['balanceAfter']);
        self::assertSame('Manual correction', $array['reason']);
        self::assertNull($array['orderId']);
        self::assertNull($array['expiresAt']);
        self::assertFalse($array['isDebit']);
        self::assertTrue($array['isCredit']);
    }

    // -----------------------------------------------------------------------
    // LoyaltyTierDTO
    // -----------------------------------------------------------------------

    #[Test]
    public function loyaltyTierDtoHoldsAllFields(): void
    {
        $dto = new LoyaltyTierDTO(
            id: 'tier-1',
            name: 'Gold',
            threshold: 1000,
            discountPercentage: 10,
            freeShippingMinOrder: ['amount' => 5000, 'currency' => 'USD'],
            sortOrder: 2,
        );

        self::assertSame('tier-1', $dto->id);
        self::assertSame('Gold', $dto->name);
        self::assertSame(1000, $dto->threshold);
        self::assertSame(10, $dto->discountPercentage);
        self::assertNotNull($dto->freeShippingMinOrder);
        self::assertSame(2, $dto->sortOrder);
    }

    #[Test]
    public function loyaltyTierDtoWithNullFreeShipping(): void
    {
        $dto = new LoyaltyTierDTO(
            id: 'tier-2',
            name: 'Bronze',
            threshold: 100,
            discountPercentage: 2,
            freeShippingMinOrder: null,
            sortOrder: 0,
        );

        self::assertNull($dto->freeShippingMinOrder);
        self::assertSame(0, $dto->sortOrder);
    }

    #[Test]
    public function loyaltyTierDtoFromDomainModel(): void
    {
        $tierId = LoyaltyTierId::generate();
        $tier = LoyaltyTier::create(
            id: $tierId,
            name: 'Silver',
            threshold: 500,
            discountPercentage: 5,
            freeShippingMinOrder: Money::of('25.00', 'USD'),
            sortOrder: 1,
        );

        $dto = LoyaltyTierDTO::fromDomainModel($tier);

        self::assertSame($tierId->toString(), $dto->id);
        self::assertSame('Silver', $dto->name);
        self::assertSame(500, $dto->threshold);
        self::assertSame(5, $dto->discountPercentage);
        self::assertNotNull($dto->freeShippingMinOrder);
        self::assertSame(1, $dto->sortOrder);
    }

    #[Test]
    public function loyaltyTierDtoFromDomainModelWithoutFreeShipping(): void
    {
        $tier = LoyaltyTier::create(
            id: LoyaltyTierId::generate(),
            name: 'Platinum',
            threshold: 5000,
            discountPercentage: 20,
            freeShippingMinOrder: null,
            sortOrder: 3,
        );

        $dto = LoyaltyTierDTO::fromDomainModel($tier);

        self::assertNull($dto->freeShippingMinOrder);
        self::assertSame(20, $dto->discountPercentage);
        self::assertSame(3, $dto->sortOrder);
    }

    // -----------------------------------------------------------------------
    // LoyaltyProgramDTO
    // -----------------------------------------------------------------------

    #[Test]
    public function loyaltyProgramDtoFromDomainModel(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'Rewards Club',
            earningRate: EarningRate::fromFloat(1.5),
            minOrderValue: Money::of('20.00', 'USD'),
            redemptionRule: RedemptionRule::create(conversionRate: 100.0, minPointsToRedeem: 50),
        );

        $dto = LoyaltyProgramDTO::fromDomainModel($program);

        self::assertSame($tenantId->toString(), $dto->tenantId);
        self::assertSame('Rewards Club', $dto->name);
        self::assertNull($dto->description);
        self::assertSame(1.5, $dto->earningRate);
        self::assertTrue($dto->isActive);
        self::assertIsArray($dto->minOrderValue);
        self::assertIsArray($dto->redemptionRule);
        self::assertNull($dto->validityDays);
        self::assertSame([], $dto->tiers);
        self::assertNotEmpty($dto->createdAt);
        self::assertNotEmpty($dto->updatedAt);
    }

    #[Test]
    public function loyaltyProgramDtoFromDomainModelWithTiers(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'VIP Rewards',
            earningRate: EarningRate::fromFloat(2.0),
            minOrderValue: Money::of('0.00', 'USD'),
            redemptionRule: RedemptionRule::create(conversionRate: 50.0, minPointsToRedeem: 100),
        );

        $bronzeTier = LoyaltyTier::create(LoyaltyTierId::generate(), 'Bronze', 100, 2, null, 0);
        $silverTier = LoyaltyTier::create(LoyaltyTierId::generate(), 'Silver', 500, 5, null, 1);
        $program->addTier($bronzeTier);
        $program->addTier($silverTier);

        $dto = LoyaltyProgramDTO::fromDomainModel($program);

        self::assertCount(2, $dto->tiers);
        self::assertContainsOnlyInstancesOf(LoyaltyTierDTO::class, $dto->tiers);
        self::assertSame('Bronze', $dto->tiers[0]->name);
        self::assertSame('Silver', $dto->tiers[1]->name);
    }

    #[Test]
    public function loyaltyProgramDtoCreatedAtAndUpdatedAtAreIso8601(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'Test Program',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('0.00', 'USD'),
            redemptionRule: RedemptionRule::create(conversionRate: 100.0, minPointsToRedeem: 0),
        );

        $dto = LoyaltyProgramDTO::fromDomainModel($program);

        // ISO 8601 / ATOM format includes T and timezone offset
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $dto->createdAt);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $dto->updatedAt);
    }

    #[Test]
    public function loyaltyProgramDtoReflectsInactiveProgram(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $program = LoyaltyProgram::create(
            tenantId: $tenantId,
            name: 'Old Program',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::of('0.00', 'USD'),
            redemptionRule: RedemptionRule::create(conversionRate: 100.0, minPointsToRedeem: 0),
        );
        $program->deactivate();

        $dto = LoyaltyProgramDTO::fromDomainModel($program);

        self::assertFalse($dto->isActive);
    }
}
