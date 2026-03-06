<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\EventSubscriber;

use App\Returns\Application\EventSubscriber\ReturnRequestApprovedSubscriber;
use App\Returns\Domain\Event\ReturnRequestApproved;
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

#[CoversClass(ReturnRequestApprovedSubscriber::class)]
final class ReturnRequestApprovedSubscriberTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private ReturnRequestApprovedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new ReturnRequestApprovedSubscriber(
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
        $subscribedEvents = ReturnRequestApprovedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(ReturnRequestApproved::class, $subscribedEvents);
        self::assertSame('onReturnRequestApproved', $subscribedEvents[ReturnRequestApproved::class]);
    }

    #[Test]
    public function itSubscribesToExactlyOneEvent(): void
    {
        $subscribedEvents = ReturnRequestApprovedSubscriber::getSubscribedEvents();

        self::assertCount(1, $subscribedEvents);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestApproved - success path
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsReturnRequestApprovalOnEvent(): void
    {
        $returnRequestId = ReturnRequestId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = ReturnRequestApproved::create(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
        );

        $capturedInfoCalls = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedInfoCalls): void {
                $capturedInfoCalls[$message] = $context;
            });

        $this->mailer->method('send');

        $this->subscriber->onReturnRequestApproved($event);

        self::assertArrayHasKey('Return request approved', $capturedInfoCalls);
        $context = $capturedInfoCalls['Return request approved'];
        self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
        self::assertSame($tenantId->toString(), $context['tenantId']);
    }

    #[Test]
    public function itSendsReturnLabelEmailOnEvent(): void
    {
        $event = $this->makeEvent();

        $this->mailer
            ->expects(self::once())
            ->method('send');

        $this->logger->method('info');

        $this->subscriber->onReturnRequestApproved($event);
    }

    #[Test]
    public function itLogsSuccessAfterReturnLabelEmailIsSent(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestApproved::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
        );

        $loggedMessages = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$loggedMessages): void {
                $loggedMessages[] = $message;
            });

        $this->mailer->method('send');

        $this->subscriber->onReturnRequestApproved($event);

        self::assertContains('Return request approved', $loggedMessages);
        self::assertContains('Return label email sent', $loggedMessages);
    }

    #[Test]
    public function itLogsReturnLabelEmailSentWithCorrectReturnRequestId(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestApproved::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
        );

        $capturedInfoCalls = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedInfoCalls): void {
                $capturedInfoCalls[$message] = $context;
            });

        $this->mailer->method('send');

        $this->subscriber->onReturnRequestApproved($event);

        self::assertArrayHasKey('Return label email sent', $capturedInfoCalls);
        self::assertSame($returnRequestId->toString(), $capturedInfoCalls['Return label email sent']['returnRequestId']);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestApproved - email transport failure path
    // -----------------------------------------------------------------------

    #[Test]
    public function itDoesNotThrowWhenMailerTransportFails(): void
    {
        $event = $this->makeEvent();

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException('SMTP connection refused'));

        $this->logger->method('info');
        $this->logger->method('error');

        // Transport errors must be swallowed - approval should not be blocked
        $this->subscriber->onReturnRequestApproved($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenMailerTransportFails(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestApproved::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
        );

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException('SMTP server unavailable'));

        $this->logger->method('info');

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to send return label email',
                self::callback(function (array $context) use ($returnRequestId): bool {
                    self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
                    self::assertArrayHasKey('error', $context);

                    return true;
                })
            );

        $this->subscriber->onReturnRequestApproved($event);
    }

    #[Test]
    public function itIncludesTransportErrorMessageInErrorLog(): void
    {
        $errorMessage = 'Connection refused by mail server at smtp.example.com:587';
        $event = $this->makeEvent();

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException($errorMessage));

        $this->logger->method('info');

        $capturedContext = [];
        $this->logger
            ->method('error')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->subscriber->onReturnRequestApproved($event);

        self::assertArrayHasKey('error', $capturedContext);
        self::assertSame($errorMessage, $capturedContext['error']);
    }

    #[Test]
    public function itLogsApprovalInfoBeforeAttemptingEmailEvenWhenMailerFails(): void
    {
        $event = $this->makeEvent();

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException('Connection timed out'));

        $infoCallCount = 0;
        $this->logger
            ->method('info')
            ->willReturnCallback(function () use (&$infoCallCount): void {
                ++$infoCallCount;
            });

        $this->logger->method('error');

        $this->subscriber->onReturnRequestApproved($event);

        // 'Return request approved' info log must always fire regardless of email outcome
        self::assertGreaterThanOrEqual(1, $infoCallCount);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeEvent(): ReturnRequestApproved
    {
        return ReturnRequestApproved::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
        );
    }
}
