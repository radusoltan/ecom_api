<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Order\Domain\Event\OrderCancelled;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Handles OrderCancelled domain events by triggering refund process and notifying customer
 *
 * Business Rules:
 * - Notify customer immediately when order is cancelled
 * - Trigger refund process (payment integration)
 * - Log cancellation for analytics
 * - Email failures should not block cancellation
 */
final readonly class OrderCancelledSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail = 'orders@ecommerce.local',
        private string $senderName = 'E-Commerce Platform'
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderCancelled::class => 'onOrderCancelled',
        ];
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        try {
            // Log cancellation for analytics
            $this->logger->info('Order cancelled', [
                'orderId' => $event->orderId->toString(),
                'previousStatus' => $event->previousStatus->value(),
            ]);

            // Trigger refund process
            $this->triggerRefund($event);

            // Send cancellation notification email
            $this->sendCancellationEmail($event);

            $this->logger->info('Order cancellation processed successfully', [
                'orderId' => $event->orderId->toString(),
            ]);
        } catch (\Throwable $exception) {
            // Log error but don't throw - notification failure shouldn't block cancellation
            $this->logger->error('Failed to process order cancellation notification', [
                'orderId' => $event->orderId->toString(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    private function triggerRefund(OrderCancelled $event): void
    {
        // TODO: Integrate with payment gateway (Stripe/PayPal) to process refund
        // This is a placeholder for the refund logic

        $this->logger->info('Refund process initiated', [
            'orderId' => $event->orderId->toString(),
            'previousStatus' => $event->previousStatus->value(),
        ]);

        // Future implementation:
        // 1. Fetch order payment details from repository
        // 2. Call payment gateway API to process refund
        // 3. Update order with refund transaction ID
        // 4. Send refund confirmation email
        //
        // Example:
        // $this->paymentGateway->refund(
        //     orderId: $event->orderId->toString(),
        //     reason: 'Order cancelled by customer'
        // );
    }

    private function sendCancellationEmail(OrderCancelled $event): void
    {
        // Note: In a real application, we would fetch the order details from the repository
        // to get the customer email. For this demo, we'll log the attempt.

        $this->logger->warning('Cannot send cancellation email - customer email not available in event', [
            'orderId' => $event->orderId->toString(),
        ]);

        // Placeholder for when customer email is available:
        // $email = (new Email())
        //     ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
        //     ->to($customerEmail)
        //     ->subject('Order Cancelled - Refund Processing')
        //     ->html($this->buildEmailBody($event))
        //     ->text($this->buildEmailTextBody($event));
        //
        // $this->mailer->send($email);
    }

    private function buildEmailBody(OrderCancelled $event, string $orderId): string
    {
        $previousStatus = ucfirst($event->previousStatus->value());

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f44336; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; margin: 20px 0; }
                .status-badge { background: #f44336; color: white; padding: 10px 20px; border-radius: 5px; display: inline-block; }
                .order-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #f44336; }
                .refund-info { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✗ Order Cancelled</h1>
                </div>
                <div class="content">
                    <p>Dear Customer,</p>
                    <p>Your order has been cancelled as requested.</p>

                    <div class="order-details">
                        <h3>Order Information</h3>
                        <p><strong>Order ID:</strong> {$orderId}</p>
                        <p><strong>Previous Status:</strong> {$previousStatus}</p>
                        <p><strong>Current Status:</strong> <span class="status-badge">Cancelled</span></p>
                    </div>

                    <div class="refund-info">
                        <h3>💰 Refund Information</h3>
                        <p>If you made a payment for this order, a refund has been initiated.</p>
                        <p>Please allow 5-10 business days for the refund to appear in your account, depending on your payment method.</p>
                    </div>

                    <p>If you have any questions about this cancellation or the refund process, please contact our support team.</p>
                    <p>We're sorry to see this order cancelled. We hope to serve you again soon!</p>
                </div>
                <div class="footer">
                    <p>This is an automated message, please do not reply.</p>
                    <p>&copy; 2025 E-Commerce Platform. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private function buildEmailTextBody(OrderCancelled $event, string $orderId): string
    {
        $previousStatus = ucfirst($event->previousStatus->value());

        return <<<TEXT
        ORDER CANCELLED

        Dear Customer,

        Your order has been cancelled as requested.

        Order Information:
        - Order ID: {$orderId}
        - Previous Status: {$previousStatus}
        - Current Status: Cancelled

        REFUND INFORMATION
        If you made a payment for this order, a refund has been initiated.
        Please allow 5-10 business days for the refund to appear in your account, depending on your payment method.

        If you have any questions about this cancellation or the refund process, please contact our support team.

        We're sorry to see this order cancelled. We hope to serve you again soon!

        ---
        This is an automated message, please do not reply.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
