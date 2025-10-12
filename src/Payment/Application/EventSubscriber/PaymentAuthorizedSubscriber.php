<?php

declare(strict_types=1);

namespace App\Payment\Application\EventSubscriber;

use App\Payment\Domain\Event\PaymentAuthorized;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Payment Authorized Subscriber
 *
 * Handles actions when a payment is successfully authorized by the gateway.
 * - Logs authorization for audit trail
 * - Could trigger fraud detection checks
 * - Could notify external systems
 */
final readonly class PaymentAuthorizedSubscriber implements EventSubscriberInterface
{
    public function __construct(
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
            // Log authorization for audit trail
            $this->logger->info('Payment authorized successfully', [
                'payment_id' => $event->paymentId->toString(),
                'gateway_transaction_id' => $event->gatewayTransactionId,
                'occurred_on' => date('Y-m-d H:i:s'),
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
