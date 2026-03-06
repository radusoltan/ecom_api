<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\EventSubscriber;

use App\Returns\Application\EventSubscriber\ReturnRequestRejectedSubscriber;
use App\Returns\Domain\Event\ReturnRequestRejected;
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

#[CoversClass(ReturnRequestRejectedSubscriber::class)]
final class ReturnRequestRejectedSubscriberTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private ReturnRequestRejectedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new ReturnRequestRejectedSubscriber(
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'noreply@example.com',
            senderName: 'E-Commerce Platform',
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
        $subscribedEvents = ReturnRequestRejectedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(ReturnRequestRejected::class, $subscribedEvents);
        self::assertSame('onReturnRequestRejected', $subscribedEvents[ReturnRequestRejected::class]);
    }

    #[Test]
    public function itSubscribesToExactlyOneEvent(): void
    {
        $subscribedEvents = ReturnRequestRejectedSubscriber::getSubscribedEvents();

        self::assertCount(1, $subscribedEvents);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestRejected - success path
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsWarningWithRejectionDetailsOnEvent(): void
    {
        $returnRequestId = ReturnRequestId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $rejectionReason = 'Item is outside the 30-day return window';

        $event = ReturnRequestRejected::create(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            rejectionReason: $rejectionReason,
        );

        $capturedWarnings = [];
        $this->logger
            ->method('warning')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedWarnings): void {
                $capturedWarnings[$message] = $context;
            });

        $this->logger->method('info');
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestRejected($event);

        self::assertArrayHasKey('Return request rejected', $capturedWarnings);
        $context = $capturedWarnings['Return request rejected'];
        self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
        self::assertSame($tenantId->toString(), $context['tenantId']);
        self::assertSame($rejectionReason, $context['rejectionReason']);
    }

    #[Test]
    public function itSendsRejectionEmailOnEvent(): void
    {
        $event = $this->makeEvent();

        $this->mailer
            ->expects(self::once())
            ->method('send');

        $this->logger->method('warning');
        $this->logger->method('info');

        $this->subscriber->onReturnRequestRejected($event);
    }

    #[Test]
    public function itLogsSuccessAfterRejectionEmailIsSent(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestRejected::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            rejectionReason: 'Item shows signs of heavy usage beyond normal wear and tear',
        );

        $loggedMessages = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$loggedMessages): void {
                $loggedMessages[] = $message;
            });

        $this->logger->method('warning');
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestRejected($event);

        self::assertContains('Rejection email sent', $loggedMessages);
    }

    #[Test]
    public function itLogsRejectionEmailSentWithCorrectReturnRequestId(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestRejected::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            rejectionReason: 'Item is not in original packaging as required by policy',
        );

        $capturedInfoCalls = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedInfoCalls): void {
                $capturedInfoCalls[$message] = $context;
            });

        $this->logger->method('warning');
        $this->mailer->method('send');

        $this->subscriber->onReturnRequestRejected($event);

        self::assertArrayHasKey('Rejection email sent', $capturedInfoCalls);
        self::assertSame($returnRequestId->toString(), $capturedInfoCalls['Rejection email sent']['returnRequestId']);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestRejected - email transport failure path
    // -----------------------------------------------------------------------

    #[Test]
    public function itDoesNotThrowWhenMailerTransportFails(): void
    {
        $event = $this->makeEvent();

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException('SMTP connection refused'));

        $this->logger->method('warning');
        $this->logger->method('info');
        $this->logger->method('error');

        // Transport errors must be swallowed - rejection handling must not block
        $this->subscriber->onReturnRequestRejected($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenMailerTransportFails(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestRejected::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            rejectionReason: 'Item was not purchased from our store',
        );

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException('SMTP server unavailable'));

        $this->logger->method('warning');
        $this->logger->method('info');

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to send rejection email',
                self::callback(function (array $context) use ($returnRequestId): bool {
                    self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
                    self::assertArrayHasKey('error', $context);

                    return true;
                })
            );

        $this->subscriber->onReturnRequestRejected($event);
    }

    #[Test]
    public function itIncludesTransportErrorMessageInErrorLog(): void
    {
        $errorMessage = 'Connection refused by mail server at smtp.example.com:587';
        $event = $this->makeEvent();

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException($errorMessage));

        $this->logger->method('warning');
        $this->logger->method('info');

        $capturedContext = [];
        $this->logger
            ->method('error')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onReturnRequestRejected($event);

        self::assertArrayHasKey('error', $capturedContext);
        self::assertSame($errorMessage, $capturedContext['error']);
    }

    #[Test]
    public function itLogsWarningBeforeAttemptingEmailEvenWhenMailerFails(): void
    {
        $event = $this->makeEvent();

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException('Connection timed out'));

        $warningCallCount = 0;
        $this->logger
            ->method('warning')
            ->willReturnCallback(function () use (&$warningCallCount): void {
                ++$warningCallCount;
            });

        $this->logger->method('info');
        $this->logger->method('error');

        $this->subscriber->onReturnRequestRejected($event);

        // 'Return request rejected' warning log must always fire regardless of email outcome
        self::assertGreaterThanOrEqual(1, $warningCallCount);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeEvent(): ReturnRequestRejected
    {
        return ReturnRequestRejected::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            rejectionReason: 'Item does not meet our return policy requirements',
        );
    }
}
