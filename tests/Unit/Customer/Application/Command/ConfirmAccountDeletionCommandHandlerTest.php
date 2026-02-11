<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\ConfirmAccountDeletion\ConfirmAccountDeletionCommand;
use App\Customer\Application\Command\ConfirmAccountDeletion\ConfirmAccountDeletionCommandHandler;
use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\DeletionStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ConfirmAccountDeletionCommandHandlerTest extends TestCase
{
    private DeletionRequestRepositoryInterface $repository;
    private ConfirmAccountDeletionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DeletionRequestRepositoryInterface::class);
        $this->handler = new ConfirmAccountDeletionCommandHandler($this->repository);
    }

    public function testConfirmSuccess(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $deletionRequest = DeletionRequest::create(
            id: $requestId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $command = new ConfirmAccountDeletionCommand(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with(
                $this->callback(fn($id) => $id->equals($requestId)),
                $this->callback(fn($id) => $id->equals($tenantId))
            )
            ->willReturn($deletionRequest);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (DeletionRequest $request) {
                return $request->status() === DeletionStatus::CONFIRMED
                    && $request->confirmedAt() !== null;
            }));

        ($this->handler)($command);

        self::assertEquals(DeletionStatus::CONFIRMED, $deletionRequest->status());
    }

    public function testConfirmSetsScheduledFor(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $deletionRequest = DeletionRequest::create(
            id: $requestId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $beforeConfirm = new \DateTimeImmutable();

        $command = new ConfirmAccountDeletionCommand(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->repository->method('findById')->willReturn($deletionRequest);
        $this->repository->method('save');

        ($this->handler)($command);

        $afterConfirm = new \DateTimeImmutable();
        $scheduledFor = $deletionRequest->scheduledFor();

        // Scheduled should be ~30 days from now
        $expectedMin = $beforeConfirm->modify('+29 days');
        $expectedMax = $afterConfirm->modify('+31 days');

        self::assertGreaterThanOrEqual($expectedMin->getTimestamp(), $scheduledFor->getTimestamp());
        self::assertLessThanOrEqual($expectedMax->getTimestamp(), $scheduledFor->getTimestamp());
    }

    public function testConfirmFailsWhenNotPending(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        // Create request in CONFIRMED status
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

        $command = new ConfirmAccountDeletionCommand(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->repository->method('findById')->willReturn($deletionRequest);

        $this->expectException(\DomainException::class);

        ($this->handler)($command);
    }

    public function testConfirmFailsWhenRequestNotFound(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $command = new ConfirmAccountDeletionCommand(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }
}
