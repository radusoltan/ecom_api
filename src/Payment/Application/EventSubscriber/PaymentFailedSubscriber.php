<?php

declare(strict_types=1);

namespace App\Payment\Application\EventSubscriber;

use App\Payment\Domain\Event\PaymentFailed;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Payment Failed Subscriber.
 *
 * Handles actions when a payment fails.
 * - Sends payment failure notification email to customer
 * - Logs failure for monitoring and fraud detection
 * - Could trigger retry logic or alternative payment methods
 * - Alerts fraud detection system if suspicious
 */
final readonly class PaymentFailedSubscriber implements EventSubscriberInterface
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
            PaymentFailed::class => 'onPaymentFailed',
        ];
    }

    public function onPaymentFailed(PaymentFailed $event): void
    {
        try {
            // Log failure for monitoring and analysis
            $this->logger->warning('Payment failed', [
                'payment_id' => $event->paymentId->toString(),
                'error_message' => $event->errorMessage,
                'occurred_on' => date('Y-m-d H:i:s'),
            ]);

            // Send failure notification email
            $this->sendFailureNotificationEmail($event);

            // TODO: Check for fraud patterns
            // Example: if ($this->fraudDetector->isSuspicious($event)) { ... }

            // TODO: Trigger retry logic for transient errors
            // Example: if ($this->isTransientError($event->errorMessage)) { $this->scheduleRetry($event->paymentId); }

            $this->logger->info('Payment failed subscriber completed successfully', [
                'payment_id' => $event->paymentId->toString(),
            ]);
        } catch (\Throwable $e) {
            // Log error but don't throw
            $this->logger->error('PaymentFailedSubscriber failed', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sendFailureNotificationEmail(PaymentFailed $event): void
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

            $this->logger->info('Payment failure notification email sent', [
                'payment_id' => $event->paymentId->toString(),
            ]);
        } catch (\Throwable $e) {
            // Log email failure but don't throw
            $this->logger->error('Failed to send payment failure notification email', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildHtmlEmailBody(PaymentFailed $event): string
    {
        $sanitizedError = $this->sanitizeErrorMessage($event->errorMessage);
        $currentDate = date('F j, Y \a\t g:i A');

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
                    <h1>❌ Payment Failed</h1>
                </div>
                <div class="content">
                    <p>Dear Customer,</p>
                    <p>We were unable to process your payment. Your order has not been completed.</p>

                    <div class="info-box">
                        <p><strong>Payment Details:</strong></p>
                        <p>Payment ID: <code>{$event->paymentId->toString()}</code></p>
                        <p>Date: {$currentDate}</p>
                        <p>Error: {$sanitizedError}</p>
                    </div>

                    <div class="action-box">
                        <p><strong>What to do next:</strong></p>
                        <ul>
                            <li>Check that your payment information is correct</li>
                            <li>Ensure you have sufficient funds available</li>
                            <li>Contact your bank if you believe this is an error</li>
                            <li>Try using a different payment method</li>
                        </ul>
                    </div>

                    <p style="text-align: center;">
                        <a href="#" class="btn">Try Again</a>
                    </p>

                    <p>If you continue to experience issues, please contact our customer support team for assistance.</p>
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

    private function buildTextEmailBody(PaymentFailed $event): string
    {
        $sanitizedError = $this->sanitizeErrorMessage($event->errorMessage);
        $currentDate = date('F j, Y \a\t g:i A');

        return <<<TEXT
        PAYMENT FAILED

        Dear Customer,

        We were unable to process your payment. Your order has not been completed.

        Payment Details:
        - Payment ID: {$event->paymentId->toString()}
        - Date: {$currentDate}
        - Error: {$sanitizedError}

        WHAT TO DO NEXT:
        - Check that your payment information is correct
        - Ensure you have sufficient funds available
        - Contact your bank if you believe this is an error
        - Try using a different payment method

        If you continue to experience issues, please contact our customer support team for assistance.

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
            $sanitized = preg_replace($pattern, $replacement, $sanitized);
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
