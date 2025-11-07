<?php

declare(strict_types=1);

namespace App\Payment\Application\EventSubscriber;

use App\Order\Application\Command\UpdateOrderStatusCommand;
use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Payment Authorized Subscriber.
 *
 * Handles actions when a payment is successfully authorized by the gateway.
 * - Updates order status to 'paid'
 * - Logs authorization for audit trail
 * - Could trigger fraud detection checks
 * - Could notify external systems
 */
final readonly class PaymentAuthorizedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private MessageBusInterface $commandBus,
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentAuthorized::class => 'onPaymentAuthorized',
        ];
    }

    public function onPaymentAuthorized(PaymentAuthorized $event): void
    {
        try {
            // Fetch payment to get order information
            $payment = $this->paymentRepository->findById($event->paymentId, $event->tenantId);

            if (null === $payment) {
                $this->logger->error('Payment not found when handling PaymentAuthorized event', [
                    'payment_id' => $event->paymentId->toString(),
                    'tenant_id' => $event->tenantId->toString(),
                ]);

                return;
            }

            // Log authorization for audit trail
            $this->logger->info('Payment authorized successfully', [
                'payment_id' => $event->paymentId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'order_id' => $payment->orderId(),
                'gateway_transaction_id' => $event->gatewayTransactionId,
                'occurred_on' => date('Y-m-d H:i:s'),
            ]);

            // Update order status to 'paid'
            $this->commandBus->dispatch(new UpdateOrderStatusCommand(
                orderId: $payment->orderId(),
                tenantId: $event->tenantId->toString(),
                newStatus: 'paid'
            ));

            $this->logger->info('Order status updated to paid', [
                'order_id' => $payment->orderId(),
                'payment_id' => $event->paymentId->toString(),
            ]);

            // TODO: Add fraud detection checks here
            // Example: $this->fraudDetectionService->analyzeTransaction($event->paymentId);

            // TODO: Notify external systems (analytics, CRM, etc.)
            // Example: $this->analyticsService->trackEvent('payment_authorized', [...]);
        } catch (\Throwable $e) {
            // Log error but don't throw - subscriber failures shouldn't block payment flow
            $this->logger->error('PaymentAuthorizedSubscriber failed', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
