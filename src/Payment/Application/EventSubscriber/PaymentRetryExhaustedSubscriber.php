<?php

declare(strict_types=1);

namespace App\Payment\Application\EventSubscriber;

use App\Payment\Domain\Event\PaymentRetryExhausted;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Payment Retry Exhausted Subscriber.
 *
 * Handles actions when all payment retry attempts have been exhausted.
 * - Sends final failure notification email to customer
 * - Logs the exhausted retries for monitoring
 * - Provides customer with total attempts made and final error
 * - Suggests customer update payment method
 */
final readonly class PaymentRetryExhaustedSubscriber implements EventSubscriberInterface
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
            PaymentRetryExhausted::class => 'onPaymentRetryExhausted',
        ];
    }

    public function onPaymentRetryExhausted(PaymentRetryExhausted $event): void
    {
        try {
            // Log retry exhaustion for monitoring
            $this->logger->error('Payment retry attempts exhausted', [
                'payment_id' => $event->paymentId->toString(),
                'order_id' => $event->orderId,
                'total_attempts' => $event->totalAttempts,
                'last_error_code' => $event->lastErrorCode,
                'last_error_message' => $event->lastErrorMessage,
            ]);

            // Send final failure notification email
            $this->sendFinalFailureNotificationEmail($event);

            $this->logger->info('Payment retry exhausted subscriber completed successfully', [
                'payment_id' => $event->paymentId->toString(),
            ]);
        } catch (\Throwable $e) {
            // Log error but don't throw
            $this->logger->error('PaymentRetryExhaustedSubscriber failed', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sendFinalFailureNotificationEmail(PaymentRetryExhausted $event): void
    {
        try {
            // Build HTML email
            $htmlBody = $this->buildHtmlEmailBody($event);

            // Build plain text email
            $textBody = $this->buildTextEmailBody($event);

            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
                ->to('customer@example.com') // TODO: Get customer email from order
                ->subject('Payment Failed - Action Required')
                ->html($htmlBody)
                ->text($textBody);

            $this->mailer->send($email);

            $this->logger->info('Payment retry exhausted notification email sent', [
                'payment_id' => $event->paymentId->toString(),
            ]);
        } catch (\Throwable $e) {
            // Log email failure but don't throw
            $this->logger->error('Failed to send payment retry exhausted notification email', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildHtmlEmailBody(PaymentRetryExhausted $event): string
    {
        $currentDate = date('F j, Y \a\t g:i A');
        $sanitizedError = $this->sanitizeErrorMessage($event->lastErrorMessage);
        $errorCode = htmlspecialchars($event->lastErrorCode, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #F44336; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .info-box { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #F44336; }
                .action-box { background-color: #FFEBEE; padding: 15px; margin: 15px 0; border-left: 4px solid #F44336; }
                .btn { display: inline-block; padding: 12px 24px; background-color: #F44336; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>❌ All Retry Attempts Exhausted</h1>
                </div>
                <div class="content">
                    <p>Dear Customer,</p>
                    <p>We're sorry, but we were unable to process your payment after multiple attempts. Your order has not been completed and no charges have been made.</p>

                    <div class="info-box">
                        <p><strong>Payment Details:</strong></p>
                        <p>Payment ID: <code>{$event->paymentId->toString()}</code></p>
                        <p>Order ID: <code>{$event->orderId}</code></p>
                        <p>Date: {$currentDate}</p>
                        <p>Total Attempts Made: <strong>{$event->totalAttempts}</strong></p>
                    </div>

                    <div class="info-box">
                        <p><strong>Error Information:</strong></p>
                        <p>Error Code: {$errorCode}</p>
                        <p>Error: {$sanitizedError}</p>
                    </div>

                    <div class="action-box">
                        <p><strong>🔧 Action Required - What to do next:</strong></p>
                        <ul>
                            <li><strong>Update your payment method:</strong> Try a different card or payment option</li>
                            <li><strong>Check with your bank:</strong> Your card may have been declined for security reasons</li>
                            <li><strong>Verify your account:</strong> Ensure you have sufficient funds and correct billing information</li>
                            <li><strong>Contact support:</strong> Our team can help resolve any payment issues</li>
                        </ul>
                    </div>

                    <p style="text-align: center;">
                        <a href="#" class="btn">Update Payment Method</a>
                    </p>

                    <p>We apologize for any inconvenience. If you continue to experience issues or have questions, please contact our customer support team. We're here to help!</p>
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

    private function buildTextEmailBody(PaymentRetryExhausted $event): string
    {
        $currentDate = date('F j, Y \a\t g:i A');
        $sanitizedError = $this->sanitizeErrorMessage($event->lastErrorMessage);

        return <<<TEXT
        ALL RETRY ATTEMPTS EXHAUSTED

        Dear Customer,

        We're sorry, but we were unable to process your payment after multiple attempts. Your order has not been completed and no charges have been made.

        Payment Details:
        - Payment ID: {$event->paymentId->toString()}
        - Order ID: {$event->orderId}
        - Date: {$currentDate}
        - Total Attempts Made: {$event->totalAttempts}

        Error Information:
        - Error Code: {$event->lastErrorCode}
        - Error: {$sanitizedError}

        ACTION REQUIRED - WHAT TO DO NEXT:
        - Update your payment method: Try a different card or payment option
        - Check with your bank: Your card may have been declined for security reasons
        - Verify your account: Ensure you have sufficient funds and correct billing information
        - Contact support: Our team can help resolve any payment issues

        We apologize for any inconvenience. If you continue to experience issues or have questions, please contact our customer support team. We're here to help!

        ---
        This is an automated email. Please do not reply to this message.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }

    /**
     * Sanitize error message to avoid exposing sensitive gateway details.
     */
    private function sanitizeErrorMessage(string $errorMessage): string
    {
        // Remove technical details that might confuse customers
        $patterns = [
            '/api[_\s]?key/i' => '[REDACTED]',
            '/secret/i' => '[REDACTED]',
            '/token/i' => '[REDACTED]',
        ];

        $sanitized = $errorMessage;
        foreach ($patterns as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $sanitized);
            $sanitized = is_string($result) ? $result : $sanitized;
        }

        // Simplify common error messages
        $errorMap = [
            'insufficient_funds' => 'Insufficient funds in your account',
            'card_declined' => 'Your card was declined',
            'expired_card' => 'Your card has expired',
            'incorrect_cvc' => 'Incorrect security code',
            'processing_error' => 'A processing error occurred',
        ];

        foreach ($errorMap as $code => $message) {
            if (false !== stripos($sanitized, $code)) {
                return $message;
            }
        }

        return $sanitized;
    }
}
