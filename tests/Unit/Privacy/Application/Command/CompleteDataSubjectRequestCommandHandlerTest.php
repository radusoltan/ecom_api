<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\Command\CompleteDataSubjectRequestCommand;
use App\Privacy\Application\Command\CompleteDataSubjectRequestCommandHandler;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompleteDataSubjectRequestCommandHandler::class)]
final class CompleteDataSubjectRequestCommandHandlerTest extends TestCase
{
    private DataSubjectRequestRepositoryInterface $repository;
    private CompleteDataSubjectRequestCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->handler = new CompleteDataSubjectRequestCommandHandler($this->repository);
    }

    #[Test]
    public function handleCompletesRequestAndSaves(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $request = $this->createReviewedRequest($requestId, RequestType::erasure());
        $command = new CompleteDataSubjectRequestCommand($requestId);

        $this->repository->expects(self::once())
            ->method('findById')
            ->with($requestId)
            ->willReturn($request);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DataSubjectRequest $saved): bool {
                return 'completed' === $saved->status()->value()
                    && null !== $saved->completedAt();
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleCompletesAccessRequestWithExportData(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $request = $this->createReviewedRequest($requestId, RequestType::access());
        $exportData = ['personal_data' => ['name' => 'John']];
        $command = new CompleteDataSubjectRequestCommand($requestId, $exportData);

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn($request);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DataSubjectRequest $saved) use ($exportData): bool {
                return $saved->exportData() === $exportData;
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsExceptionWhenRequestNotFound(): void
    {
        $command = new CompleteDataSubjectRequestCommand(DataSubjectRequestId::generate());

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Data subject request not found');

        ($this->handler)($command);
    }

    private function createReviewedRequest(DataSubjectRequestId $id, RequestType $type): DataSubjectRequest
    {
        $request = DataSubjectRequest::submit(
            $id,
            TenantId::generate(),
            CustomerId::generate(),
            $type
        );
        $request->startReview('Reviewing for compliance verification.');

        return $request;
    }
}
