<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Model\ConsentHistory;
use App\Customer\Domain\ValueObject\ConsentHistoryId;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsentHistory::class)]
final class ConsentHistoryExtendedTest extends TestCase
{
    private CustomerId $customerId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->customerId = CustomerId::generate();
        $this->tenantId = TenantId::generate();
    }

    // -----------------------------------------------------------------------
    // All ConsentType variants
    // -----------------------------------------------------------------------

    #[Test]
    public function itRecordsMarketingEmailConsent(): void
    {
        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true,
        );

        self::assertSame(ConsentType::MARKETING_EMAIL, $history->consentType());
        self::assertTrue($history->granted());
    }

    #[Test]
    public function itRecordsMarketingSmsConsent(): void
    {
        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::MARKETING_SMS,
            granted: false,
        );

        self::assertSame(ConsentType::MARKETING_SMS, $history->consentType());
        self::assertFalse($history->granted());
    }

    #[Test]
    public function itRecordsThirdPartySharingConsent(): void
    {
        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::THIRD_PARTY_SHARING,
            granted: true,
        );

        self::assertSame(ConsentType::THIRD_PARTY_SHARING, $history->consentType());
    }

    #[Test]
    public function itRecordsAnalyticsTrackingConsent(): void
    {
        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::ANALYTICS_TRACKING,
            granted: true,
        );

        self::assertSame(ConsentType::ANALYTICS_TRACKING, $history->consentType());
    }

    // -----------------------------------------------------------------------
    // Multiple records get unique IDs
    // -----------------------------------------------------------------------

    #[Test]
    public function itGeneratesUniqueIdsForEachRecord(): void
    {
        $history1 = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true,
        );

        $history2 = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: false,
        );

        self::assertFalse($history1->id()->equals($history2->id()));
    }

    // -----------------------------------------------------------------------
    // Timestamps
    // -----------------------------------------------------------------------

    #[Test]
    public function itSetsCreatedAtOnRecord(): void
    {
        $before = new \DateTimeImmutable();
        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true,
        );
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $history->createdAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $history->createdAt()->getTimestamp());
    }

    // -----------------------------------------------------------------------
    // reconstituteFromPersistence — all consent types
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstituteAllConsentTypes(): void
    {
        $consentTypes = [
            ConsentType::MARKETING_EMAIL,
            ConsentType::MARKETING_SMS,
            ConsentType::THIRD_PARTY_SHARING,
            ConsentType::ANALYTICS_TRACKING,
        ];

        foreach ($consentTypes as $type) {
            $id = ConsentHistoryId::generate();
            $createdAt = new \DateTimeImmutable('-1 hour');

            $history = ConsentHistory::reconstituteFromPersistence(
                id: $id,
                customerId: $this->customerId,
                tenantId: $this->tenantId,
                consentType: $type,
                granted: true,
                ipAddress: '10.0.0.1',
                userAgent: 'Mozilla/5.0',
                createdAt: $createdAt,
            );

            self::assertTrue($id->equals($history->id()));
            self::assertSame($type, $history->consentType());
            self::assertTrue($history->granted());
            self::assertSame('10.0.0.1', $history->ipAddress());
            self::assertSame('Mozilla/5.0', $history->userAgent());
            self::assertEquals($createdAt, $history->createdAt());
        }
    }

    #[Test]
    public function itReconstituteWithNullIpAndUserAgent(): void
    {
        $history = ConsentHistory::reconstituteFromPersistence(
            id: ConsentHistoryId::generate(),
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::ANALYTICS_TRACKING,
            granted: false,
            ipAddress: null,
            userAgent: null,
            createdAt: new \DateTimeImmutable(),
        );

        self::assertNull($history->ipAddress());
        self::assertNull($history->userAgent());
        self::assertFalse($history->granted());
    }

    // -----------------------------------------------------------------------
    // Tenant isolation
    // -----------------------------------------------------------------------

    #[Test]
    public function itStoresTenantId(): void
    {
        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true,
        );

        self::assertTrue($this->tenantId->equals($history->tenantId()));
    }

    #[Test]
    public function itDifferentTenantsCanHaveSameCustomerId(): void
    {
        $tenantA = TenantId::generate();
        $tenantB = TenantId::generate();

        $historyA = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $tenantA,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true,
        );

        $historyB = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $tenantB,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: false,
        );

        self::assertTrue($tenantA->equals($historyA->tenantId()));
        self::assertTrue($tenantB->equals($historyB->tenantId()));
        self::assertTrue($this->customerId->equals($historyA->customerId()));
        self::assertTrue($this->customerId->equals($historyB->customerId()));
    }
}
