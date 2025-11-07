<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles OrderCancelled domain events by triggering refund process and notifying customer.
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
        // Fetch order to get customer email and details
        $order = $this->orderRepository->findById($event->orderId);

        if (null === $order) {
            $this->logger->warning('Cannot send cancellation email - order not found', [
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
            ->subject($this->translator->trans('emails.order.cancelled.title', [], 'emails', $locale))
            ->htmlTemplate('emails/order/order_cancelled.html.twig')
            ->context([
                'locale' => $locale,
                'customerName' => 'Customer', // TODO: Get from customer entity
                'orderId' => $order->id()->toString(),
                'cancelledDate' => new \DateTimeImmutable(),
                'total' => sprintf('%.2f', $total),
                'currency' => $currency,
                'refundAmount' => sprintf('%.2f', $total), // Assuming full refund
                'orderViewUrl' => '#', // TODO: Generate proper order view URL
            ]);

        $this->mailer->send($email);
    }
}
