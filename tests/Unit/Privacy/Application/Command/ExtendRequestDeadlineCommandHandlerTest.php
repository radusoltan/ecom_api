<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\Command\ExtendRequestDeadlineCommand;
use App\Privacy\Application\Command\ExtendRequestDeadlineCommandHandler;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExtendRequestDeadlineCommandHandler::class)]
final class ExtendRequestDeadlineCommandHandlerTest extends TestCase
{
    private DataSubjectRequestRepositoryInterface $repository;
    private ExtendRequestDeadlineCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->handler = new ExtendRequestDeadlineCommandHandler($this->repository);
    }

    #[Test]
    public function handleExtendsDeadlineAndSaves(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $request = DataSubjectRequest::submit(
            $requestId,
            TenantId::generate(),
            CustomerId::generate(),
            RequestType::access()
        );

        $command = new ExtendRequestDeadlineCommand($requestId);

        $this->repository->expects(self::once())
            ->method('findById')
            ->with($requestId)
            ->willReturn($request);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DataSubjectRequest $saved): bool {
                return $saved->isExtended();
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsExceptionWhenRequestNotFound(): void
    {
        $command = new ExtendRequestDeadlineCommand(DataSubjectRequestId::generate());

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Data subject request not found');

        ($this->handler)($command);
    }
}
