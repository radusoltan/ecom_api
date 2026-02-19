<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Application\Command\CompleteDataSubjectRequestCommand;
use App\Privacy\Application\Command\ExtendRequestDeadlineCommand;
use App\Privacy\Application\Command\RejectDataSubjectRequestCommand;
use App\Privacy\Application\Command\ReviewDataSubjectRequestCommand;
use App\Privacy\Application\Command\SubmitDataSubjectRequestCommand;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\DataSubjectRequestEntity;
use App\Privacy\Presentation\Api\Processor\CompleteDataSubjectRequestProcessor;
use App\Privacy\Presentation\Api\Processor\ExtendRequestDeadlineProcessor;
use App\Privacy\Presentation\Api\Processor\RejectDataSubjectRequestProcessor;
use App\Privacy\Presentation\Api\Processor\ReviewDataSubjectRequestProcessor;
use App\Privacy\Presentation\Api\Processor\SubmitDataSubjectRequestProcessor;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class DataSubjectRequestProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private MessageBusInterface $commandBus;
    private DataSubjectRequestRepositoryInterface $requestRepository;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->requestRepository = $this->createMock(DataSubjectRequestRepositoryInterface::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildDomainRequest(
        DataSubjectRequestId $requestId,
        string $requestType = 'rectification',
        string $status = 'pending',
    ): DataSubjectRequest {
        $now = new \DateTimeImmutable();

        return DataSubjectRequest::reconstituteFromPersistence(
            id: $requestId,
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerId: CustomerId::fromString(self::CUSTOMER_ID),
            requestType: RequestType::fromString($requestType),
            status: RequestStatus::fromString($status),
            reason: 'I want my data corrected',
            reviewNotes: null,
            rejectionReason: null,
            exportData: null,
            submittedAt: $now,
            completedAt: null,
            deadline: $now->modify('+30 days'),
            isExtended: false,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function buildSubmitEntity(DataSubjectRequestId $requestId): DataSubjectRequestEntity
    {
        $domainRequest = DataSubjectRequest::submit(
            $requestId,
            TenantId::fromString(self::TENANT_ID),
            CustomerId::fromString(self::CUSTOMER_ID),
            RequestType::fromString('access'),
            'I want access to my personal data',
        );

        return DataSubjectRequestEntity::fromDomainModel($domainRequest);
    }

    private function makeRequestStack(array $payload): RequestStack
    {
        $request = new Request(content: json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    // -------------------------------------------------------------------------
    // SubmitDataSubjectRequestProcessor tests
    // -------------------------------------------------------------------------

    public function testSubmitDSRDispatchesCommandAndReturnsEntity(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $entity = $this->buildSubmitEntity($requestId);

        $domainRequest = $this->buildDomainRequest($requestId, 'access');

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) {
                return $command instanceof SubmitDataSubjectRequestCommand
                    && $command->tenantId->toString() === self::TENANT_ID
                    && $command->customerId->toString() === self::CUSTOMER_ID
                    && $command->requestType->value() === 'access';
            }))
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp($requestId, 'handler')]));

        $this->requestRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->callback(fn ($id) => $id instanceof DataSubjectRequestId && $id->equals($requestId)))
            ->willReturn($domainRequest);

        $processor = new SubmitDataSubjectRequestProcessor($this->commandBus, $this->requestRepository);

        $result = $processor->process(
            $entity,
            $this->createMock(Operation::class),
            [],
            ['tenant_id' => self::TENANT_ID],
        );

        $this->assertInstanceOf(DataSubjectRequestEntity::class, $result);
        $this->assertSame(self::CUSTOMER_ID, $result->getCustomerId());
        $this->assertSame('access', $result->getRequestType());
    }

    public function testSubmitDSRThrowsWhenMissingCustomerId(): void
    {
        // Build an entity where customerId is empty — we do this via a domain
        // request whose customerId is a known UUID then override via fromDomainModel,
        // but the processor reads getCustomerId() on the DataSubjectRequestEntity.
        // We use a partial mock to control getCustomerId() returning ''.
        $entity = $this->createMock(DataSubjectRequestEntity::class);
        $entity->method('getCustomerId')->willReturn('');
        $entity->method('getRequestType')->willReturn('access');

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new SubmitDataSubjectRequestProcessor($this->commandBus, $this->requestRepository);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Customer ID is required');

        $processor->process(
            $entity,
            $this->createMock(Operation::class),
            [],
            ['tenant_id' => self::TENANT_ID],
        );
    }

    public function testSubmitDSRThrowsWhenMissingRequestType(): void
    {
        $entity = $this->createMock(DataSubjectRequestEntity::class);
        $entity->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
        $entity->method('getRequestType')->willReturn('');

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new SubmitDataSubjectRequestProcessor($this->commandBus, $this->requestRepository);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Request type is required');

        $processor->process(
            $entity,
            $this->createMock(Operation::class),
            [],
            ['tenant_id' => self::TENANT_ID],
        );
    }

    public function testSubmitDSRThrowsWhenMissingTenantId(): void
    {
        $entity = $this->createMock(DataSubjectRequestEntity::class);
        $entity->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
        $entity->method('getRequestType')->willReturn('access');

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new SubmitDataSubjectRequestProcessor($this->commandBus, $this->requestRepository);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant ID not found in context');

        // Context without tenant_id key
        $processor->process(
            $entity,
            $this->createMock(Operation::class),
            [],
            [],
        );
    }

    public function testSubmitDSRThrowsRuntimeExceptionWhenRequestNotFoundAfterCreate(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $entity = $this->buildSubmitEntity($requestId);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp($requestId, 'handler')]));

        $this->requestRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $processor = new SubmitDataSubjectRequestProcessor($this->commandBus, $this->requestRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Data subject request was created but could not be loaded');

        $processor->process(
            $entity,
            $this->createMock(Operation::class),
            [],
            ['tenant_id' => self::TENANT_ID],
        );
    }

    // -------------------------------------------------------------------------
    // ReviewDataSubjectRequestProcessor tests
    // -------------------------------------------------------------------------

    public function testReviewDSRDispatchesCommandAndReturnsEntity(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId);

        $requestStack = $this->makeRequestStack(['reviewNotes' => 'Looking into it now']);

        $this->requestRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->with($this->callback(fn ($id) => $id instanceof DataSubjectRequestId && $id->equals($requestId)))
            ->willReturn($domainRequest);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($requestId) {
                return $command instanceof ReviewDataSubjectRequestCommand
                    && $command->requestId->equals($requestId)
                    && $command->reviewNotes === 'Looking into it now';
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $processor = new ReviewDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $result = $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );

        $this->assertInstanceOf(DataSubjectRequestEntity::class, $result);
        $this->assertSame($requestId->toString(), $result->getId());
    }

    public function testReviewDSRThrowsWhenNotFound(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $requestStack = $this->makeRequestStack(['reviewNotes' => 'Looking into it now']);

        $this->requestRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new ReviewDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(sprintf('Data subject request "%s" not found', $requestId->toString()));

        $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );
    }

    public function testReviewDSRThrowsWhenMissingReviewNotes(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId);

        // Payload has no reviewNotes key
        $requestStack = $this->makeRequestStack([]);

        $this->requestRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($domainRequest);

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new ReviewDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Review notes are required');

        $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );
    }

    public function testReviewDSRThrowsWhenReviewNotesAreBlank(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId);

        $requestStack = $this->makeRequestStack(['reviewNotes' => '   ']);

        $this->requestRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($domainRequest);

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new ReviewDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Review notes are required');

        $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );
    }

    public function testReviewDSRThrowsNotFoundWhenIdMissing(): void
    {
        $requestStack = $this->makeRequestStack(['reviewNotes' => 'Some notes']);

        $this->requestRepository->expects($this->never())->method('findById');
        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new ReviewDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Request ID is required');

        $processor->process(
            null,
            $this->createMock(Operation::class),
            [],
        );
    }

    // -------------------------------------------------------------------------
    // CompleteDataSubjectRequestProcessor tests
    // -------------------------------------------------------------------------

    public function testCompleteDSRDispatchesCommand(): void
    {
        // Use erasure type so exportData is not required by domain
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId, 'erasure', 'under_review');

        $requestStack = $this->makeRequestStack(['exportData' => null]);

        $this->requestRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->with($this->callback(fn ($id) => $id instanceof DataSubjectRequestId && $id->equals($requestId)))
            ->willReturn($domainRequest);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($requestId) {
                return $command instanceof CompleteDataSubjectRequestCommand
                    && $command->requestId->equals($requestId)
                    && null === $command->exportData;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $processor = new CompleteDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $result = $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );

        $this->assertInstanceOf(DataSubjectRequestEntity::class, $result);
        $this->assertSame($requestId->toString(), $result->getId());
    }

    public function testCompleteDSRPassesExportDataToCommand(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId, 'erasure', 'under_review');
        $exportPayload = ['email' => 'test@example.com', 'orders' => []];

        $requestStack = $this->makeRequestStack(['exportData' => $exportPayload]);

        $this->requestRepository
            ->method('findById')
            ->willReturn($domainRequest);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($exportPayload) {
                return $command instanceof CompleteDataSubjectRequestCommand
                    && $command->exportData === $exportPayload;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $processor = new CompleteDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $result = $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );

        $this->assertInstanceOf(DataSubjectRequestEntity::class, $result);
    }

    public function testCompleteDSRThrowsNotFoundWhenRequestDoesNotExist(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $requestStack = $this->makeRequestStack([]);

        $this->requestRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new CompleteDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(sprintf('Data subject request "%s" not found', $requestId->toString()));

        $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );
    }

    public function testCompleteDSRThrowsNotFoundWhenIdMissing(): void
    {
        $requestStack = $this->makeRequestStack([]);

        $this->requestRepository->expects($this->never())->method('findById');
        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new CompleteDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Request ID is required');

        $processor->process(
            null,
            $this->createMock(Operation::class),
            [],
        );
    }

    // -------------------------------------------------------------------------
    // RejectDataSubjectRequestProcessor tests
    // -------------------------------------------------------------------------

    public function testRejectDSRDispatchesCommand(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId);

        $rejectionReason = 'The request cannot be fulfilled because the data is required for legal obligations.';
        $requestStack = $this->makeRequestStack(['rejectionReason' => $rejectionReason]);

        $this->requestRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->with($this->callback(fn ($id) => $id instanceof DataSubjectRequestId && $id->equals($requestId)))
            ->willReturn($domainRequest);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($requestId, $rejectionReason) {
                return $command instanceof RejectDataSubjectRequestCommand
                    && $command->requestId->equals($requestId)
                    && $command->rejectionReason === $rejectionReason;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $processor = new RejectDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $result = $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );

        $this->assertInstanceOf(DataSubjectRequestEntity::class, $result);
        $this->assertSame($requestId->toString(), $result->getId());
    }

    public function testRejectDSRThrowsWhenMissingRejectionReason(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId);

        // No rejectionReason key in payload
        $requestStack = $this->makeRequestStack([]);

        $this->requestRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($domainRequest);

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new RejectDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Rejection reason is required');

        $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );
    }

    public function testRejectDSRThrowsWhenRejectionReasonIsBlank(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId);

        $requestStack = $this->makeRequestStack(['rejectionReason' => '   ']);

        $this->requestRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($domainRequest);

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new RejectDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Rejection reason is required');

        $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );
    }

    public function testRejectDSRThrowsNotFoundWhenRequestDoesNotExist(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $requestStack = $this->makeRequestStack(['rejectionReason' => 'Some reason for rejection here.']);

        $this->requestRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new RejectDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(sprintf('Data subject request "%s" not found', $requestId->toString()));

        $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );
    }

    public function testRejectDSRThrowsNotFoundWhenIdMissing(): void
    {
        $requestStack = $this->makeRequestStack(['rejectionReason' => 'Some reason']);

        $this->requestRepository->expects($this->never())->method('findById');
        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new RejectDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Request ID is required');

        $processor->process(
            null,
            $this->createMock(Operation::class),
            [],
        );
    }

    // -------------------------------------------------------------------------
    // ExtendRequestDeadlineProcessor tests
    // -------------------------------------------------------------------------

    public function testExtendDeadlineDispatchesCommand(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId);

        $this->requestRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->with($this->callback(fn ($id) => $id instanceof DataSubjectRequestId && $id->equals($requestId)))
            ->willReturn($domainRequest);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($requestId) {
                return $command instanceof ExtendRequestDeadlineCommand
                    && $command->requestId->equals($requestId);
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $processor = new ExtendRequestDeadlineProcessor($this->commandBus, $this->requestRepository);

        $result = $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );

        $this->assertInstanceOf(DataSubjectRequestEntity::class, $result);
        $this->assertSame($requestId->toString(), $result->getId());
    }

    public function testExtendDeadlineThrowsWhenNotFound(): void
    {
        $requestId = DataSubjectRequestId::generate();

        $this->requestRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new ExtendRequestDeadlineProcessor($this->commandBus, $this->requestRepository);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage(sprintf('Data subject request "%s" not found', $requestId->toString()));

        $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );
    }

    public function testExtendDeadlineThrowsNotFoundWhenIdMissing(): void
    {
        $this->requestRepository->expects($this->never())->method('findById');
        $this->commandBus->expects($this->never())->method('dispatch');

        $processor = new ExtendRequestDeadlineProcessor($this->commandBus, $this->requestRepository);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Request ID is required');

        $processor->process(
            null,
            $this->createMock(Operation::class),
            [],
        );
    }

    public function testExtendDeadlineFallsBackToOriginalRequestWhenUpdatedNotFound(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId);

        // First call returns the request; second call (after dispatch) returns null
        $this->requestRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($domainRequest, null);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $processor = new ExtendRequestDeadlineProcessor($this->commandBus, $this->requestRepository);

        // Falls back to original $request via `$updatedRequest ?? $request`
        $result = $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );

        $this->assertInstanceOf(DataSubjectRequestEntity::class, $result);
        $this->assertSame($requestId->toString(), $result->getId());
    }

    // -------------------------------------------------------------------------
    // Additional edge-case / branch coverage tests
    // -------------------------------------------------------------------------

    public function testReviewDSRFallsBackToOriginalRequestWhenUpdatedNotFound(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId);

        $requestStack = $this->makeRequestStack(['reviewNotes' => 'Investigated thoroughly']);

        // First call returns domain model; second call returns null (simulates race condition)
        $this->requestRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($domainRequest, null);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $processor = new ReviewDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $result = $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );

        $this->assertInstanceOf(DataSubjectRequestEntity::class, $result);
        $this->assertSame($requestId->toString(), $result->getId());
    }

    public function testRejectDSRFallsBackToOriginalRequestWhenUpdatedNotFound(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId);

        $rejectionReason = 'Request rejected due to insufficient identity verification provided.';
        $requestStack = $this->makeRequestStack(['rejectionReason' => $rejectionReason]);

        $this->requestRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($domainRequest, null);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $processor = new RejectDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $result = $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );

        $this->assertInstanceOf(DataSubjectRequestEntity::class, $result);
        $this->assertSame($requestId->toString(), $result->getId());
    }

    public function testCompleteDSRFallsBackToOriginalWhenUpdatedNotFound(): void
    {
        $requestId = DataSubjectRequestId::generate();
        $domainRequest = $this->buildDomainRequest($requestId, 'erasure', 'under_review');

        $requestStack = $this->makeRequestStack([]);

        $this->requestRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($domainRequest, null);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $processor = new CompleteDataSubjectRequestProcessor(
            $this->commandBus,
            $this->requestRepository,
            $requestStack,
        );

        $result = $processor->process(
            null,
            $this->createMock(Operation::class),
            ['id' => $requestId->toString()],
        );

        $this->assertInstanceOf(DataSubjectRequestEntity::class, $result);
        $this->assertSame($requestId->toString(), $result->getId());
    }

    public function testSubmitDSRWithNullReason(): void
    {
        $requestId = DataSubjectRequestId::generate();

        // Build entity via mock since DataSubjectRequestEntity::fromDomainModel sets reason from domain
        $entity = $this->createMock(DataSubjectRequestEntity::class);
        $entity->method('getCustomerId')->willReturn(self::CUSTOMER_ID);
        $entity->method('getRequestType')->willReturn('erasure');
        $entity->method('getReason')->willReturn(null);

        $now = new \DateTimeImmutable();
        $domainRequest = DataSubjectRequest::reconstituteFromPersistence(
            id: $requestId,
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerId: CustomerId::fromString(self::CUSTOMER_ID),
            requestType: RequestType::fromString('erasure'),
            status: RequestStatus::fromString('pending'),
            reason: null,
            reviewNotes: null,
            rejectionReason: null,
            exportData: null,
            submittedAt: $now,
            completedAt: null,
            deadline: $now->modify('+30 days'),
            isExtended: false,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) {
                return $command instanceof SubmitDataSubjectRequestCommand
                    && $command->requestType->value() === 'erasure'
                    && null === $command->reason;
            }))
            ->willReturn(new Envelope(new \stdClass(), [new HandledStamp($requestId, 'handler')]));

        $this->requestRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($domainRequest);

        $processor = new SubmitDataSubjectRequestProcessor($this->commandBus, $this->requestRepository);

        $result = $processor->process(
            $entity,
            $this->createMock(Operation::class),
            [],
            ['tenant_id' => self::TENANT_ID],
        );

        $this->assertInstanceOf(DataSubjectRequestEntity::class, $result);
        $this->assertSame('erasure', $result->getRequestType());
    }
}
