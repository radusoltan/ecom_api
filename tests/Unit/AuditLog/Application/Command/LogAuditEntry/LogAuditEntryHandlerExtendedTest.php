<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Application\Command\LogAuditEntry;

use App\AuditLog\Application\Command\LogAuditEntry\LogAuditEntry;
use App\AuditLog\Application\Command\LogAuditEntry\LogAuditEntryHandler;
use App\AuditLog\Domain\Model\AuditLogEntry;
use App\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogAuditEntryHandler::class)]
final class LogAuditEntryHandlerExtendedTest extends TestCase
{
    private AuditLogRepositoryInterface&MockObject $repository;
    private LogAuditEntryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AuditLogRepositoryInterface::class);
        $this->handler = new LogAuditEntryHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Happy path — with user
    // -----------------------------------------------------------------------

    #[Test]
    public function itSavesEntryWithUserAndAllFields(): void
    {
        $command = new LogAuditEntry(
            tenantId: '00000000-0000-4000-8000-000000000001',
            userId: '00000000-0000-4000-8000-000000000002',
            actionType: 'create',
            resourceType: 'product',
            resourceId: 'prod-001',
            metadata: ['name' => 'Widget'],
            ipAddress: '127.0.0.1',
            userAgent: 'test-agent',
        );

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(AuditLogEntry::class));

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Happy path — system action (no user)
    // -----------------------------------------------------------------------

    #[Test]
    public function itSavesEntryWithNullUserId(): void
    {
        $command = new LogAuditEntry(
            tenantId: '00000000-0000-4000-8000-000000000001',
            userId: null,
            actionType: 'activate',
            resourceType: 'tenant',
            resourceId: 'tenant-001',
        );

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuditLogEntry $entry): bool {
                return $entry->isSystemAction();
            }));

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // All action types
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesDeleteAction(): void
    {
        $command = new LogAuditEntry(
            tenantId: '00000000-0000-4000-8000-000000000001',
            userId: '00000000-0000-4000-8000-000000000002',
            actionType: 'delete',
            resourceType: 'order',
            resourceId: 'ord-001',
        );

        $this->repository->expects(self::once())->method('save');
        ($this->handler)($command);
    }

    #[Test]
    public function itHandlesLoginAction(): void
    {
        $command = new LogAuditEntry(
            tenantId: '00000000-0000-4000-8000-000000000001',
            userId: '00000000-0000-4000-8000-000000000002',
            actionType: 'login',
            resourceType: 'user',
            resourceId: 'user-001',
        );

        $this->repository->expects(self::once())->method('save');
        ($this->handler)($command);
    }

    #[Test]
    public function itHandlesRefundAction(): void
    {
        $command = new LogAuditEntry(
            tenantId: '00000000-0000-4000-8000-000000000001',
            userId: null,
            actionType: 'refund',
            resourceType: 'payment',
            resourceId: 'pay-001',
        );

        $this->repository->expects(self::once())->method('save');
        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Entry validation passthrough
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesEntryWithCorrectResourceId(): void
    {
        $command = new LogAuditEntry(
            tenantId: '00000000-0000-4000-8000-000000000001',
            userId: null,
            actionType: 'view',
            resourceType: 'media',
            resourceId: 'img-42',
        );

        $capturedEntry = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuditLogEntry $e) use (&$capturedEntry): bool {
                $capturedEntry = $e;

                return true;
            }));

        ($this->handler)($command);

        self::assertNotNull($capturedEntry);
        self::assertSame('img-42', $capturedEntry->resourceId());
    }

    #[Test]
    public function itPreservesMetadataInEntry(): void
    {
        $metadata = ['action' => 'bulk_delete', 'count' => 5];
        $command = new LogAuditEntry(
            tenantId: '00000000-0000-4000-8000-000000000001',
            userId: null,
            actionType: 'delete',
            resourceType: 'product',
            resourceId: 'batch',
            metadata: $metadata,
        );

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuditLogEntry $e) use ($metadata): bool {
                return $e->metadata() === $metadata;
            }));

        ($this->handler)($command);
    }
}
