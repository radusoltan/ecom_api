<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Order\Domain\Event\OrderDelivered;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles OrderDelivered domain events by sending delivery confirmation emails to customers.
 *
 * Business Rules:
 * - Send delivery confirmation email when order is delivered
 * - Email includes order ID, delivery date, and delivery method
 * - Failures should be logged but not block order delivery
 */
final readonly class OrderDeliveredSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
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
            OrderDelivered::class => 'onOrderDelivered',
        ];
    }

    public function onOrderDelivered(OrderDelivered $event): void
    {
        try {
            $this->sendDeliveryConfirmationEmail($event);

            $this->logger->info('Order delivery confirmation email sent', [
                'orderId' => $event->orderId->toString(),
                'customerEmail' => $event->customerEmail,
                'tenantId' => $event->tenantId->toString(),
                'deliveryDate' => $event->deliveryDate->format('Y-m-d H:i:s'),
                'deliveryMethod' => $event->deliveryMethod,
            ]);
        } catch (\Throwable $exception) {
            // Log error but don't throw - email failure shouldn't block order delivery
            $this->logger->error('Failed to send order delivery confirmation email', [
                'orderId' => $event->orderId->toString(),
                'customerEmail' => $event->customerEmail,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    private function sendDeliveryConfirmationEmail(OrderDelivered $event): void
    {
        $locale = $this->defaultLocale; // TODO: Get from customer preferences or tenant settings

        $email = (new TemplatedEmail())
            ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
            ->to($event->customerEmail)
            ->subject($this->translator->trans('emails.order.delivered.title', [], 'emails', $locale))
            ->htmlTemplate('emails/order/order_delivered.html.twig')
            ->context([
                'locale' => $locale,
                'customerName' => 'Customer', // TODO: Get from customer entity
                'orderId' => $event->orderId->toString(),
                'deliveryDate' => $event->deliveryDate->format('F j, Y'),
                'deliveryTime' => $event->deliveryDate->format('g:i A'),
                'deliveryMethod' => ucfirst($event->deliveryMethod),
                'status' => 'delivered',
                'orderViewUrl' => '#', // TODO: Generate proper order view URL
            ]);

        $this->mailer->send($email);
    }
}
