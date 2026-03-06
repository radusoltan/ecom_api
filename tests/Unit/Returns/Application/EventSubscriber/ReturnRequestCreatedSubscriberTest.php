<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Application\EventSubscriber;

use App\Returns\Application\EventSubscriber\ReturnRequestCreatedSubscriber;
use App\Returns\Domain\Event\ReturnRequestCreated;
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

#[CoversClass(ReturnRequestCreatedSubscriber::class)]
final class ReturnRequestCreatedSubscriberTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private ReturnRequestCreatedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new ReturnRequestCreatedSubscriber(
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
        $subscribedEvents = ReturnRequestCreatedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(ReturnRequestCreated::class, $subscribedEvents);
        self::assertSame('onReturnRequestCreated', $subscribedEvents[ReturnRequestCreated::class]);
    }

    #[Test]
    public function itSubscribesToExactlyOneEvent(): void
    {
        $subscribedEvents = ReturnRequestCreatedSubscriber::getSubscribedEvents();

        self::assertCount(1, $subscribedEvents);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestCreated - success path
    // -----------------------------------------------------------------------

    #[Test]
    public function itLogsReturnRequestCreationOnEvent(): void
    {
        $returnRequestId = ReturnRequestId::generate();
        $tenantId = TenantId::generate();
        $orderId = '550e8400-e29b-41d4-a716-446655440000';
        $reason = 'Item arrived completely broken and unusable';

        $event = ReturnRequestCreated::create(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            orderId: $orderId,
            reason: $reason,
        );

        $capturedInfoCalls = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedInfoCalls): void {
                $capturedInfoCalls[$message] = $context;
            });

        $this->mailer->method('send');

        $this->subscriber->onReturnRequestCreated($event);

        self::assertArrayHasKey('Return request created', $capturedInfoCalls);
        $context = $capturedInfoCalls['Return request created'];
        self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
        self::assertSame($tenantId->toString(), $context['tenantId']);
        self::assertSame($orderId, $context['orderId']);
        self::assertSame($reason, $context['reason']);
    }

    #[Test]
    public function itSendsEmailOnEvent(): void
    {
        $event = $this->makeEvent();

        $this->mailer
            ->expects(self::once())
            ->method('send');

        $this->logger->method('info');

        $this->subscriber->onReturnRequestCreated($event);
    }

    #[Test]
    public function itLogsSuccessAfterEmailIsSent(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestCreated::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::generate(),
            orderId: '550e8400-e29b-41d4-a716-446655440000',
            reason: 'Wrong item delivered, completely different product',
        );

        $loggedMessages = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$loggedMessages): void {
                $loggedMessages[] = $message;
            });

        $this->mailer->method('send');

        $this->subscriber->onReturnRequestCreated($event);

        self::assertContains('Return request created', $loggedMessages);
        self::assertContains('RMA confirmation email sent', $loggedMessages);
    }

    // -----------------------------------------------------------------------
    // onReturnRequestCreated - email transport failure path
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

        // Should not throw - transport errors are swallowed
        $this->subscriber->onReturnRequestCreated($event);

        // If we reach here the exception was correctly caught
        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenMailerTransportFails(): void
    {
        $returnRequestId = ReturnRequestId::generate();

        $event = ReturnRequestCreated::create(
            returnRequestId: $returnRequestId,
            tenantId: TenantId::generate(),
            orderId: '550e8400-e29b-41d4-a716-446655440000',
            reason: 'Product packaging severely damaged during shipping',
        );

        $this->mailer
            ->method('send')
            ->willThrowException(new TransportException('SMTP server unavailable'));

        $this->logger->method('info');

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to send RMA confirmation email',
                self::callback(function (array $context) use ($returnRequestId): bool {
                    self::assertSame($returnRequestId->toString(), $context['returnRequestId']);
                    self::assertArrayHasKey('error', $context);

                    return true;
                })
            );

        $this->subscriber->onReturnRequestCreated($event);
    }

    #[Test]
    public function itLogsInfoBeforeAttemptingEmailEvenWhenMailerFails(): void
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

        $this->subscriber->onReturnRequestCreated($event);

        // 'Return request created' info log must always fire regardless of email outcome
        self::assertGreaterThanOrEqual(1, $infoCallCount);
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

        $this->subscriber->onReturnRequestCreated($event);

        self::assertArrayHasKey('error', $capturedContext);
        self::assertSame($errorMessage, $capturedContext['error']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeEvent(): ReturnRequestCreated
    {
        return ReturnRequestCreated::create(
            returnRequestId: ReturnRequestId::generate(),
            tenantId: TenantId::generate(),
            orderId: '550e8400-e29b-41d4-a716-446655440000',
            reason: 'Item does not match description, wrong colour received',
        );
    }
}
