<?php

declare(strict_types=1);

namespace App\Notifications\Application\EventSubscriber;

use App\Notifications\Domain\Service\EmailSenderInterface;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles Payment domain events by sending notification emails to customers.
 *
 * This subscriber listens to payment-related domain events and dispatches
 * email notifications using the SymfonyEmailSender service.
 *
 * Business Rules:
 * - Send confirmation email when payment is captured (successful payment)
 * - Send failure notification when payment fails
 * - Send refund confirmation when payment is refunded
 * - Email failures should be logged but not block payment processing
 * - All emails are sent with graceful error handling
 */
final readonly class PaymentNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EmailSenderInterface $emailSender,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentCaptured::class => 'onPaymentCaptured',
            PaymentFailed::class => 'onPaymentFailed',
            PaymentRefunded::class => 'onPaymentRefunded',
        ];
    }

    public function onPaymentCaptured(PaymentCaptured $event): void
    {
        try {
            $this->emailSender->send(
                to: 'customer@example.com', // TODO: Get customer email from order/payment entity
                subject: 'Payment Confirmed',
                templateName: 'payment/payment_captured',
                context: [
                    'customerName' => 'Customer', // TODO: Get from customer entity
                    'paymentId' => $event->paymentId->toString(),
                    'orderId' => $event->orderId ?? 'N/A',
                    'amount' => sprintf('%.2f', $event->capturedAmount->getAmount() / 100),
                    'currency' => $event->capturedAmount->getCurrency()->getCurrencyCode(),
                ],
            );

            $this->logger->info('Payment captured notification email sent', [
                'paymentId' => $event->paymentId->toString(),
                'orderId' => $event->orderId,
                'tenantId' => $event->tenantId->toString(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send payment captured email', [
                'paymentId' => $event->paymentId->toString(),
                'orderId' => $event->orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function onPaymentFailed(PaymentFailed $event): void
    {
        try {
            $this->emailSender->send(
                to: 'customer@example.com', // TODO: Get customer email from order/payment entity
                subject: 'Payment Failed',
                templateName: 'payment/payment_failed',
                context: [
                    'customerName' => 'Customer', // TODO: Get from customer entity
                    'paymentId' => $event->paymentId->toString(),
                    'errorMessage' => $event->errorMessage,
                ],
            );

            $this->logger->info('Payment failed notification email sent', [
                'paymentId' => $event->paymentId->toString(),
                'tenantId' => $event->tenantId->toString(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send payment failed email', [
                'paymentId' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function onPaymentRefunded(PaymentRefunded $event): void
    {
        try {
            $this->emailSender->send(
                to: 'customer@example.com', // TODO: Get customer email from order/payment entity
                subject: 'Payment Refunded',
                templateName: 'payment/payment_refunded',
                context: [
                    'customerName' => 'Customer', // TODO: Get from customer entity
                    'paymentId' => $event->paymentId->toString(),
                    'amount' => sprintf('%.2f', $event->refundedAmount->getAmount() / 100),
                    'currency' => $event->refundedAmount->getCurrency()->getCurrencyCode(),
                ],
            );

            $this->logger->info('Payment refunded notification email sent', [
                'paymentId' => $event->paymentId->toString(),
                'tenantId' => $event->tenantId->toString(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send payment refunded email', [
                'paymentId' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
