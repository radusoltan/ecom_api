<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\Model;

use App\Customer\Domain\Model\ConsentHistory;
use App\Customer\Domain\ValueObject\ConsentHistoryId;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ConsentHistoryTest extends TestCase
{
    private CustomerId $customerId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->customerId = CustomerId::generate();
        $this->tenantId = TenantId::generate();
    }

    public function testRecord(): void
    {
        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0'
        );

        self::assertInstanceOf(ConsentHistoryId::class, $history->id());
        self::assertTrue($this->customerId->equals($history->customerId()));
        self::assertTrue($this->tenantId->equals($history->tenantId()));
        self::assertEquals(ConsentType::MARKETING_EMAIL, $history->consentType());
        self::assertTrue($history->granted());
        self::assertEquals('192.168.1.1', $history->ipAddress());
        self::assertEquals('Mozilla/5.0', $history->userAgent());
        self::assertInstanceOf(\DateTimeImmutable::class, $history->createdAt());
    }

    public function testRecordWithGrantedFalse(): void
    {
        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::ANALYTICS_TRACKING,
            granted: false
        );

        self::assertFalse($history->granted());
    }

    public function testGetters(): void
    {
        $ipAddress = '10.0.0.1';
        $userAgent = 'Chrome/90.0';

        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::THIRD_PARTY_SHARING,
            granted: true,
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        self::assertInstanceOf(ConsentHistoryId::class, $history->id());
        self::assertTrue($this->customerId->equals($history->customerId()));
        self::assertTrue($this->tenantId->equals($history->tenantId()));
        self::assertEquals(ConsentType::THIRD_PARTY_SHARING, $history->consentType());
        self::assertTrue($history->granted());
        self::assertEquals($ipAddress, $history->ipAddress());
        self::assertEquals($userAgent, $history->userAgent());
        self::assertInstanceOf(\DateTimeImmutable::class, $history->createdAt());
    }

    public function testWithIpAndUserAgent(): void
    {
        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::MARKETING_SMS,
            granted: true,
            ipAddress: '203.0.113.45',
            userAgent: 'Safari/14.0'
        );

        self::assertEquals('203.0.113.45', $history->ipAddress());
        self::assertEquals('Safari/14.0', $history->userAgent());
    }

    public function testWithoutIpAndUserAgent(): void
    {
        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: false
        );

        self::assertNull($history->ipAddress());
        self::assertNull($history->userAgent());
    }

    public function testReconstituteFromPersistence(): void
    {
        $id = ConsentHistoryId::generate();
        $ipAddress = '172.16.0.1';
        $userAgent = 'Firefox/88.0';
        $createdAt = new \DateTimeImmutable('-1 hour');

        $history = ConsentHistory::reconstituteFromPersistence(
            id: $id,
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::ANALYTICS_TRACKING,
            granted: false,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            createdAt: $createdAt
        );

        self::assertTrue($id->equals($history->id()));
        self::assertTrue($this->customerId->equals($history->customerId()));
        self::assertTrue($this->tenantId->equals($history->tenantId()));
        self::assertEquals(ConsentType::ANALYTICS_TRACKING, $history->consentType());
        self::assertFalse($history->granted());
        self::assertEquals($ipAddress, $history->ipAddress());
        self::assertEquals($userAgent, $history->userAgent());
        self::assertEquals($createdAt, $history->createdAt());
    }

    public function testIsReadOnly(): void
    {
        $history = ConsentHistory::record(
            customerId: $this->customerId,
            tenantId: $this->tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true
        );

        // Verify the class is readonly (PHP 8.2+)
        $reflection = new \ReflectionClass($history);
        self::assertTrue($reflection->isReadOnly());
    }
}
