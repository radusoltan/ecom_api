<?php

declare(strict_types=1);

namespace App\AuditLog\Application\EventSubscriber;

use App\AuditLog\Application\Command\LogAuditEntry\LogAuditEntry;
use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Event\OrderPaid;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentCreated;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Review\Domain\Event\ReviewApproved;
use App\Review\Domain\Event\ReviewRejected;
use App\Review\Domain\Event\ReviewSubmitted;
use App\User\Domain\Event\UserCreated;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Automatically logs all domain events to the audit log
 *
 * This subscriber listens to domain events and creates audit log entries
 * to maintain a comprehensive audit trail of all actions in the system.
 *
 * Business Rules:
 * - All domain events should be logged
 * - Log entries include user, action, resource, timestamp, and context
 * - System-initiated events logged with null userId
 * - Failures should be logged but not block event processing
 */
final readonly class DomainEventAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // User events
            UserCreated::class => 'onUserCreated',

            // Order events
            OrderPlaced::class => 'onOrderPlaced',
            OrderPaid::class => 'onOrderPaid',
            OrderStatusChanged::class => 'onOrderStatusChanged',
            OrderCancelled::class => 'onOrderCancelled',

            // Payment events
            PaymentCreated::class => 'onPaymentCreated',
            PaymentAuthorized::class => 'onPaymentAuthorized',
            PaymentCaptured::class => 'onPaymentCaptured',
            PaymentRefunded::class => 'onPaymentRefunded',
            PaymentCancelled::class => 'onPaymentCancelled',
            PaymentFailed::class => 'onPaymentFailed',

            // Review events
            ReviewSubmitted::class => 'onReviewSubmitted',
            ReviewApproved::class => 'onReviewApproved',
            ReviewRejected::class => 'onReviewRejected',
        ];
    }

    // User Events
    public function onUserCreated(UserCreated $event): void
    {
        $this->logEvent(
            tenantId: null, // Users are cross-tenant
            actionType: 'create',
            resourceType: 'user',
            resourceId: $event->userId->toString(),
            metadata: [
                'email' => $event->email,
                'username' => $event->username,
                'event' => 'UserCreated',
            ]
        );
    }

    // Order Events
    public function onOrderPlaced(OrderPlaced $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'place',
            resourceType: 'order',
            resourceId: $event->orderId->toString(),
            metadata: [
                'customerId' => $event->customerId->toString(),
                'totalInCents' => $event->totalInCents,
                'currency' => $event->currency,
                'event' => 'OrderPlaced',
            ]
        );
    }

    public function onOrderPaid(OrderPaid $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'order',
            resourceId: $event->orderId->toString(),
            metadata: [
                'paymentId' => $event->paymentId,
                'paidAmountInCents' => $event->paidAmountInCents,
                'event' => 'OrderPaid',
            ]
        );
    }

    public function onOrderStatusChanged(OrderStatusChanged $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'order',
            resourceId: $event->orderId->toString(),
            metadata: [
                'oldStatus' => $event->oldStatus->value(),
                'newStatus' => $event->newStatus->value(),
                'event' => 'OrderStatusChanged',
            ]
        );
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'cancel',
            resourceType: 'order',
            resourceId: $event->orderId->toString(),
            metadata: [
                'reason' => $event->reason,
                'event' => 'OrderCancelled',
            ]
        );
    }

    // Payment Events
    public function onPaymentCreated(PaymentCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'orderId' => $event->orderId->toString(),
                'amountInCents' => $event->amountInCents,
                'currency' => $event->currency,
                'gateway' => $event->gateway,
                'event' => 'PaymentCreated',
            ]
        );
    }

    public function onPaymentAuthorized(PaymentAuthorized $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'authorize',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'orderId' => $event->orderId->toString(),
                'authorizedAmountInCents' => $event->authorizedAmountInCents,
                'transactionId' => $event->transactionId,
                'event' => 'PaymentAuthorized',
            ]
        );
    }

    public function onPaymentCaptured(PaymentCaptured $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'capture',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'orderId' => $event->orderId->toString(),
                'capturedAmountInCents' => $event->capturedAmountInCents,
                'transactionId' => $event->transactionId,
                'event' => 'PaymentCaptured',
            ]
        );
    }

    public function onPaymentRefunded(PaymentRefunded $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'refund',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'refundedAmountInCents' => $event->refundedAmountInCents,
                'reason' => $event->reason,
                'event' => 'PaymentRefunded',
            ]
        );
    }

    public function onPaymentCancelled(PaymentCancelled $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'cancel',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'reason' => $event->reason,
                'event' => 'PaymentCancelled',
            ]
        );
    }

    public function onPaymentFailed(PaymentFailed $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'failureReason' => $event->failureReason,
                'event' => 'PaymentFailed',
            ]
        );
    }

    // Review Events
    public function onReviewSubmitted(ReviewSubmitted $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'review',
            resourceId: $event->reviewId->toString(),
            metadata: [
                'productId' => $event->productId->toString(),
                'customerId' => $event->customerId->toString(),
                'rating' => $event->rating,
                'event' => 'ReviewSubmitted',
            ]
        );
    }

    public function onReviewApproved(ReviewApproved $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'review',
            resourceId: $event->reviewId->toString(),
            metadata: [
                'approvedBy' => $event->approvedBy->toString(),
                'event' => 'ReviewApproved',
            ]
        );
    }

    public function onReviewRejected(ReviewRejected $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'review',
            resourceId: $event->reviewId->toString(),
            metadata: [
                'rejectedBy' => $event->rejectedBy->toString(),
                'reason' => $event->reason,
                'event' => 'ReviewRejected',
            ]
        );
    }

    /**
     * Log an audit entry
     */
    private function logEvent(
        ?string $tenantId,
        string $actionType,
        string $resourceType,
        string $resourceId,
        array $metadata = []
    ): void {
        try {
            // Get current user from security context
            $userId = null;
            $token = $this->tokenStorage->getToken();
            if ($token && $token->getUser() instanceof UserInterface) {
                $user = $token->getUser();
                // Assuming the user identifier is the user ID
                $userId = $user->getUserIdentifier();
            }

            // Get IP address and user agent from request
            $ipAddress = null;
            $userAgent = null;
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $ipAddress = $request->getClientIp();
                $userAgent = $request->headers->get('User-Agent');
            }

            // Use a default tenant ID for cross-tenant events (like UserCreated)
            if ($tenantId === null) {
                $tenantId = '00000000-0000-0000-0000-000000000000'; // System tenant
            }

            $command = new LogAuditEntry(
                tenantId: $tenantId,
                userId: $userId,
                actionType: $actionType,
                resourceType: $resourceType,
                resourceId: $resourceId,
                metadata: $metadata,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->commandBus->dispatch($command);

            $this->logger->debug('Audit log entry created', [
                'actionType' => $actionType,
                'resourceType' => $resourceType,
                'resourceId' => $resourceId,
            ]);
        } catch (\Throwable $exception) {
            // Log error but don't throw - audit logging shouldn't block domain events
            $this->logger->error('Failed to create audit log entry', [
                'actionType' => $actionType,
                'resourceType' => $resourceType,
                'resourceId' => $resourceId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}
