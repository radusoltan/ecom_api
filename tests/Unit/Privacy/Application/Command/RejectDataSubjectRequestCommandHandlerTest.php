<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\Command\RejectDataSubjectRequestCommand;
use App\Privacy\Application\Command\RejectDataSubjectRequestCommandHandler;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RejectDataSubjectRequestCommandHandler::class)]
final class RejectDataSubjectRequestCommandHandlerTest extends TestCase
{
    private DataSubjectRequestRepositoryInterface $repository;
    private RejectDataSubjectRequestCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->handler = new RejectDataSubjectRequestCommandHandler($this->repository);
    }

    #[Test]
    public function handleRejectsRequestAndSaves(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $request = DataSubjectRequest::submit(
            $requestId,
            TenantId::generate(),
            CustomerId::generate(),
            RequestType::erasure()
        );

        $rejectionReason = 'Data required for legal compliance and ongoing contractual obligations per GDPR Art. 17(3).';
        $command = new RejectDataSubjectRequestCommand($requestId, $rejectionReason);

        $this->repository->expects(self::once())
            ->method('findById')
            ->with($requestId)
            ->willReturn($request);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DataSubjectRequest $saved) use ($rejectionReason): bool {
                return 'rejected' === $saved->status()->value()
                    && $saved->rejectionReason() === $rejectionReason;
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsExceptionWhenRequestNotFound(): void
    {
        $command = new RejectDataSubjectRequestCommand(
            DataSubjectRequestId::generate(),
            'Data required for legal compliance and contractual obligations.'
        );

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Data subject request not found');

        ($this->handler)($command);
    }
}
