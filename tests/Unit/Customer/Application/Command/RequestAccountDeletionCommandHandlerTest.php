<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\RequestAccountDeletion\RequestAccountDeletionCommand;
use App\Customer\Application\Command\RequestAccountDeletion\RequestAccountDeletionCommandHandler;
use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestAccountDeletionCommandHandler::class)]
final class RequestAccountDeletionCommandHandlerTest extends TestCase
{
    private DeletionRequestRepositoryInterface&MockObject $repository;
    private RequestAccountDeletionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DeletionRequestRepositoryInterface::class);
        $this->handler = new RequestAccountDeletionCommandHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Happy path: deletion request saved
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesDeletionRequestAndPersistsIt(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository
            ->expects(self::once())
            ->method('findPendingByCustomerId')
            ->with($customerId, $tenantId)
            ->willReturn(null);

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(
                fn (DeletionRequest $req) => $req->customerId()->equals($customerId)
                    && $req->tenantId()->equals($tenantId)
            ));

        ($this->handler)(new RequestAccountDeletionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            reason: 'No longer using the service',
        ));
    }

    // -----------------------------------------------------------------------
    // Reason propagates to the request
    // -----------------------------------------------------------------------

    #[Test]
    public function itStoresReasonOnDeletionRequest(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $reason = 'Privacy concerns';

        $this->repository->method('findPendingByCustomerId')->willReturn(null);

        $capturedRequest = null;
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (DeletionRequest $req) use (&$capturedRequest): void {
                $capturedRequest = $req;
            });

        ($this->handler)(new RequestAccountDeletionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            reason: $reason,
        ));

        self::assertNotNull($capturedRequest);
        self::assertSame($reason, $capturedRequest->reason());
    }

    // -----------------------------------------------------------------------
    // Null reason is accepted
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsNullReason(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository->method('findPendingByCustomerId')->willReturn(null);
        $this->repository->expects(self::once())->method('save');

        ($this->handler)(new RequestAccountDeletionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            reason: null,
        ));
    }

    // -----------------------------------------------------------------------
    // Exception: pending request already exists
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenPendingRequestAlreadyExists(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $existingRequest = DeletionRequest::create(
            id: DeletionRequestId::generate(),
            customerId: $customerId,
            tenantId: $tenantId,
        );

        $this->repository
            ->expects(self::once())
            ->method('findPendingByCustomerId')
            ->willReturn($existingRequest);

        $this->repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already exists');

        ($this->handler)(new RequestAccountDeletionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            reason: 'Duplicate attempt',
        ));
    }

    // -----------------------------------------------------------------------
    // New request has a unique generated ID
    // -----------------------------------------------------------------------

    #[Test]
    public function itGeneratesUniqueIdForEachRequest(): void
    {
        $customerId1 = CustomerId::generate();
        $customerId2 = CustomerId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->repository->method('findPendingByCustomerId')->willReturn(null);

        $capturedIds = [];
        $this->repository
            ->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(static function (DeletionRequest $req) use (&$capturedIds): void {
                $capturedIds[] = $req->id()->toString();
            });

        ($this->handler)(new RequestAccountDeletionCommand(
            customerId: $customerId1,
            tenantId: $tenantId,
        ));

        ($this->handler)(new RequestAccountDeletionCommand(
            customerId: $customerId2,
            tenantId: $tenantId,
        ));

        self::assertCount(2, $capturedIds);
        self::assertNotSame($capturedIds[0], $capturedIds[1]);
    }
}
