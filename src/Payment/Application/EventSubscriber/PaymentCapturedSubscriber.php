<?php

declare(strict_types=1);

namespace App\Payment\Application\EventSubscriber;

use App\Payment\Domain\Event\PaymentCaptured;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Payment Captured Subscriber
 *
 * Handles actions when a payment is successfully captured (funds transferred).
 * - Sends payment confirmation email to customer
 * - Updates order status to "paid" (order integration)
 * - Logs capture for audit trail
 * - Triggers fulfillment workflow
 */
final readonly class PaymentCapturedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail = 'payments@ecommerce.local',
        private string $senderName = 'E-Commerce Platform'
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentCaptured::class => 'onPaymentCaptured',
        ];
    }

    public function onPaymentCaptured(PaymentCaptured $event): void
    {
        try {
            // Log capture for audit trail
            $this->logger->info('Payment captured successfully', [
                'payment_id' => $event->paymentId->toString(),
                'captured_amount_in_cents' => $event->capturedAmountInCents,
                'occurred_on' => date('Y-m-d H:i:s'),
            ]);

            // Send payment confirmation email
            $this->sendConfirmationEmail($event);

            // TODO: Update order status to "paid"
            // Example: $this->orderService->markAsPaid($event->orderId);

            // TODO: Trigger fulfillment workflow
            // Example: $this->fulfillmentService->startFulfillment($event->orderId);

            $this->logger->info('Payment captured subscriber completed successfully', [
                'payment_id' => $event->paymentId->toString(),
            ]);

        } catch (\Throwable $e) {
            // Log error but don't throw - subscriber failures shouldn't block payment flow
            $this->logger->error('PaymentCapturedSubscriber failed', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sendConfirmationEmail(PaymentCaptured $event): void
    {
        try {
            $amountFormatted = number_format($event->capturedAmountInCents / 100, 2);

            // Build HTML email
            $htmlBody = $this->buildHtmlEmailBody($event, $amountFormatted);

            // Build plain text email
            $textBody = $this->buildTextEmailBody($event, $amountFormatted);

            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
                ->to('customer@example.com') // TODO: Get customer email from order
                ->subject('Payment Confirmation - Order Paid Successfully')
                ->html($htmlBody)
                ->text($textBody);

            $this->mailer->send($email);

            $this->logger->info('Payment confirmation email sent', [
                'payment_id' => $event->paymentId->toString(),
            ]);

        } catch (\Throwable $e) {
            // Log email failure but don't throw - email failures shouldn't block payment
            $this->logger->error('Failed to send payment confirmation email', [
                'payment_id' => $event->paymentId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildHtmlEmailBody(PaymentCaptured $event, string $amountFormatted): string
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
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .amount { font-size: 24px; font-weight: bold; color: #4CAF50; }
                .info-box { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #4CAF50; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✅ Payment Confirmed</h1>
                </div>
                <div class="content">
                    <p>Dear Customer,</p>
                    <p>We have successfully received your payment. Your order is now being processed.</p>

                    <div class="info-box">
                        <p><strong>Payment Details:</strong></p>
                        <p>Payment ID: <code>{$event->paymentId->toString()}</code></p>
                        <p>Amount Paid: <span class="amount">\${$amountFormatted}</span></p>
                        <p>Date: {$currentDate}</p>
                    </div>

                    <p>Your order will be shipped soon. You will receive a shipping confirmation email once your order has been dispatched.</p>

                    <p>Thank you for your purchase!</p>
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

    private function buildTextEmailBody(PaymentCaptured $event, string $amountFormatted): string
    {
        $currentDate = date('F j, Y \a\t g:i A');
        return <<<TEXT
        PAYMENT CONFIRMED

        Dear Customer,

        We have successfully received your payment. Your order is now being processed.

        Payment Details:
        - Payment ID: {$event->paymentId->toString()}
        - Amount Paid: \${$amountFormatted}
        - Date: {$currentDate}

        Your order will be shipped soon. You will receive a shipping confirmation email once your order has been dispatched.

        Thank you for your purchase!

        ---
        This is an automated email. Please do not reply to this message.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
