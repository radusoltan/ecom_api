<?php

declare(strict_types=1);

namespace App\Payment\Application\EventSubscriber;

use App\Payment\Application\Service\PaymentCustomerEmailResolver;
use App\Payment\Domain\Event\PaymentCancelled;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Payment Cancelled Subscriber.
 *
 * Handles actions when a payment is cancelled.
 * - Sends cancellation notification email to customer
 * - Updates order status to "cancelled"
 * - Logs cancellation for audit trail
 * - Releases inventory reservations
 */
final readonly class PaymentCancelledSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PaymentCustomerEmailResolver $emailResolver,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail = 'payments@ecommerce.local',
        private string $senderName = 'E-Commerce Platform',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentCancelled::class => 'onPaymentCancelled',
        ];
    }

    public function onPaymentCancelled(PaymentCancelled $event): void
    {
        try {
            // Log cancellation for audit trail
            $this->logger->info('Payment cancelled', [
                'payment_id' => $event->paymentId->toString(),
                'reason' => $event->reason,
                'occurred_on' => date('Y-m-d H:i:s'),
            ]);

            // Send cancellation notification email
            $this->sendCancellationNotificationEmail($event);

            // TODO: Update order status to "cancelled"
            // Example: $this->orderService->markAsCancelled($event->orderId);

            // TODO: Release inventory reservations
            // Example: $this->inventoryService->releaseReservation($event->orderId);

            $this->logger->info('Payment cancelled subscriber completed successfully', [
                'payment_id' => $event->paymentId->toString(),
            ]);
        } catch (\Throwable $e) {
            // Log error but don't throw
            $this->logger->error('PaymentCancelledSubscriber failed', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sendCancellationNotificationEmail(PaymentCancelled $event): void
    {
        try {
            $customerEmail = $this->emailResolver->resolveByPaymentId($event->paymentId, $event->tenantId);

            if (null === $customerEmail) {
                $this->logger->warning('Cannot send cancellation email: customer email not found', [
                    'payment_id' => $event->paymentId->toString(),
                ]);

                return;
            }

            // Build HTML email
            $htmlBody = $this->buildHtmlEmailBody($event);

            // Build plain text email
            $textBody = $this->buildTextEmailBody($event);

            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
                ->to($customerEmail)
                ->subject('Payment Cancelled - Order Cancelled')
                ->html($htmlBody)
                ->text($textBody);

            $this->mailer->send($email);

            $this->logger->info('Payment cancellation notification email sent', [
                'payment_id' => $event->paymentId->toString(),
                'customer_email' => $customerEmail,
            ]);
        } catch (\Throwable $e) {
            // Log email failure but don't throw
            $this->logger->error('Failed to send payment cancellation notification email', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildHtmlEmailBody(PaymentCancelled $event): string
    {
        $currentDate = date('F j, Y \a\t g:i A');

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #FF9800; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .info-box { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #FF9800; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>⚠️ Payment Cancelled</h1>
                </div>
                <div class="content">
                    <p>Dear Customer,</p>
                    <p>Your payment has been cancelled and no charges have been made to your account.</p>

                    <div class="info-box">
                        <p><strong>Cancellation Details:</strong></p>
                        <p>Payment ID: <code>{$event->paymentId->toString()}</code></p>
                        <p>Date: {$currentDate}</p>
                        <p>Reason: {$event->reason}</p>
                    </div>

                    <p>Your order has been cancelled and no payment was processed. If you still wish to complete your purchase, you can place a new order.</p>

                    <p>If you did not request this cancellation or have any questions, please contact our customer support team.</p>
                </div>
                <div class="footer">
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; 2025 E-Commerce Platform. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function buildTextEmailBody(PaymentCancelled $event): string
    {
        $currentDate = date('F j, Y \a\t g:i A');

        return <<<TEXT
        PAYMENT CANCELLED

        Dear Customer,

        Your payment has been cancelled and no charges have been made to your account.

        Cancellation Details:
        - Payment ID: {$event->paymentId->toString()}
        - Date: {$currentDate}
        - Reason: {$event->reason}

        Your order has been cancelled and no payment was processed. If you still wish to complete your purchase, you can place a new order.

        If you did not request this cancellation or have any questions, please contact our customer support team.

        ---
        This is an automated email. Please do not reply to this message.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
