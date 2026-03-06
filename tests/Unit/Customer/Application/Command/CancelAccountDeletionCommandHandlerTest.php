<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\CancelAccountDeletion\CancelAccountDeletionCommand;
use App\Customer\Application\Command\CancelAccountDeletion\CancelAccountDeletionCommandHandler;
use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\DeletionStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CancelAccountDeletionCommandHandler::class)]
final class CancelAccountDeletionCommandHandlerTest extends TestCase
{
    private DeletionRequestRepositoryInterface $repository;
    private CancelAccountDeletionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DeletionRequestRepositoryInterface::class);
        $this->handler = new CancelAccountDeletionCommandHandler($this->repository);
    }

    #[Test]
    public function itCancelsPendingRequest(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $deletionRequest = DeletionRequest::create(
            id: $requestId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $command = new CancelAccountDeletionCommand(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->repository
            ->expects(self::once())
            ->method('findById')
            ->with($requestId, $tenantId)
            ->willReturn($deletionRequest);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with($this->callback(fn (DeletionRequest $saved) => DeletionStatus::CANCELLED === $saved->status()));

        ($this->handler)($command);

        self::assertSame(DeletionStatus::CANCELLED, $deletionRequest->status());
    }

    #[Test]
    public function itCancelsConfirmedRequest(): void
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

        $command = new CancelAccountDeletionCommand(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->repository->method('findById')->willReturn($deletionRequest);
        $this->repository->expects(self::once())->method('save');

        ($this->handler)($command);

        self::assertSame(DeletionStatus::CANCELLED, $deletionRequest->status());
    }

    #[Test]
    public function itThrowsWhenRequestNotFound(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $command = new CancelAccountDeletionCommand(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->repository->method('findById')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenRequestBelongsToDifferentCustomer(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $ownerCustomerId = CustomerId::generate();
        $otherCustomerId = CustomerId::generate();

        $deletionRequest = DeletionRequest::create(
            id: $requestId,
            customerId: $ownerCustomerId,
            tenantId: $tenantId
        );

        $command = new CancelAccountDeletionCommand(
            requestId: $requestId,
            customerId: $otherCustomerId,
            tenantId: $tenantId
        );

        $this->repository->method('findById')->willReturn($deletionRequest);
        $this->repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not belong to this customer');

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenRequestAlreadyCompleted(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

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

        $command = new CancelAccountDeletionCommand(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->repository->method('findById')->willReturn($deletionRequest);

        $this->expectException(\DomainException::class);

        ($this->handler)($command);
    }
}
