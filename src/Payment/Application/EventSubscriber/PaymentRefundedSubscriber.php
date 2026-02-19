<?php

declare(strict_types=1);

namespace App\Payment\Application\EventSubscriber;

use App\Payment\Application\Service\PaymentCustomerEmailResolver;
use App\Payment\Domain\Event\PaymentRefunded;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Payment Refunded Subscriber.
 *
 * Handles actions when a payment is refunded.
 * - Sends refund notification email to customer
 * - Updates order status to "refunded"
 * - Logs refund for audit trail
 * - Releases inventory reservations
 */
final readonly class PaymentRefundedSubscriber implements EventSubscriberInterface
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
            PaymentRefunded::class => 'onPaymentRefunded',
        ];
    }

    public function onPaymentRefunded(PaymentRefunded $event): void
    {
        try {
            // Log refund for audit trail
            $this->logger->info('Payment refunded', [
                'payment_id' => $event->paymentId->toString(),
                'refunded_amount_in_cents' => $event->refundedAmountInCents,
                'reason' => $event->reason,
                'occurred_on' => date('Y-m-d H:i:s'),
            ]);

            // Send refund notification email
            $this->sendRefundNotificationEmail($event);

            // TODO: Update order status to "refunded"
            // Example: $this->orderService->markAsRefunded($event->orderId);

            // TODO: Release inventory reservations
            // Example: $this->inventoryService->releaseReservation($event->orderId);

            $this->logger->info('Payment refunded subscriber completed successfully', [
                'payment_id' => $event->paymentId->toString(),
            ]);
        } catch (\Throwable $e) {
            // Log error but don't throw - subscriber failures shouldn't block refund flow
            $this->logger->error('PaymentRefundedSubscriber failed', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sendRefundNotificationEmail(PaymentRefunded $event): void
    {
        try {
            $customerEmail = $this->emailResolver->resolveByPaymentId($event->paymentId, $event->tenantId);

            if (null === $customerEmail) {
                $this->logger->warning('Cannot send refund email: customer email not found', [
                    'payment_id' => $event->paymentId->toString(),
                ]);

                return;
            }

            $amountFormatted = number_format($event->refundedAmountInCents / 100, 2);

            // Build HTML email
            $htmlBody = $this->buildHtmlEmailBody($event, $amountFormatted);

            // Build plain text email
            $textBody = $this->buildTextEmailBody($event, $amountFormatted);

            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
                ->to($customerEmail)
                ->subject('Refund Processed - Your Payment Has Been Refunded')
                ->html($htmlBody)
                ->text($textBody);

            $this->mailer->send($email);

            $this->logger->info('Refund notification email sent', [
                'payment_id' => $event->paymentId->toString(),
                'customer_email' => $customerEmail,
            ]);
        } catch (\Throwable $e) {
            // Log email failure but don't throw
            $this->logger->error('Failed to send refund notification email', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildHtmlEmailBody(PaymentRefunded $event, string $amountFormatted): string
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
                .header { background-color: #2196F3; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .amount { font-size: 24px; font-weight: bold; color: #2196F3; }
                .info-box { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3; }
                .notice { background-color: #FFF9C4; padding: 15px; margin: 15px 0; border-left: 4px solid #FBC02D; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>💳 Refund Processed</h1>
                </div>
                <div class="content">
                    <p>Dear Customer,</p>
                    <p>We have processed your refund. The funds will be returned to your original payment method.</p>

                    <div class="info-box">
                        <p><strong>Refund Details:</strong></p>
                        <p>Payment ID: <code>{$event->paymentId->toString()}</code></p>
                        <p>Refund Amount: <span class="amount">\${$amountFormatted}</span></p>
                        <p>Date: {$currentDate}</p>
                        <p>Reason: {$event->reason}</p>
                    </div>

                    <div class="notice">
                        <p><strong>⏱ Processing Time:</strong></p>
                        <p>Depending on your payment method and financial institution, it may take 5-10 business days for the refund to appear in your account.</p>
                    </div>

                    <p>If you have any questions about this refund, please contact our customer support team.</p>

                    <p>We apologize for any inconvenience and hope to serve you again in the future.</p>
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

    private function buildTextEmailBody(PaymentRefunded $event, string $amountFormatted): string
    {
        $currentDate = date('F j, Y \a\t g:i A');

        return <<<TEXT
        REFUND PROCESSED

        Dear Customer,

        We have processed your refund. The funds will be returned to your original payment method.

        Refund Details:
        - Payment ID: {$event->paymentId->toString()}
        - Refund Amount: \${$amountFormatted}
        - Date: {$currentDate}
        - Reason: {$event->reason}

        PROCESSING TIME:
        Depending on your payment method and financial institution, it may take 5-10 business days for the refund to appear in your account.

        If you have any questions about this refund, please contact our customer support team.

        We apologize for any inconvenience and hope to serve you again in the future.

        ---
        This is an automated email. Please do not reply to this message.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
