<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\EventSubscriber;

use App\Returns\Application\EventSubscriber\ReturnRequestInspectedSubscriber;
use App\Returns\Domain\Event\ReturnRequestInspected;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ReturnRequestInspectedSubscriber::class)]
final class ReturnRequestInspectedSubscriberTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private MessageBusInterface&MockObject $commandBus;
    private ReturnRequestRepositoryInterface&MockObject $repository;
    private ReturnRequestInspectedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->repository = $this->createMock(ReturnRequestRepositoryInterface::class);

        $this->subscriber = new ReturnRequestInspectedSubscriber(
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'noreply@example.com',
            senderName: 'E-Commerce Platform',
            commandBus: $this->commandBus,
            returnRequestRepository: $this->repository,
        );
    }

    // -----------------------------------------------------------------------
    // getSubscribedEvents
    // -----------------------------------------------------------------------

    #[Test]
    public function itImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    #[Test]
    public function itReturnsCorrectSubscribedEvents(): void
    {
        $subscribedEvents = ReturnRequestInspectedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(ReturnRequestInspected::class, $subscribedEvents);
        self::assertSame('onReturnRequestInspected', $subscribedEvents[ReturnRequestInspected::class]);
    }

    #[Test]
    public function itSubscribesToExactlyOneEvent(): void
    {
        $subscribedEvents = ReturnRequestInspectedSubscriber::getSubscribedEvents();

        self::assertCount(1, $subscribedEvents);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestInspected - logging
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsInspectionDetailsOnEvent(): void
    {
        $returnRequestId = ReturnRequestId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = ReturnRequestInspected::create(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            isResellable: true,
            inspectionNotes: 'Item is in good condition and suitable for resale',
        );

        $capturedInfoCalls = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedInfoCalls): void {
                $capturedInfoCalls[$message] = $context;
            });
        $this->logger->method('warning');
        $this->logger->method('error');

        // Repository returns null so stock adjustment is skipped cleanly (logged as error)
        $this->repository->method('findById')->willReturn(null);
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestInspected($event);

        self::assertArrayHasKey('Return request inspected', $capturedInfoCalls);
        $context = $capturedInfoCalls['Return request inspected'];
        self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
        self::assertSame($tenantId->toString(), $context['tenantId']);
        self::assertTrue($context['isResellable']);
        self::assertSame('Item is in good condition and suitable for resale', $context['inspectionNotes']);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestInspected - non-resellable path
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsWarningForNonResellableItem(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestInspected::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: false,
            inspectionNotes: 'Item is heavily damaged and cannot be resold',
        );

        $capturedWarnings = [];
        $this->logger
            ->method('warning')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedWarnings): void {
                $capturedWarnings[$message] = $context;
            });
        $this->logger->method('info');
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestInspected($event);

        self::assertArrayHasKey('Non-resellable item - quality assurance review needed', $capturedWarnings);
        $context = $capturedWarnings['Non-resellable item - quality assurance review needed'];
        self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
        self::assertSame('Item is heavily damaged and cannot be resold', $context['inspectionNotes']);
        self::assertSame('quality_review', $context['action']);
    }

    #[Test]
    public function itDoesNotDispatchCommandForNonResellableItem(): void
    {
        $event = ReturnRequestInspected::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: false,
            inspectionNotes: 'Item arrived back severely damaged beyond repair',
        );

        $this->commandBus
            ->expects(self::never())
            ->method('dispatch');

        $this->logger->method('warning');
        $this->logger->method('info');
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestInspected($event);
    }

    #[Test]
    public function itDoesNotQueryRepositoryForNonResellableItem(): void
    {
        $event = ReturnRequestInspected::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: false,
            inspectionNotes: 'Item was opened and used, cannot be restocked',
        );

        $this->repository
            ->expects(self::never())
            ->method('findById');

        $this->logger->method('warning');
        $this->logger->method('info');
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestInspected($event);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestInspected - resellable path, return request not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsErrorWhenResellableItemReturnRequestNotFound(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestInspected::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: true,
            inspectionNotes: 'Item is clean and suitable for resale',
        );

        $this->repository->method('findById')->willReturn(null);

        $this->logger->method('info');
        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Cannot adjust stock: Return request not found',
                self::callback(function (array $context) use ($returnRequestId): bool {
                    self::assertSame($returnRequestId->toString(), $context['returnRequestId']);

                    return true;
                })
            );
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestInspected($event);
    }

    #[Test]
    public function itDoesNotDispatchCommandWhenReturnRequestNotFound(): void
    {
        $event = ReturnRequestInspected::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: true,
            inspectionNotes: 'Item is in resellable condition',
        );

        $this->repository->method('findById')->willReturn(null);

        $this->commandBus
            ->expects(self::never())
            ->method('dispatch');

        $this->logger->method('info');
        $this->logger->method('error');
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestInspected($event);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestInspected - resellable path, no warehouse assigned
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsWarningWhenResellableItemHasNoWarehouseAssigned(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestInspected::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: true,
            inspectionNotes: 'Good condition, ready for resale',
        );

        $returnRequest = $this->createMock(ReturnRequest::class);
        $returnRequest->method('warehouseId')->willReturn(null);

        $this->repository->method('findById')->willReturn($returnRequest);

        $this->logger->method('info');
        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Cannot adjust stock: No warehouse assigned to return',
                self::callback(function (array $context) use ($returnRequestId): bool {
                    self::assertSame($returnRequestId->toString(), $context['returnRequestId']);

                    return true;
                })
            );
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestInspected($event);
    }

    #[Test]
    public function itDoesNotDispatchCommandWhenNoWarehouseAssigned(): void
    {
        $event = ReturnRequestInspected::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: true,
            inspectionNotes: 'Item is pristine, ready to sell again',
        );

        $returnRequest = $this->createMock(ReturnRequest::class);
        $returnRequest->method('warehouseId')->willReturn(null);

        $this->repository->method('findById')->willReturn($returnRequest);

        $this->commandBus
            ->expects(self::never())
            ->method('dispatch');

        $this->logger->method('info');
        $this->logger->method('warning');
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestInspected($event);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestInspected - email sending
    // -----------------------------------------------------------------------

    #[Test]
    public function itSendsInspectionResultEmailForResellableItem(): void
    {
        $event = ReturnRequestInspected::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: true,
            inspectionNotes: 'Item is in excellent condition',
        );

        $this->repository->method('findById')->willReturn(null);

        $this->mailer
            ->expects(self::once())
            ->method('send');

        $this->logger->method('info');
        $this->logger->method('error');

        $this->subscriber->onReturnRequestInspected($event);
    }

    #[Test]
    public function itSendsInspectionResultEmailForNonResellableItem(): void
    {
        $event = ReturnRequestInspected::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: false,
            inspectionNotes: 'Item is damaged and cannot be resold',
        );

        $this->mailer
            ->expects(self::once())
            ->method('send');

        $this->logger->method('info');
        $this->logger->method('warning');

        $this->subscriber->onReturnRequestInspected($event);
    }

    #[Test]
    public function itLogsInspectionEmailSentWithCorrectReturnRequestId(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestInspected::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: false,
            inspectionNotes: 'Item has cosmetic damage only',
        );

        $capturedInfoCalls = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedInfoCalls): void {
                $capturedInfoCalls[$message] = $context;
            });
        $this->logger->method('warning');
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestInspected($event);

        self::assertArrayHasKey('Inspection result email sent', $capturedInfoCalls);
        self::assertSame($returnRequestId->toString(), $capturedInfoCalls['Inspection result email sent']['returnRequestId']);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestInspected - email transport failure path
    // -----------------------------------------------------------------------

    #[Test]
    public function itDoesNotThrowWhenMailerTransportFails(): void
    {
        $event = ReturnRequestInspected::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: false,
            inspectionNotes: 'Item has minor scratches but is functionally intact',
        );

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException('SMTP connection refused'));

        $this->logger->method('info');
        $this->logger->method('warning');
        $this->logger->method('error');

        // Transport errors must be swallowed - inspection handling must not block
        $this->subscriber->onReturnRequestInspected($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenMailerTransportFails(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestInspected::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: false,
            inspectionNotes: 'Item is not functioning correctly',
        );

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException('SMTP server unavailable'));

        $this->logger->method('info');
        $this->logger->method('warning');

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to send inspection result email',
                self::callback(function (array $context) use ($returnRequestId): bool {
                    self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
                    self::assertArrayHasKey('error', $context);

                    return true;
                })
            );

        $this->subscriber->onReturnRequestInspected($event);
    }

    #[Test]
    public function itIncludesTransportErrorMessageInErrorLogWhenEmailFails(): void
    {
        $errorMessage = 'Connection refused by mail server at smtp.example.com:587';

        $event = ReturnRequestInspected::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            isResellable: false,
            inspectionNotes: 'Item shows severe physical damage',
        );

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException($errorMessage));

        $this->logger->method('info');
        $this->logger->method('warning');

        $capturedContext = [];
        $this->logger
            ->method('error')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onReturnRequestInspected($event);

        self::assertArrayHasKey('error', $capturedContext);
        self::assertSame($errorMessage, $capturedContext['error']);
    }
}
