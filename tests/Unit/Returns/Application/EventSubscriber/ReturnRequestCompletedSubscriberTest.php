<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\EventSubscriber;

use App\Returns\Application\EventSubscriber\ReturnRequestCompletedSubscriber;
use App\Returns\Domain\Event\ReturnRequestCompleted;
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

#[CoversClass(ReturnRequestCompletedSubscriber::class)]
final class ReturnRequestCompletedSubscriberTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private ReturnRequestCompletedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new ReturnRequestCompletedSubscriber(
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
        $subscribedEvents = ReturnRequestCompletedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(ReturnRequestCompleted::class, $subscribedEvents);
        self::assertSame('onReturnRequestCompleted', $subscribedEvents[ReturnRequestCompleted::class]);
    }

    #[Test]
    public function itSubscribesToExactlyOneEvent(): void
    {
        $subscribedEvents = ReturnRequestCompletedSubscriber::getSubscribedEvents();

        self::assertCount(1, $subscribedEvents);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestCompleted - success path
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsCompletionWithRefundDetailsOnEvent(): void
    {
        $returnRequestId = ReturnRequestId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $refundAmount = 4999;
        $refundCurrency = 'USD';

        $event = ReturnRequestCompleted::create(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            refundAmount: $refundAmount,
            refundCurrency: $refundCurrency,
        );

        $capturedInfoCalls = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedInfoCalls): void {
                $capturedInfoCalls[$message] = $context;
            });

        $this->mailer->method('send');

        $this->subscriber->onReturnRequestCompleted($event);

        self::assertArrayHasKey('Return request completed', $capturedInfoCalls);
        $context = $capturedInfoCalls['Return request completed'];
        self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
        self::assertSame($tenantId->toString(), $context['tenantId']);
        self::assertSame($refundAmount, $context['refundAmount']);
        self::assertSame($refundCurrency, $context['refundCurrency']);
    }

    #[Test]
    public function itTriggersRefundProcessLogOnEvent(): void
    {
        $returnRequestId = ReturnRequestId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = ReturnRequestCompleted::create(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            refundAmount: 2500,
            refundCurrency: 'EUR',
        );

        $capturedInfoCalls = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedInfoCalls): void {
                $capturedInfoCalls[$message] = $context;
            });

        $this->mailer->method('send');

        $this->subscriber->onReturnRequestCompleted($event);

        self::assertArrayHasKey('Return completed, refund process pending payment lookup', $capturedInfoCalls);
        $context = $capturedInfoCalls['Return completed, refund process pending payment lookup'];
        self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
        self::assertSame($tenantId->toString(), $context['tenantId']);
        self::assertSame(2500, $context['refundAmount']);
        self::assertSame('EUR', $context['refundCurrency']);
        self::assertSame('payment_refund_pending', $context['action']);
    }

    #[Test]
    public function itSendsRefundConfirmationEmailOnEvent(): void
    {
        $event = $this->makeEvent();

        $this->mailer
            ->expects(self::once())
            ->method('send');

        $this->logger->method('info');

        $this->subscriber->onReturnRequestCompleted($event);
    }

    #[Test]
    public function itLogsSuccessAfterRefundConfirmationEmailIsSent(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestCompleted::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            refundAmount: 9999,
            refundCurrency: 'USD',
        );

        $loggedMessages = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message) use (&$loggedMessages): void {
                $loggedMessages[] = $message;
            });

        $this->mailer->method('send');

        $this->subscriber->onReturnRequestCompleted($event);

        self::assertContains('Return request completed', $loggedMessages);
        self::assertContains('Return completed, refund process pending payment lookup', $loggedMessages);
        self::assertContains('Refund confirmation email sent', $loggedMessages);
    }

    #[Test]
    public function itLogsRefundConfirmationEmailSentWithCorrectReturnRequestId(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestCompleted::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            refundAmount: 1500,
            refundCurrency: 'GBP',
        );

        $capturedInfoCalls = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedInfoCalls): void {
                $capturedInfoCalls[$message] = $context;
            });

        $this->mailer->method('send');

        $this->subscriber->onReturnRequestCompleted($event);

        self::assertArrayHasKey('Refund confirmation email sent', $capturedInfoCalls);
        self::assertSame($returnRequestId->toString(), $capturedInfoCalls['Refund confirmation email sent']['returnRequestId']);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestCompleted - email transport failure path
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

        // Transport errors for email must not block completion processing
        $this->subscriber->onReturnRequestCompleted($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenMailerTransportFails(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestCompleted::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            refundAmount: 3000,
            refundCurrency: 'USD',
        );

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException('SMTP server unavailable'));

        $this->logger->method('info');

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to send refund confirmation email',
                self::callback(function (array $context) use ($returnRequestId): bool {
                    self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
                    self::assertArrayHasKey('error', $context);

                    return true;
                })
            );

        $this->subscriber->onReturnRequestCompleted($event);
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

        $this->subscriber->onReturnRequestCompleted($event);

        self::assertArrayHasKey('error', $capturedContext);
        self::assertSame($errorMessage, $capturedContext['error']);
    }

    #[Test]
    public function itStillLogsRefundProcessWhenEmailFails(): void
    {
        $event = $this->makeEvent();

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException('Connection timed out'));

        $loggedMessages = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message) use (&$loggedMessages): void {
                $loggedMessages[] = $message;
            });

        $this->logger->method('error');

        $this->subscriber->onReturnRequestCompleted($event);

        // Refund process log fires independently of email sending
        self::assertContains('Return completed, refund process pending payment lookup', $loggedMessages);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeEvent(): ReturnRequestCompleted
    {
        return ReturnRequestCompleted::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            refundAmount: 4999,
            refundCurrency: 'USD',
        );
    }
}
