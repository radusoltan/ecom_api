<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Domain\Model;

use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\ValueObject\ActionType;
use App\AuditLog\Domain\ValueObject\AuditLogId;
use App\AuditLog\Domain\ValueObject\ResourceType;
use App\Shared\Domain\ValueObject\TenantId;
use App\User\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditLogEntry::class)]
final class AuditLogEntryExtendedTest extends TestCase
{
    private TenantId $tenantId;
    private UserId $userId;
    private ActionType $actionType;
    private ResourceType $resourceType;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->userId = UserId::fromString('00000000-0000-4000-8000-000000000002');
        $this->actionType = ActionType::create();
        $this->resourceType = ResourceType::product();
    }

    // -----------------------------------------------------------------------
    // AuditLogEntry::log()
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsEntryWithAllFieldsPopulated(): void
    {
        $entry = AuditLogEntry::log(
            tenantId: $this->tenantId,
            userId: $this->userId,
            actionType: $this->actionType,
            resourceType: $this->resourceType,
            resourceId: 'res-001',
            metadata: ['key' => 'value'],
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
        );

        self::assertInstanceOf(AuditLogId::class, $entry->id());
        self::assertTrue($this->tenantId->equals($entry->tenantId()));
        self::assertTrue($this->userId->equals($entry->userId()));
        self::assertTrue($this->actionType->equals($entry->actionType()));
        self::assertTrue($this->resourceType->equals($entry->resourceType()));
        self::assertSame('res-001', $entry->resourceId());
        self::assertSame(['key' => 'value'], $entry->metadata());
        self::assertSame('192.168.1.1', $entry->ipAddress());
        self::assertSame('Mozilla/5.0', $entry->userAgent());
        self::assertInstanceOf(\DateTimeImmutable::class, $entry->occurredAt());
    }

    #[Test]
    public function itLogsEntryWithNullableFieldsAsNull(): void
    {
        $entry = AuditLogEntry::log(
            tenantId: $this->tenantId,
            userId: null,
            actionType: $this->actionType,
            resourceType: $this->resourceType,
            resourceId: 'res-002',
        );

        self::assertNull($entry->userId());
        self::assertNull($entry->ipAddress());
        self::assertNull($entry->userAgent());
        self::assertSame([], $entry->metadata());
    }

    #[Test]
    public function itGeneratesUniqueIdOnEachLog(): void
    {
        $entry1 = AuditLogEntry::log($this->tenantId, null, $this->actionType, $this->resourceType, 'r1');
        $entry2 = AuditLogEntry::log($this->tenantId, null, $this->actionType, $this->resourceType, 'r2');

        self::assertFalse($entry1->id()->equals($entry2->id()));
    }

    #[Test]
    public function itSetsOccurredAtCloseToNow(): void
    {
        $before = new \DateTimeImmutable();
        $entry = AuditLogEntry::log($this->tenantId, null, $this->actionType, $this->resourceType, 'r');
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $entry->occurredAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $entry->occurredAt()->getTimestamp());
    }

    // -----------------------------------------------------------------------
    // AuditLogEntry::reconstitute()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstituteEntryFromPersistence(): void
    {
        $id = AuditLogId::generate();
        $occurredAt = new \DateTimeImmutable('2024-01-15 12:00:00');

        $entry = AuditLogEntry::reconstitute(
            id: $id,
            tenantId: $this->tenantId,
            userId: $this->userId,
            actionType: $this->actionType,
            resourceType: $this->resourceType,
            resourceId: 'res-persist',
            metadata: ['restored' => true],
            ipAddress: '10.0.0.1',
            userAgent: 'curl/7.0',
            occurredAt: $occurredAt,
        );

        self::assertTrue($id->equals($entry->id()));
        self::assertTrue($this->tenantId->equals($entry->tenantId()));
        self::assertTrue($this->userId->equals($entry->userId()));
        self::assertSame('res-persist', $entry->resourceId());
        self::assertSame(['restored' => true], $entry->metadata());
        self::assertSame('10.0.0.1', $entry->ipAddress());
        self::assertSame('curl/7.0', $entry->userAgent());
        self::assertSame($occurredAt->getTimestamp(), $entry->occurredAt()->getTimestamp());
    }

    #[Test]
    public function itReconstituteEntryWithNullUserId(): void
    {
        $id = AuditLogId::generate();
        $entry = AuditLogEntry::reconstitute(
            id: $id,
            tenantId: $this->tenantId,
            userId: null,
            actionType: $this->actionType,
            resourceType: $this->resourceType,
            resourceId: 'r',
            metadata: [],
            ipAddress: null,
            userAgent: null,
            occurredAt: new \DateTimeImmutable(),
        );

        self::assertNull($entry->userId());
        self::assertNull($entry->ipAddress());
        self::assertNull($entry->userAgent());
    }

    // -----------------------------------------------------------------------
    // wasPerformedBy()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsTrueWhenPerformedBySameUser(): void
    {
        $entry = AuditLogEntry::log($this->tenantId, $this->userId, $this->actionType, $this->resourceType, 'r');

        self::assertTrue($entry->wasPerformedBy($this->userId));
    }

    #[Test]
    public function itReturnsFalseWhenPerformedByDifferentUser(): void
    {
        $otherUser = UserId::fromString('00000000-0000-4000-8000-000000000099');
        $entry = AuditLogEntry::log($this->tenantId, $this->userId, $this->actionType, $this->resourceType, 'r');

        self::assertFalse($entry->wasPerformedBy($otherUser));
    }

    #[Test]
    public function itReturnsFalseWhenUserIdIsNull(): void
    {
        $entry = AuditLogEntry::log($this->tenantId, null, $this->actionType, $this->resourceType, 'r');

        self::assertFalse($entry->wasPerformedBy($this->userId));
    }

    // -----------------------------------------------------------------------
    // affectedResource()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsTrueWhenResourceMatches(): void
    {
        $entry = AuditLogEntry::log($this->tenantId, null, $this->actionType, $this->resourceType, 'res-123');

        self::assertTrue($entry->affectedResource($this->resourceType, 'res-123'));
    }

    #[Test]
    public function itReturnsFalseWhenResourceIdDiffers(): void
    {
        $entry = AuditLogEntry::log($this->tenantId, null, $this->actionType, $this->resourceType, 'res-123');

        self::assertFalse($entry->affectedResource($this->resourceType, 'res-999'));
    }

    #[Test]
    public function itReturnsFalseWhenResourceTypeDiffers(): void
    {
        $entry = AuditLogEntry::log($this->tenantId, null, $this->actionType, $this->resourceType, 'res-123');
        $otherType = ResourceType::order();

        self::assertFalse($entry->affectedResource($otherType, 'res-123'));
    }

    // -----------------------------------------------------------------------
    // isSystemAction()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsTrueForSystemAction(): void
    {
        $entry = AuditLogEntry::log($this->tenantId, null, $this->actionType, $this->resourceType, 'r');

        self::assertTrue($entry->isSystemAction());
    }

    #[Test]
    public function itReturnsFalseForUserAction(): void
    {
        $entry = AuditLogEntry::log($this->tenantId, $this->userId, $this->actionType, $this->resourceType, 'r');

        self::assertFalse($entry->isSystemAction());
    }

    // -----------------------------------------------------------------------
    // Metadata richness
    // -----------------------------------------------------------------------

    #[Test]
    public function itStoresComplexMetadata(): void
    {
        $metadata = [
            'before' => ['status' => 'active'],
            'after' => ['status' => 'inactive'],
            'reason' => 'User request',
            'count' => 42,
        ];

        $entry = AuditLogEntry::log(
            $this->tenantId, null, $this->actionType, $this->resourceType, 'r', $metadata
        );

        self::assertSame($metadata, $entry->metadata());
    }

    // -----------------------------------------------------------------------
    // All ActionType factory methods (logged)
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsWithUpdateActionType(): void
    {
        $entry = AuditLogEntry::log(
            $this->tenantId, null, ActionType::update(), $this->resourceType, 'r'
        );
        self::assertTrue(ActionType::update()->equals($entry->actionType()));
    }

    #[Test]
    public function itLogsWithDeleteActionType(): void
    {
        $entry = AuditLogEntry::log(
            $this->tenantId, null, ActionType::delete(), $this->resourceType, 'r'
        );
        self::assertTrue(ActionType::delete()->equals($entry->actionType()));
    }

    #[Test]
    public function itLogsWithLoginActionType(): void
    {
        $entry = AuditLogEntry::log(
            $this->tenantId, $this->userId, ActionType::login(), $this->resourceType, 'r'
        );
        self::assertTrue(ActionType::login()->equals($entry->actionType()));
    }

    // -----------------------------------------------------------------------
    // All ResourceType factory methods (logged)
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsForOrderResource(): void
    {
        $entry = AuditLogEntry::log(
            $this->tenantId, null, $this->actionType, ResourceType::order(), 'order-1'
        );
        self::assertTrue(ResourceType::order()->equals($entry->resourceType()));
        self::assertSame('order-1', $entry->resourceId());
    }

    #[Test]
    public function itLogsForUserResource(): void
    {
        $entry = AuditLogEntry::log(
            $this->tenantId, null, $this->actionType, ResourceType::user(), 'user-1'
        );
        self::assertTrue(ResourceType::user()->equals($entry->resourceType()));
    }

    #[Test]
    public function itLogsForPaymentResource(): void
    {
        $entry = AuditLogEntry::log(
            $this->tenantId, null, ActionType::capture(), ResourceType::payment(), 'pay-1'
        );
        self::assertTrue(ResourceType::payment()->equals($entry->resourceType()));
    }
}
