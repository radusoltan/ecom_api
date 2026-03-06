<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\HoldDeletion\HoldDeletionCommand;
use App\Customer\Application\Command\HoldDeletion\HoldDeletionCommandHandler;
use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\DeletionStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HoldDeletionCommandHandler::class)]
final class HoldDeletionCommandHandlerTest extends TestCase
{
    private DeletionRequestRepositoryInterface $repository;
    private HoldDeletionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DeletionRequestRepositoryInterface::class);
        $this->handler = new HoldDeletionCommandHandler($this->repository);
    }

    #[Test]
    public function itPutsRequestOnHold(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $deletionRequest = DeletionRequest::reconstituteFromPersistence(
            id: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            status: DeletionStatus::CONFIRMED,
            reason: null,
            holdReason: null,
            scheduledFor: new \DateTimeImmutable('+30 days'),
            confirmedAt: new \DateTimeImmutable(),
            completedAt: null,
            createdAt: new \DateTimeImmutable()
        );

        $command = new HoldDeletionCommand(
            requestId: $requestId,
            tenantId: $tenantId,
            holdReason: 'Legal investigation pending'
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with($requestId, $tenantId)
            ->willReturn($deletionRequest);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with($this->callback(function (DeletionRequest $saved) {
                return DeletionStatus::ON_HOLD === $saved->status()
                    && 'Legal investigation pending' === $saved->holdReason();
            }));

        ($this->handler)($command);

        self::assertSame(DeletionStatus::ON_HOLD, $deletionRequest->status());
        self::assertSame('Legal investigation pending', $deletionRequest->holdReason());
    }

    #[Test]
    public function itThrowsWhenRequestNotFound(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();

        $command = new HoldDeletionCommand(
            requestId: $requestId,
            tenantId: $tenantId,
            holdReason: 'Litigation hold'
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Deletion request not found');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenHoldReasonIsEmpty(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $deletionRequest = DeletionRequest::reconstituteFromPersistence(
            id: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            status: DeletionStatus::CONFIRMED,
            reason: null,
            holdReason: null,
            scheduledFor: new \DateTimeImmutable('+30 days'),
            confirmedAt: new \DateTimeImmutable(),
            completedAt: null,
            createdAt: new \DateTimeImmutable()
        );

        $command = new HoldDeletionCommand(
            requestId: $requestId,
            tenantId: $tenantId,
            holdReason: '   '
        );

        $this->repository->method('findById')->willReturn($deletionRequest);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Hold reason is required');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenRequestCannotBeHeld(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        // COMPLETED status cannot be put on hold
        $deletionRequest = DeletionRequest::reconstituteFromPersistence(
            id: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            status: DeletionStatus::COMPLETED,
            reason: null,
            holdReason: null,
            scheduledFor: new \DateTimeImmutable('-1 day'),
            confirmedAt: new \DateTimeImmutable('-31 days'),
            completedAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable('-31 days')
        );

        $command = new HoldDeletionCommand(
            requestId: $requestId,
            tenantId: $tenantId,
            holdReason: 'Compliance hold'
        );

        $this->repository->method('findById')->willReturn($deletionRequest);

        $this->expectException(\DomainException::class);

        ($this->handler)($command);
    }
}
