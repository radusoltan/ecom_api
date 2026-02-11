<?php

declare(strict_types=1);

namespace App\Payment\Application\EventSubscriber;

use App\Payment\Domain\Event\PaymentRetryScheduled;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Payment Retry Scheduled Subscriber.
 *
 * Handles actions when a payment retry is scheduled.
 * - Sends notification email to customer about the scheduled retry
 * - Logs the retry scheduling for monitoring
 * - Provides customer with retry details (attempt number, next retry time)
 * - Explains the error that caused the failure
 */
final readonly class PaymentRetryScheduledSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail = 'payments@ecommerce.local',
        private string $senderName = 'E-Commerce Platform',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentRetryScheduled::class => 'onPaymentRetryScheduled',
        ];
    }

    public function onPaymentRetryScheduled(PaymentRetryScheduled $event): void
    {
        try {
            // Log retry scheduling for monitoring
            $this->logger->info('Payment retry scheduled', [
                'payment_id' => $event->paymentId->toString(),
                'order_id' => $event->orderId,
                'retry_attempt' => $event->retryAttempt,
                'scheduled_for' => $event->scheduledFor->format('Y-m-d H:i:s'),
                'error_code' => $event->errorCode,
            ]);

            // Send retry notification email
            $this->sendRetryNotificationEmail($event);

            $this->logger->info('Payment retry scheduled subscriber completed successfully', [
                'payment_id' => $event->paymentId->toString(),
            ]);
        } catch (\Throwable $e) {
            // Log error but don't throw
            $this->logger->error('PaymentRetryScheduledSubscriber failed', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sendRetryNotificationEmail(PaymentRetryScheduled $event): void
    {
        try {
            // Build HTML email
            $htmlBody = $this->buildHtmlEmailBody($event);

            // Build plain text email
            $textBody = $this->buildTextEmailBody($event);

            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
                ->to('customer@example.com') // TODO: Get customer email from order
                ->subject('Payment Retry Scheduled - No Action Required')
                ->html($htmlBody)
                ->text($textBody);

            $this->mailer->send($email);

            $this->logger->info('Payment retry notification email sent', [
                'payment_id' => $event->paymentId->toString(),
            ]);
        } catch (\Throwable $e) {
            // Log email failure but don't throw
            $this->logger->error('Failed to send payment retry notification email', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildHtmlEmailBody(PaymentRetryScheduled $event): string
    {
        $retryTime = $event->scheduledFor->format('F j, Y \a\t g:i A');
        $currentDate = date('F j, Y \a\t g:i A');
        $errorCode = htmlspecialchars($event->errorCode, ENT_QUOTES, 'UTF-8');

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
                .notice-box { background-color: #FFF3E0; padding: 15px; margin: 15px 0; border-left: 4px solid #FF9800; }
                .btn { display: inline-block; padding: 12px 24px; background-color: #FF9800; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔄 Payment Retry Scheduled</h1>
                </div>
                <div class="content">
                    <p>Dear Customer,</p>
                    <p>We encountered a temporary issue processing your payment. Don't worry - we'll automatically retry the payment for you.</p>

                    <div class="info-box">
                        <p><strong>Payment Details:</strong></p>
                        <p>Payment ID: <code>{$event->paymentId->toString()}</code></p>
                        <p>Order ID: <code>{$event->orderId}</code></p>
                        <p>Date: {$currentDate}</p>
                        <p>Error Code: {$errorCode}</p>
                    </div>

                    <div class="notice-box">
                        <p><strong>⏰ Automatic Retry Information:</strong></p>
                        <p>Retry Attempt: <strong>#{$event->retryAttempt}</strong></p>
                        <p>Next Retry: <strong>{$retryTime}</strong></p>
                        <p>We will automatically attempt to process your payment again at the scheduled time. You will receive a notification about the result.</p>
                    </div>

                    <div class="info-box">
                        <p><strong>What you can do:</strong></p>
                        <ul>
                            <li>Wait for the automatic retry - no action needed</li>
                            <li>Check that you have sufficient funds available</li>
                            <li>Ensure your payment method is valid and not expired</li>
                            <li>Contact your bank if needed</li>
                        </ul>
                    </div>

                    <p style="text-align: center;">
                        <a href="#" class="btn">View Order Status</a>
                    </p>

                    <p>If you have any questions or want to use a different payment method, please contact our customer support team.</p>
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

    private function buildTextEmailBody(PaymentRetryScheduled $event): string
    {
        $retryTime = $event->scheduledFor->format('F j, Y \a\t g:i A');
        $currentDate = date('F j, Y \a\t g:i A');

        return <<<TEXT
        PAYMENT RETRY SCHEDULED

        Dear Customer,

        We encountered a temporary issue processing your payment. Don't worry - we'll automatically retry the payment for you.

        Payment Details:
        - Payment ID: {$event->paymentId->toString()}
        - Order ID: {$event->orderId}
        - Date: {$currentDate}
        - Error Code: {$event->errorCode}

        AUTOMATIC RETRY INFORMATION:
        - Retry Attempt: #{$event->retryAttempt}
        - Next Retry: {$retryTime}

        We will automatically attempt to process your payment again at the scheduled time. You will receive a notification about the result.

        WHAT YOU CAN DO:
        - Wait for the automatic retry - no action needed
        - Check that you have sufficient funds available
        - Ensure your payment method is valid and not expired
        - Contact your bank if needed

        If you have any questions or want to use a different payment method, please contact our customer support team.

        ---
        This is an automated email. Please do not reply to this message.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
