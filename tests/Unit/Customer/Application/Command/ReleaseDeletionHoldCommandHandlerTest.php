<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\ReleaseDeletionHold\ReleaseDeletionHoldCommand;
use App\Customer\Application\Command\ReleaseDeletionHold\ReleaseDeletionHoldCommandHandler;
use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\DeletionStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReleaseDeletionHoldCommandHandler::class)]
final class ReleaseDeletionHoldCommandHandlerTest extends TestCase
{
    private DeletionRequestRepositoryInterface $repository;
    private ReleaseDeletionHoldCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DeletionRequestRepositoryInterface::class);
        $this->handler = new ReleaseDeletionHoldCommandHandler($this->repository);
    }

    #[Test]
    public function itReleasesHoldAndRestoresConfirmedStatus(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();

        $deletionRequest = DeletionRequest::reconstituteFromPersistence(
            id: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            status: DeletionStatus::ON_HOLD,
            reason: null,
            holdReason: 'Legal hold',
            scheduledFor: new \DateTimeImmutable('+30 days'),
            confirmedAt: new \DateTimeImmutable(),
            completedAt: null,
            createdAt: new \DateTimeImmutable()
        );

        $command = new ReleaseDeletionHoldCommand(
            requestId: $requestId,
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
            ->with($this->callback(function (DeletionRequest $saved) {
                return DeletionStatus::CONFIRMED === $saved->status()
                    && null === $saved->holdReason();
            }));

        ($this->handler)($command);

        self::assertSame(DeletionStatus::CONFIRMED, $deletionRequest->status());
        self::assertNull($deletionRequest->holdReason());
    }

    #[Test]
    public function itThrowsWhenRequestNotFound(): void
    {
        $requestId = DeletionRequestId::generate();
        $tenantId = TenantId::generate();

        $command = new ReleaseDeletionHoldCommand(
            requestId: $requestId,
            tenantId: $tenantId
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
    public function itThrowsWhenRequestIsNotOnHold(): void
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

        $command = new ReleaseDeletionHoldCommand(
            requestId: $requestId,
            tenantId: $tenantId
        );

        $this->repository->method('findById')->willReturn($deletionRequest);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not on hold');

        ($this->handler)($command);
    }
}
