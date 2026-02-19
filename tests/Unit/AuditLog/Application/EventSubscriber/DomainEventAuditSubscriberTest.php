<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Application\EventSubscriber;

use App\AuditLog\Application\Command\LogAuditEntry\LogAuditEntry;
use App\AuditLog\Application\EventSubscriber\DomainEventAuditSubscriber;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentCreated;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\Event\PaymentRetryAttempted;
use App\Payment\Domain\Event\PaymentRetryExhausted;
use App\Payment\Domain\Event\PaymentRetryScheduled;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class DomainEventAuditSubscriberTest extends TestCase
{
    private MessageBusInterface $commandBus;
    private TokenStorageInterface $tokenStorage;
    private RequestStack $requestStack;
    private DomainEventAuditSubscriber $subscriber;

    /** @var list<LogAuditEntry> */
    private array $dispatchedCommands = [];

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const USER_ID = 'user-123';
    private const IP_ADDRESS = '192.168.1.1';
    private const USER_AGENT = 'TestAgent/1.0';

    protected function setUp(): void
    {
        $this->dispatchedCommands = [];

        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->commandBus->method('dispatch')
            ->willReturnCallback(function (object $message): Envelope {
                if ($message instanceof LogAuditEntry) {
                    $this->dispatchedCommands[] = $message;
                }

                return new Envelope($message);
            });

        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn(self::USER_ID);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->tokenStorage->method('getToken')->willReturn($token);

        $request = new Request(server: [
            'REMOTE_ADDR' => self::IP_ADDRESS,
            'HTTP_USER_AGENT' => self::USER_AGENT,
        ]);

        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);

        $this->subscriber = new DomainEventAuditSubscriber(
            $this->commandBus,
            $this->tokenStorage,
            $this->requestStack,
            new NullLogger(),
        );
    }

    public function testSubscribesToAllPaymentEvents(): void
    {
        $events = DomainEventAuditSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(PaymentCreated::class, $events);
        $this->assertArrayHasKey(PaymentCaptured::class, $events);
        $this->assertArrayHasKey(PaymentRefunded::class, $events);
        $this->assertArrayHasKey(PaymentCancelled::class, $events);
        $this->assertArrayHasKey(PaymentFailed::class, $events);
        $this->assertArrayHasKey(PaymentRetryScheduled::class, $events);
        $this->assertArrayHasKey(PaymentRetryAttempted::class, $events);
        $this->assertArrayHasKey(PaymentRetryExhausted::class, $events);
    }

    public function testPaymentCapturedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: 5000,
            orderId: 'order-123',
        );

        $this->subscriber->onPaymentCaptured($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame(self::USER_ID, $command->userId);
        $this->assertSame('capture', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame('order-123', $command->metadata['orderId']);
        $this->assertSame(5000, $command->metadata['capturedAmountInCents']);
        $this->assertSame('PaymentCaptured', $command->metadata['event']);
        $this->assertSame(self::IP_ADDRESS, $command->ipAddress);
        $this->assertSame(self::USER_AGENT, $command->userAgent);
    }

    public function testPaymentRefundedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmountInCents: 2500,
            reason: 'Customer request',
        );

        $this->subscriber->onPaymentRefunded($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('refund', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame(2500, $command->metadata['refundedAmountInCents']);
        $this->assertSame('Customer request', $command->metadata['reason']);
        $this->assertSame('PaymentRefunded', $command->metadata['event']);
    }

    public function testPaymentCancelledCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentCancelled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            reason: 'Order cancelled by customer',
        );

        $this->subscriber->onPaymentCancelled($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('cancel', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame('Order cancelled by customer', $command->metadata['reason']);
        $this->assertSame('PaymentCancelled', $command->metadata['event']);
    }

    public function testPaymentRetryScheduledCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $scheduledFor = new \DateTimeImmutable('2026-02-19T15:30:00+00:00');

        $event = new PaymentRetryScheduled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            retryAttempt: 2,
            scheduledFor: $scheduledFor,
            errorCode: 'gateway_timeout',
            orderId: 'order-456',
        );

        $this->subscriber->onPaymentRetryScheduled($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('retry', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame('order-456', $command->metadata['orderId']);
        $this->assertSame(2, $command->metadata['retryAttempt']);
        $this->assertSame('2026-02-19T15:30:00+00:00', $command->metadata['scheduledFor']);
        $this->assertSame('gateway_timeout', $command->metadata['errorCode']);
        $this->assertSame('PaymentRetryScheduled', $command->metadata['event']);
    }

    public function testPaymentRetryAttemptedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentRetryAttempted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            attemptNumber: 3,
            wasSuccessful: false,
            errorCode: 'insufficient_funds',
            errorMessage: 'Card declined',
        );

        $this->subscriber->onPaymentRetryAttempted($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('retry', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame(3, $command->metadata['attemptNumber']);
        $this->assertFalse($command->metadata['wasSuccessful']);
        $this->assertSame('insufficient_funds', $command->metadata['errorCode']);
        $this->assertSame('Card declined', $command->metadata['errorMessage']);
        $this->assertSame('PaymentRetryAttempted', $command->metadata['event']);
    }

    public function testPaymentRetryExhaustedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentRetryExhausted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            totalAttempts: 5,
            lastErrorCode: 'card_declined',
            lastErrorMessage: 'Insufficient funds',
            orderId: 'order-789',
        );

        $this->subscriber->onPaymentRetryExhausted($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame('order-789', $command->metadata['orderId']);
        $this->assertSame(5, $command->metadata['totalAttempts']);
        $this->assertSame('card_declined', $command->metadata['lastErrorCode']);
        $this->assertSame('Insufficient funds', $command->metadata['lastErrorMessage']);
        $this->assertSame('PaymentRetryExhausted', $command->metadata['event']);
    }

    public function testPaymentCreatedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentCreated(
            paymentId: $paymentId,
            tenantId: $tenantId,
            orderId: 'order-100',
            amountInCents: 9999,
            currency: 'EUR',
            gateway: 'stripe',
        );

        $this->subscriber->onPaymentCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('create', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame('order-100', $command->metadata['orderId']);
        $this->assertSame(9999, $command->metadata['amountInCents']);
        $this->assertSame('EUR', $command->metadata['currency']);
        $this->assertSame('stripe', $command->metadata['gateway']);
    }

    public function testPaymentFailedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentFailed(
            paymentId: $paymentId,
            tenantId: $tenantId,
            errorMessage: 'Gateway timeout',
        );

        $this->subscriber->onPaymentFailed($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('update', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame('Gateway timeout', $command->metadata['errorMessage']);
        $this->assertSame('PaymentFailed', $command->metadata['event']);
    }

    public function testAuditRecordIncludesUserContext(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: 1000,
        );

        $this->subscriber->onPaymentCaptured($event);

        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::USER_ID, $command->userId);
        $this->assertSame(self::IP_ADDRESS, $command->ipAddress);
        $this->assertSame(self::USER_AGENT, $command->userAgent);
    }

    public function testAuditRecordWithSystemContext(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $subscriber = new DomainEventAuditSubscriber(
            $this->commandBus,
            $tokenStorage,
            new RequestStack(),
            new NullLogger(),
        );

        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentRetryAttempted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            attemptNumber: 1,
            wasSuccessful: true,
        );

        $subscriber->onPaymentRetryAttempted($event);

        $command = $this->dispatchedCommands[0];
        $this->assertNull($command->userId);
        $this->assertNull($command->ipAddress);
        $this->assertNull($command->userAgent);
    }
}
