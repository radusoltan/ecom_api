<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\Command\SubmitDataSubjectRequestCommand;
use App\Privacy\Application\Command\SubmitDataSubjectRequestCommandHandler;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubmitDataSubjectRequestCommandHandler::class)]
final class SubmitDataSubjectRequestCommandHandlerTest extends TestCase
{
    private DataSubjectRequestRepositoryInterface $repository;
    private SubmitDataSubjectRequestCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->handler = new SubmitDataSubjectRequestCommandHandler($this->repository);
    }

    #[Test]
    public function handleCreatesAndSavesRequest(): void
    {
        $command = new SubmitDataSubjectRequestCommand(
            tenantId: TenantId::generate(),
            customerId: CustomerId::generate(),
            requestType: RequestType::access()
        );

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DataSubjectRequest $request) use ($command): bool {
                return $request->tenantId()->equals($command->tenantId)
                    && $request->customerId()->equals($command->customerId)
                    && $request->requestType()->equals($command->requestType)
                    && $request->status()->value() === 'pending';
            }));

        $result = ($this->handler)($command);

        self::assertInstanceOf(DataSubjectRequestId::class, $result);
    }

    #[Test]
    public function handleErasureRequestChecksDuplicates(): void
    {
        $customerId = CustomerId::generate();
        $command = new SubmitDataSubjectRequestCommand(
            tenantId: TenantId::generate(),
            customerId: $customerId,
            requestType: RequestType::erasure()
        );

        $this->repository->expects(self::once())
            ->method('hasPendingErasureRequest')
            ->with($customerId)
            ->willReturn(true);

        $this->repository->expects(self::never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pending erasure request already exists');

        ($this->handler)($command);
    }

    #[Test]
    public function handleErasureRequestSucceedsWhenNoPendingExists(): void
    {
        $command = new SubmitDataSubjectRequestCommand(
            tenantId: TenantId::generate(),
            customerId: CustomerId::generate(),
            requestType: RequestType::erasure()
        );

        $this->repository->expects(self::once())
            ->method('hasPendingErasureRequest')
            ->willReturn(false);

        $this->repository->expects(self::once())
            ->method('save');

        $result = ($this->handler)($command);

        self::assertInstanceOf(DataSubjectRequestId::class, $result);
    }

    #[Test]
    public function handleNonErasureRequestDoesNotCheckDuplicates(): void
    {
        $command = new SubmitDataSubjectRequestCommand(
            tenantId: TenantId::generate(),
            customerId: CustomerId::generate(),
            requestType: RequestType::access()
        );

        $this->repository->expects(self::never())
            ->method('hasPendingErasureRequest');

        $this->repository->expects(self::once())
            ->method('save');

        ($this->handler)($command);
    }
}
