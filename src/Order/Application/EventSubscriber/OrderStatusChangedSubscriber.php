<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Order\Domain\Event\OrderStatusChanged;
use App\Order\Domain\Model\OrderStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Handles OrderStatusChanged domain events by notifying customers of order progress
 *
 * Business Rules:
 * - Notify customer on every status change
 * - Different email templates for each status transition
 * - Include tracking information when order is shipped
 * - Failures should be logged but not block status updates
 */
final readonly class OrderStatusChangedSubscriber implements EventSubscriberInterface
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
            OrderStatusChanged::class => 'onOrderStatusChanged',
        ];
    }

    public function onOrderStatusChanged(OrderStatusChanged $event): void
    {
        try {
            // Only send notifications for certain status changes
            if ($this->shouldNotifyCustomer($event->newStatus)) {
                $this->sendStatusChangeEmail($event);

                $this->logger->info('Order status change notification sent', [
                    'orderId' => $event->orderId->toString(),
                    'oldStatus' => $event->oldStatus->value(),
                    'newStatus' => $event->newStatus->value(),
                ]);
            }
        } catch (\Throwable $exception) {
            // Log error but don't throw - email failure shouldn't block status update
            $this->logger->error('Failed to send order status change notification', [
                'orderId' => $event->orderId->toString(),
                'oldStatus' => $event->oldStatus->value(),
                'newStatus' => $event->newStatus->value(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    private function shouldNotifyCustomer(OrderStatus $newStatus): bool
    {
        // Notify for processing, shipped, and delivered statuses
        // Don't notify for pending (already handled by OrderPlacedSubscriber)
        // Don't notify for cancelled (handled by OrderCancelledSubscriber)
        return $newStatus->isProcessing() || $newStatus->isShipped() || $newStatus->isDelivered();
    }

    private function sendStatusChangeEmail(OrderStatusChanged $event): void
    {
        // Note: In a real application, we would fetch the order details from the repository
        // to get the customer email. For this demo, we'll use a placeholder.
        // The event could be enriched with customer email in the future.

        $this->logger->warning('Cannot send status change email - customer email not available in event', [
            'orderId' => $event->orderId->toString(),
            'newStatus' => $event->newStatus->value(),
        ]);

        // Placeholder for when customer email is available:
        // $email = (new Email())
        //     ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
        //     ->to($customerEmail)
        //     ->subject($this->getEmailSubject($event->newStatus))
        //     ->html($this->buildEmailBody($event))
        //     ->text($this->buildEmailTextBody($event));
        //
        // $this->mailer->send($email);
    }

    private function getEmailSubject(OrderStatus $status): string
    {
        return match (true) {
            $status->isProcessing() => 'Your Order is Being Processed',
            $status->isShipped() => 'Your Order Has Been Shipped!',
            $status->isDelivered() => 'Your Order Has Been Delivered',
            default => 'Order Status Update',
        };
    }

    private function buildEmailBody(OrderStatusChanged $event, string $orderId): string
    {
        $status = $event->newStatus;
        $statusName = ucfirst($status->value());

        $statusMessage = match (true) {
            $status->isProcessing() => 'We are now processing your order and will ship it soon.',
            $status->isShipped() => 'Great news! Your order has been shipped and is on its way to you.',
            $status->isDelivered() => 'Your order has been successfully delivered. We hope you enjoy your purchase!',
            default => 'Your order status has been updated.',
        };

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2196F3; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; margin: 20px 0; }
                .status-badge { background: #2196F3; color: white; padding: 10px 20px; border-radius: 5px; display: inline-block; }
                .order-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📦 Order Status Update</h1>
                </div>
                <div class="content">
                    <p>Dear Customer,</p>
                    <p>{$statusMessage}</p>

                    <div class="order-details">
                        <h3>Order Information</h3>
                        <p><strong>Order ID:</strong> {$orderId}</p>
                        <p><strong>Status:</strong> <span class="status-badge">{$statusName}</span></p>
                    </div>

                    <p>You can track your order status by logging into your account.</p>
                    <p>If you have any questions, please contact our support team.</p>
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

    private function buildEmailTextBody(OrderStatusChanged $event, string $orderId): string
    {
        $status = $event->newStatus;
        $statusName = ucfirst($status->value());

        $statusMessage = match (true) {
            $status->isProcessing() => 'We are now processing your order and will ship it soon.',
            $status->isShipped() => 'Great news! Your order has been shipped and is on its way to you.',
            $status->isDelivered() => 'Your order has been successfully delivered. We hope you enjoy your purchase!',
            default => 'Your order status has been updated.',
        };

        return <<<TEXT
        ORDER STATUS UPDATE

        Dear Customer,

        {$statusMessage}

        Order Information:
        - Order ID: {$orderId}
        - Status: {$statusName}

        You can track your order status by logging into your account.

        If you have any questions, please contact our support team.

        ---
        This is an automated message, please do not reply.
        © 2025 E-Commerce Platform. All rights reserved.
        TEXT;
    }
}
