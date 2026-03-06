<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\Command\ReviewDataSubjectRequestCommand;
use App\Privacy\Application\Command\ReviewDataSubjectRequestCommandHandler;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviewDataSubjectRequestCommandHandler::class)]
final class ReviewDataSubjectRequestCommandHandlerTest extends TestCase
{
    private DataSubjectRequestRepositoryInterface $repository;
    private ReviewDataSubjectRequestCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
        $this->handler = new ReviewDataSubjectRequestCommandHandler($this->repository);
    }

    #[Test]
    public function handleStartsReviewAndSaves(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $request = DataSubjectRequest::submit(
            $requestId,
            TenantId::generate(),
            CustomerId::generate(),
            RequestType::access()
        );

        $command = new ReviewDataSubjectRequestCommand($requestId, 'Reviewing access request for compliance.');

        $this->repository->expects(self::once())
            ->method('findById')
            ->with($requestId)
            ->willReturn($request);

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (DataSubjectRequest $saved): bool {
                return 'under_review' === $saved->status()->value()
                    && 'Reviewing access request for compliance.' === $saved->reviewNotes();
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsExceptionWhenRequestNotFound(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $command = new ReviewDataSubjectRequestCommand($requestId, 'Review notes.');

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Data subject request not found');

        ($this->handler)($command);
    }
}
