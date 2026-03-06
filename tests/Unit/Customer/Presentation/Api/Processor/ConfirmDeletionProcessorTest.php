<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Customer\Application\Command\ConfirmAccountDeletion\ConfirmAccountDeletionCommand;
use App\Customer\Application\DTO\DeletionRequestStatusDTO;
use App\Customer\Application\Query\GetDeletionRequestStatus\GetDeletionRequestStatusQuery;
use App\Customer\Presentation\Api\Processor\ConfirmDeletionProcessor;
use App\Customer\Presentation\Api\Resource\DeletionRequestResource;
use App\Shared\Application\Service\TenantContextInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ConfirmDeletionProcessor::class)]
final class ConfirmDeletionProcessorTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const CUSTOMER_ID = 'bbbbcccc-bbbb-4bbb-8bbb-bbbbbbbbcccc';
    private const REQUEST_ID = 'ccccdddd-cccc-4ccc-8ccc-ccccccccdddd';

    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;
    private TenantContextInterface $tenantContext;
    private ConfirmDeletionProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = $this->createMock(MessageBusInterface::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        $this->tenantContext
            ->method('getCurrentTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_ID));

        $this->processor = new ConfirmDeletionProcessor(
            $this->commandBus,
            $this->queryBus,
            $this->tenantContext,
        );
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itFetchesStatusDispatchesCommandAndReturnsMappedResource(): void
    {
        $statusDto = $this->buildDeletionRequestStatusDTO(status: 'pending_confirmation');
        $confirmedDto = $this->buildDeletionRequestStatusDTO(status: 'confirmed');

        // First query: get current status
        $statusEnvelope = new Envelope(new \stdClass(), [new HandledStamp($statusDto, 'handler')]);
        // Second query: get updated status after confirmation
        $confirmedEnvelope = new Envelope(new \stdClass(), [new HandledStamp($confirmedDto, 'handler')]);

        $this->queryBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::isInstanceOf(GetDeletionRequestStatusQuery::class))
            ->willReturnOnConsecutiveCalls($statusEnvelope, $confirmedEnvelope);

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ConfirmAccountDeletionCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process(
            null,
            $operation,
            ['customerId' => self::CUSTOMER_ID],
            []
        );

        self::assertInstanceOf(DeletionRequestResource::class, $result);
        self::assertSame($confirmedDto->id, $result->id);
        self::assertSame($confirmedDto->customerId, $result->customerId);
        self::assertSame($confirmedDto->status, $result->status);
        self::assertNotNull($result->message);
        self::assertStringContainsString('Deletion confirmed', $result->message);
    }

    #[Test]
    public function itMapsAllFieldsFromDtoToResource(): void
    {
        $dto = $this->buildDeletionRequestStatusDTO(
            status: 'confirmed',
            reason: 'No longer using service',
            holdReason: null,
            confirmedAt: '2024-06-01T12:00:00+00:00',
            completedAt: null,
            canBeCancelled: true,
            isOnHold: false,
            canBeExecuted: false,
        );

        $statusEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);
        $confirmedEnvelope = new Envelope(new \stdClass(), [new HandledStamp($dto, 'handler')]);

        $this->queryBus
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($statusEnvelope, $confirmedEnvelope);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID], []);

        self::assertSame('No longer using service', $result->reason);
        self::assertNull($result->holdReason);
        self::assertSame('2024-06-01T12:00:00+00:00', $result->confirmedAt);
        self::assertNull($result->completedAt);
        self::assertTrue($result->canBeCancelled);
        self::assertFalse($result->isOnHold);
        self::assertFalse($result->canBeExecuted);
    }

    // ---------------------------------------------------------------
    // Validation guards – URI variables
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenCustomerIdMissingFromUriVariables(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Customer ID is required');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, [], []);
    }

    // ---------------------------------------------------------------
    // Validation guards – tenant
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenTenantContextIsNull(): void
    {
        $tenantContext = $this->createMock(TenantContextInterface::class);
        $tenantContext->method('getCurrentTenantId')->willReturn(null);

        $processor = new ConfirmDeletionProcessor($this->commandBus, $this->queryBus, $tenantContext);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Tenant context is required');

        $operation = $this->createMock(Operation::class);
        $processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Status query failure paths
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenInitialQueryHandlerReturnsNoStamp(): void
    {
        $this->queryBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenNoPendingDeletionRequestFound(): void
    {
        $statusEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);
        $this->queryBus->method('dispatch')->willReturn($statusEnvelope);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('No pending deletion request found');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenSecondQueryHandlerReturnsNoStamp(): void
    {
        $statusDto = $this->buildDeletionRequestStatusDTO();

        $statusEnvelope = new Envelope(new \stdClass(), [new HandledStamp($statusDto, 'handler')]);
        $noStampEnvelope = new Envelope(new \stdClass());

        $this->queryBus
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($statusEnvelope, $noStampEnvelope);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for query');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    #[Test]
    public function itThrowsWhenSecondQueryHandlerReturnsNull(): void
    {
        $statusDto = $this->buildDeletionRequestStatusDTO();

        $statusEnvelope = new Envelope(new \stdClass(), [new HandledStamp($statusDto, 'handler')]);
        $nullEnvelope = new Envelope(new \stdClass(), [new HandledStamp(null, 'handler')]);

        $this->queryBus
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls($statusEnvelope, $nullEnvelope);

        $this->commandBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Deletion request not found after confirmation');

        $operation = $this->createMock(Operation::class);
        $this->processor->process(null, $operation, ['customerId' => self::CUSTOMER_ID], []);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildDeletionRequestStatusDTO(
        string $status = 'pending_confirmation',
        ?string $reason = null,
        ?string $holdReason = null,
        ?string $confirmedAt = null,
        ?string $completedAt = null,
        bool $canBeCancelled = true,
        bool $isOnHold = false,
        bool $canBeExecuted = false,
    ): DeletionRequestStatusDTO {
        return new DeletionRequestStatusDTO(
            id: self::REQUEST_ID,
            customerId: self::CUSTOMER_ID,
            status: $status,
            statusLabel: ucfirst(str_replace('_', ' ', $status)),
            reason: $reason,
            holdReason: $holdReason,
            scheduledFor: '2024-07-01T00:00:00+00:00',
            confirmedAt: $confirmedAt,
            completedAt: $completedAt,
            createdAt: '2024-06-01T00:00:00+00:00',
            canBeCancelled: $canBeCancelled,
            isOnHold: $isOnHold,
            canBeExecuted: $canBeExecuted,
        );
    }
}
