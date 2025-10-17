<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Order\Domain\Event\OrderStatusChanged;
use App\Order\Domain\Model\OrderStatus;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private OrderRepositoryInterface $orderRepository,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private string $senderEmail = 'orders@ecommerce.local',
        private string $senderName = 'E-Commerce Platform',
        private string $defaultLocale = 'en'
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
        // Notify for paid status (payment confirmed)
        // Don't notify for pending (already handled by OrderPlacedSubscriber)
        // Don't notify for cancelled (handled by OrderCancelledSubscriber)
        // Don't notify for shipped/delivered (handled by FulfillmentShippedSubscriber)
        return $newStatus->isPaid();
    }

    private function sendStatusChangeEmail(OrderStatusChanged $event): void
    {
        // Fetch order to get customer email and details
        $order = $this->orderRepository->findById($event->orderId);

        if ($order === null) {
            $this->logger->warning('Cannot send status change email - order not found', [
                'orderId' => $event->orderId->toString(),
            ]);
            return;
        }

        $locale = $this->defaultLocale; // TODO: Get from customer preferences or tenant settings

        $total = $order->total()->getAmount() / 100;
        $currency = $order->total()->getCurrency()->getCurrencyCode();

        $email = (new TemplatedEmail())
            ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
            ->to($order->customerEmail())
            ->subject($this->translator->trans('emails.order.paid.title', [], 'emails', $locale))
            ->htmlTemplate('emails/order/order_paid.html.twig')
            ->context([
                'locale' => $locale,
                'customerName' => 'Customer', // TODO: Get from customer entity
                'orderId' => $order->id()->toString(),
                'paymentDate' => new \DateTimeImmutable(),
                'total' => sprintf('%.2f', $total),
                'currency' => $currency,
                'orderViewUrl' => '#', // TODO: Generate proper order view URL
            ]);

        $this->mailer->send($email);
    }
}
