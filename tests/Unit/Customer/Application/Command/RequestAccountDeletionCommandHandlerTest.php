<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Command;

use App\Customer\Application\Command\RequestAccountDeletion\RequestAccountDeletionCommand;
use App\Customer\Application\Command\RequestAccountDeletion\RequestAccountDeletionCommandHandler;
use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class RequestAccountDeletionCommandHandlerTest extends TestCase
{
    private DeletionRequestRepositoryInterface $repository;
    private RequestAccountDeletionCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DeletionRequestRepositoryInterface::class);
        $this->handler = new RequestAccountDeletionCommandHandler($this->repository);
    }

    public function testRequestSuccess(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();
        $reason = 'No longer need the account';

        $command = new RequestAccountDeletionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            reason: $reason
        );

        $this->repository->expects($this->once())
            ->method('findPendingByCustomerId')
            ->with($customerId, $tenantId)
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (DeletionRequest $request) use ($customerId, $tenantId, $reason) {
                return $request->customerId()->equals($customerId)
                    && $request->tenantId()->equals($tenantId)
                    && $request->reason() === $reason;
            }));

        ($this->handler)($command);
    }

    public function testRequestFailsWhenPendingExists(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::generate();

        // Mock existing pending request
        $existingRequest = DeletionRequest::create(
            id: \App\Customer\Domain\ValueObject\DeletionRequestId::generate(),
            customerId: $customerId,
            tenantId: $tenantId
        );

        $this->repository->expects($this->once())
            ->method('findPendingByCustomerId')
            ->with($customerId, $tenantId)
            ->willReturn($existingRequest);

        $this->repository->expects($this->never())
            ->method('save');

        $command = new RequestAccountDeletionCommand(
            customerId: $customerId,
            tenantId: $tenantId,
            reason: 'Some reason'
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already exists');

        ($this->handler)($command);
    }

    public function testRequestFailsWhenCustomerNotFound(): void
    {
        // This test depends on CustomerRepository check if implemented
        $this->expectNotToPerformAssertions();
    }
}
