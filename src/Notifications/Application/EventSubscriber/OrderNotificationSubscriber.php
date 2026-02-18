<?php

declare(strict_types=1);

namespace App\Notifications\Application\EventSubscriber;

use App\Notifications\Domain\Service\EmailSenderInterface;
use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\OrderStatusChanged;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles Order domain events by sending notification emails to customers.
 *
 * This subscriber listens to order-related domain events and dispatches
 * email notifications using the SymfonyEmailSender service.
 *
 * Business Rules:
 * - Send confirmation email immediately after order placement
 * - Send status update emails for specific status changes (paid, shipped, delivered)
 * - Send cancellation email when order is cancelled
 * - Email failures should be logged but not block order processing
 * - All emails are sent with graceful error handling
 */
final readonly class OrderNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EmailSenderInterface $emailSender,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlaced::class => 'onOrderPlaced',
            OrderStatusChanged::class => 'onOrderStatusChanged',
            OrderCancelled::class => 'onOrderCancelled',
        ];
    }

    public function onOrderPlaced(OrderPlaced $event): void
    {
        try {
            $this->emailSender->send(
                to: $event->customerEmail,
                subject: 'Order Confirmation',
                templateName: 'order/order_placed',
                context: [
                    'customerName' => 'Customer', // TODO: Get from customer entity
                    'orderId' => $event->orderId->toString(),
                    'orderDate' => new \DateTimeImmutable(),
                    'total' => sprintf('%.2f', $event->total->getAmount() / 100),
                    'currency' => $event->total->getCurrency()->getCurrencyCode(),
                    'status' => 'pending',
                ],
            );

            $this->logger->info('Order placed notification email sent', [
                'orderId' => $event->orderId->toString(),
                'customerEmail' => $event->customerEmail,
                'tenantId' => $event->tenantId->toString(),
            ]);
        } catch (\Throwable $e) {
            // Log error but don't throw - email failure shouldn't block order placement
            $this->logger->error('Failed to send order placed email', [
                'orderId' => $event->orderId->toString(),
                'customerEmail' => $event->customerEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function onOrderStatusChanged(OrderStatusChanged $event): void
    {
        // Map status to template - only send emails for specific statuses
        $templateMap = [
            'paid' => 'order/order_paid',
            'shipped' => 'order/order_shipped',
            'delivered' => 'order/order_delivered',
        ];

        $newStatus = $event->newStatus->value();
        $template = $templateMap[$newStatus] ?? null;

        // Skip if no template for this status
        if (null === $template) {
            $this->logger->debug('No email template for order status change', [
                'orderId' => $event->orderId->toString(),
                'newStatus' => $newStatus,
            ]);

            return;
        }

        try {
            $this->emailSender->send(
                to: 'customer@example.com', // TODO: Get customer email from order entity
                subject: 'Order Update',
                templateName: $template,
                context: [
                    'customerName' => 'Customer', // TODO: Get from customer entity
                    'orderId' => $event->orderId->toString(),
                    'status' => $newStatus,
                    'previousStatus' => $event->oldStatus->value(),
                ],
            );

            $this->logger->info('Order status changed notification email sent', [
                'orderId' => $event->orderId->toString(),
                'newStatus' => $newStatus,
                'tenantId' => $event->tenantId->toString(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send order status changed email', [
                'orderId' => $event->orderId->toString(),
                'newStatus' => $newStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        try {
            $this->emailSender->send(
                to: 'customer@example.com', // TODO: Get customer email from order entity
                subject: 'Order Cancelled',
                templateName: 'order/order_cancelled',
                context: [
                    'customerName' => 'Customer', // TODO: Get from customer entity
                    'orderId' => $event->orderId->toString(),
                    'reason' => $event->reason ?? 'No reason provided',
                ],
            );

            $this->logger->info('Order cancelled notification email sent', [
                'orderId' => $event->orderId->toString(),
                'tenantId' => $event->tenantId->toString(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send order cancelled email', [
                'orderId' => $event->orderId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
