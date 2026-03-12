<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\DTO;

use App\Customer\Application\DTO\CustomerAddressDTO;
use App\Customer\Application\DTO\CustomerFilterDTO;
use App\Customer\Application\DTO\CustomerLoyaltyStatusDTO;
use App\Customer\Application\DTO\CustomerSummaryDTO;
use App\Customer\Application\DTO\ExportResult;
use App\Customer\Application\DTO\ImportResult;
use App\Customer\Application\DTO\NotificationPreferencesDTO;
use App\Customer\Application\DTO\RedeemPointsResult;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerAddressDTO::class)]
#[CoversClass(CustomerFilterDTO::class)]
#[CoversClass(CustomerLoyaltyStatusDTO::class)]
#[CoversClass(CustomerSummaryDTO::class)]
#[CoversClass(ExportResult::class)]
#[CoversClass(ImportResult::class)]
#[CoversClass(NotificationPreferencesDTO::class)]
#[CoversClass(RedeemPointsResult::class)]
final class CustomerDTOsTest extends TestCase
{
    // --- CustomerFilterDTO ---

    #[Test]
    public function filterDtoDefaults(): void
    {
        $dto = new CustomerFilterDTO();

        self::assertNull($dto->searchTerm);
        self::assertNull($dto->status);
        self::assertNull($dto->segment);
        self::assertNull($dto->registeredFrom);
        self::assertNull($dto->registeredTo);
        self::assertNull($dto->hasOrders);
        self::assertNull($dto->minOrderCount);
        self::assertNull($dto->maxOrderCount);
        self::assertSame('createdAt', $dto->sortBy);
        self::assertSame('DESC', $dto->sortOrder);
        self::assertSame(1, $dto->page);
        self::assertSame(20, $dto->limit);
    }

    #[Test]
    public function filterDtoFromArray(): void
    {
        $dto = CustomerFilterDTO::fromArray([
            'search' => 'john',
            'status' => 'active',
            'segment' => 'vip',
            'hasOrders' => 'true',
            'minOrderCount' => '5',
            'sortBy' => 'email',
            'sortOrder' => 'asc',
            'page' => '2',
            'limit' => '50',
        ]);

        self::assertSame('john', $dto->searchTerm);
        self::assertSame('active', $dto->status);
        self::assertSame('vip', $dto->segment);
        self::assertTrue($dto->hasOrders);
        self::assertSame(5, $dto->minOrderCount);
        self::assertSame('email', $dto->sortBy);
        self::assertSame('ASC', $dto->sortOrder);
        self::assertSame(2, $dto->page);
        self::assertSame(50, $dto->limit);
    }

    #[Test]
    public function filterDtoFromEmptyArray(): void
    {
        $dto = CustomerFilterDTO::fromArray([]);

        self::assertNull($dto->searchTerm);
        self::assertSame('createdAt', $dto->sortBy);
        self::assertSame('DESC', $dto->sortOrder);
        self::assertSame(1, $dto->page);
        self::assertSame(20, $dto->limit);
    }

    #[Test]
    public function filterDtoLimitsCappedAt100(): void
    {
        $dto = CustomerFilterDTO::fromArray(['limit' => '500']);
        self::assertSame(100, $dto->limit);
    }

    #[Test]
    public function filterDtoWithDates(): void
    {
        $dto = CustomerFilterDTO::fromArray([
            'registeredFrom' => '2025-01-01',
            'registeredTo' => '2025-12-31',
        ]);

        self::assertInstanceOf(\DateTimeImmutable::class, $dto->registeredFrom);
        self::assertInstanceOf(\DateTimeImmutable::class, $dto->registeredTo);
    }

    // --- CustomerSummaryDTO ---

    #[Test]
    public function summaryDtoConstruction(): void
    {
        $now = new \DateTimeImmutable();
        $spent = Money::of('500.00', 'USD');

        $dto = new CustomerSummaryDTO(
            id: 'cust-1',
            tenantId: 't-1',
            email: 'john@example.com',
            firstName: 'John',
            lastName: 'Doe',
            fullName: 'John Doe',
            phoneNumber: '+1234567890',
            segment: 'vip',
            loyaltyPoints: 1500,
            isActive: true,
            status: 'active',
            orderCount: 10,
            totalSpent: $spent,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertSame('cust-1', $dto->id);
        self::assertSame('john@example.com', $dto->email);
        self::assertSame('John Doe', $dto->fullName);
        self::assertSame(1500, $dto->loyaltyPoints);
        self::assertTrue($dto->isActive);
        self::assertSame(10, $dto->orderCount);
    }

    #[Test]
    public function summaryDtoToArray(): void
    {
        $now = new \DateTimeImmutable('2025-06-15T10:00:00+00:00');
        $spent = Money::of('250.00', 'EUR');

        $dto = new CustomerSummaryDTO(
            id: 'cust-2',
            tenantId: 't-1',
            email: 'jane@example.com',
            firstName: 'Jane',
            lastName: 'Smith',
            fullName: 'Jane Smith',
            phoneNumber: null,
            segment: 'regular',
            loyaltyPoints: 0,
            isActive: false,
            status: 'inactive',
            orderCount: 0,
            totalSpent: $spent,
            createdAt: $now,
            updatedAt: $now,
        );

        $array = $dto->toArray();

        self::assertSame('cust-2', $array['id']);
        self::assertSame('jane@example.com', $array['email']);
        self::assertSame('Jane Smith', $array['fullName']);
        self::assertNull($array['phoneNumber']);
        self::assertSame('regular', $array['segment']);
        self::assertFalse($array['isActive']);
        self::assertSame(25000, $array['totalSpent']);
        self::assertSame('EUR', $array['totalSpentCurrency']);
    }

    #[Test]
    public function summaryDtoToArrayWithNullSpent(): void
    {
        $now = new \DateTimeImmutable();
        $dto = new CustomerSummaryDTO(
            id: 'c-1',
            tenantId: 't-1',
            email: 'x@x.com',
            firstName: 'X',
            lastName: 'Y',
            fullName: 'X Y',
            phoneNumber: null,
            segment: 'regular',
            loyaltyPoints: 0,
            isActive: true,
            status: 'active',
            orderCount: 0,
            totalSpent: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $array = $dto->toArray();
        self::assertNull($array['totalSpent']);
        self::assertNull($array['totalSpentCurrency']);
    }

    // --- RedeemPointsResult ---

    #[Test]
    public function redeemPointsResult(): void
    {
        $result = new RedeemPointsResult(
            pointsRedeemed: 500,
            discountAmountMinorUnits: 500,
            discountCurrency: 'USD',
            remainingBalance: 1000,
        );

        self::assertSame(500, $result->pointsRedeemed);
        self::assertSame(500, $result->discountAmountMinorUnits);
        self::assertSame('USD', $result->discountCurrency);
        self::assertSame(1000, $result->remainingBalance);
    }

    #[Test]
    public function redeemPointsResultToArray(): void
    {
        $result = new RedeemPointsResult(
            pointsRedeemed: 200,
            discountAmountMinorUnits: 200,
            discountCurrency: 'EUR',
            remainingBalance: 300,
        );

        $array = $result->toArray();

        self::assertSame(200, $array['points_redeemed']);
        self::assertSame(200, $array['discount_amount']);
        self::assertSame('EUR', $array['discount_currency']);
        self::assertSame(300, $array['remaining_balance']);
    }

    // --- CustomerLoyaltyStatusDTO ---

    #[Test]
    public function loyaltyStatusDtoConstruction(): void
    {
        $dto = new CustomerLoyaltyStatusDTO(
            customerId: 'cust-1',
            currentPoints: 1500,
            currentTier: 'Gold',
            nextTier: 'Platinum',
            pointsToNextTier: 500,
            lifetimePointsEarned: 5000,
            lifetimePointsRedeemed: 3500,
            currentTierDiscount: 10,
            programName: 'Rewards Plus',
            programIsActive: true,
        );

        self::assertSame('cust-1', $dto->customerId);
        self::assertSame(1500, $dto->currentPoints);
        self::assertSame('Gold', $dto->currentTier);
        self::assertSame('Platinum', $dto->nextTier);
        self::assertSame(500, $dto->pointsToNextTier);
        self::assertSame(5000, $dto->lifetimePointsEarned);
        self::assertSame(3500, $dto->lifetimePointsRedeemed);
        self::assertSame(10, $dto->currentTierDiscount);
        self::assertTrue($dto->programIsActive);
    }

    #[Test]
    public function loyaltyStatusDtoToArray(): void
    {
        $dto = new CustomerLoyaltyStatusDTO(
            customerId: 'cust-2',
            currentPoints: 0,
            currentTier: null,
            nextTier: 'Bronze',
            pointsToNextTier: 100,
            lifetimePointsEarned: 0,
            lifetimePointsRedeemed: 0,
            currentTierDiscount: null,
            programName: 'Basic Loyalty',
            programIsActive: false,
        );

        $array = $dto->toArray();

        self::assertSame('cust-2', $array['customer_id']);
        self::assertSame(0, $array['current_points']);
        self::assertNull($array['current_tier']);
        self::assertSame('Bronze', $array['next_tier']);
        self::assertNull($array['current_tier_discount']);
        self::assertFalse($array['program_is_active']);
    }

    // --- NotificationPreferencesDTO ---

    #[Test]
    public function notificationPreferencesDtoConstruction(): void
    {
        $now = new \DateTimeImmutable();
        $dto = new NotificationPreferencesDTO(
            orderUpdates: true,
            shippingUpdates: true,
            promotionalOffers: false,
            priceDropAlerts: true,
            backInStockAlerts: false,
            securityAlerts: true,
            newsletterWeekly: false,
            preferSms: false,
            lastUpdatedAt: $now,
        );

        self::assertTrue($dto->orderUpdates);
        self::assertTrue($dto->shippingUpdates);
        self::assertFalse($dto->promotionalOffers);
        self::assertTrue($dto->priceDropAlerts);
        self::assertFalse($dto->backInStockAlerts);
        self::assertTrue($dto->securityAlerts);
        self::assertFalse($dto->newsletterWeekly);
        self::assertFalse($dto->preferSms);
        self::assertSame($now, $dto->lastUpdatedAt);
    }

    #[Test]
    public function notificationPreferencesDtoDefaultsNullTimestamp(): void
    {
        $dto = new NotificationPreferencesDTO(
            orderUpdates: true,
            shippingUpdates: true,
            promotionalOffers: true,
            priceDropAlerts: true,
            backInStockAlerts: true,
            securityAlerts: true,
            newsletterWeekly: true,
            preferSms: true,
        );

        self::assertNull($dto->lastUpdatedAt);
    }

    // --- CustomerAddressDTO ---

    #[Test]
    public function addressDtoConstruction(): void
    {
        $now = new \DateTimeImmutable();
        $dto = new CustomerAddressDTO(
            id: 'addr-1',
            customerId: 'cust-1',
            tenantId: 't-1',
            street: '123 Main St',
            street2: 'Apt 4',
            city: 'Springfield',
            state: 'IL',
            postalCode: '62701',
            country: 'US',
            type: 'shipping',
            isDefaultShipping: true,
            isDefaultBilling: false,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertSame('addr-1', $dto->id);
        self::assertSame('123 Main St', $dto->street);
        self::assertSame('Apt 4', $dto->street2);
        self::assertSame('Springfield', $dto->city);
        self::assertSame('IL', $dto->state);
        self::assertTrue($dto->isDefaultShipping);
        self::assertFalse($dto->isDefaultBilling);
    }

    #[Test]
    public function addressDtoToArray(): void
    {
        $dto = new CustomerAddressDTO(
            id: 'addr-2',
            customerId: 'cust-2',
            tenantId: 't-1',
            street: '456 Oak Ave',
            street2: null,
            city: 'Portland',
            state: null,
            postalCode: '97201',
            country: 'US',
            type: 'billing',
            isDefaultShipping: false,
            isDefaultBilling: true,
        );

        $array = $dto->toArray();

        self::assertSame('addr-2', $array['id']);
        self::assertSame('cust-2', $array['customerId']);
        self::assertSame('456 Oak Ave', $array['street']);
        self::assertNull($array['street2']);
        self::assertNull($array['state']);
        self::assertSame('billing', $array['type']);
        self::assertTrue($array['isDefaultBilling']);
        self::assertNull($array['createdAt']);
        self::assertNull($array['updatedAt']);
    }

    // --- ImportResult ---

    #[Test]
    public function importResultSuccessful(): void
    {
        $result = new ImportResult(
            totalRows: 100,
            imported: 90,
            updated: 10,
            skipped: 0,
            errors: [],
        );

        self::assertTrue($result->isSuccessful());
        self::assertFalse($result->hasPartialSuccess());
        self::assertSame(0, $result->errorCount());
    }

    #[Test]
    public function importResultPartialSuccess(): void
    {
        $result = new ImportResult(
            totalRows: 100,
            imported: 80,
            updated: 5,
            skipped: 10,
            errors: [5 => 'Invalid email', 15 => 'Duplicate'],
        );

        self::assertFalse($result->isSuccessful());
        self::assertTrue($result->hasPartialSuccess());
        self::assertSame(2, $result->errorCount());
    }

    #[Test]
    public function importResultAllFailed(): void
    {
        $result = new ImportResult(
            totalRows: 5,
            imported: 0,
            updated: 0,
            skipped: 5,
            errors: [1 => 'Bad data'],
        );

        self::assertFalse($result->isSuccessful());
        self::assertFalse($result->hasPartialSuccess());
    }

    #[Test]
    public function importResultToArray(): void
    {
        $result = new ImportResult(
            totalRows: 50,
            imported: 40,
            updated: 5,
            skipped: 3,
            errors: [10 => 'Error'],
        );

        $array = $result->toArray();

        self::assertSame(50, $array['total_rows']);
        self::assertSame(40, $array['imported']);
        self::assertSame(5, $array['updated']);
        self::assertSame(3, $array['skipped']);
        self::assertSame(1, $array['error_count']);
        self::assertFalse($array['is_successful']);
        self::assertTrue($array['has_partial_success']);
    }

    // --- ExportResult ---

    #[Test]
    public function exportResultCsv(): void
    {
        $result = ExportResult::csv('id,name\n1,John');

        self::assertSame('id,name\n1,John', $result->content);
        self::assertSame('text/csv', $result->mimeType);
        self::assertStringContainsString('.csv', $result->filename);
        self::assertStringContainsString('customers-export-', $result->filename);
    }

    #[Test]
    public function exportResultJson(): void
    {
        $result = ExportResult::json('[{"id": 1}]');

        self::assertSame('[{"id": 1}]', $result->content);
        self::assertSame('application/json', $result->mimeType);
        self::assertStringContainsString('.json', $result->filename);
    }
}
