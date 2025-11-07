<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Order\Domain\Event\OrderPlaced;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles OrderPlaced domain events by sending confirmation emails to customers.
 *
 * Business Rules:
 * - Send confirmation email immediately after order placement
 * - Email includes order ID, total amount, and customer details
 * - Failures should be logged but not block order placement
 */
final readonly class OrderPlacedSubscriber implements EventSubscriberInterface
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
            OrderPlaced::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(OrderPlaced $event): void
    {
        try {
            $this->sendConfirmationEmail($event);

            $this->logger->info('Order confirmation email sent', [
                'orderId' => $event->orderId->toString(),
                'customerEmail' => $event->customerEmail,
                'tenantId' => $event->tenantId->toString(),
            ]);
        } catch (\Throwable $exception) {
            // Log error but don't throw - email failure shouldn't block order placement
            $this->logger->error('Failed to send order confirmation email', [
                'orderId' => $event->orderId->toString(),
                'customerEmail' => $event->customerEmail,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    private function sendConfirmationEmail(OrderPlaced $event): void
    {
        $locale = $this->defaultLocale; // TODO: Get from customer preferences or tenant settings

        $total = $event->total->getAmount() / 100; // Convert cents to currency units
        $currency = $event->total->getCurrency()->getCurrencyCode();

        $email = (new TemplatedEmail())
            ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
            ->to($event->customerEmail)
            ->subject($this->translator->trans('emails.order.placed.title', [], 'emails', $locale))
            ->htmlTemplate('emails/order/order_placed.html.twig')
            ->context([
                'locale' => $locale,
                'customerName' => 'Customer', // TODO: Get from customer entity
                'orderId' => $event->orderId->toString(),
                'orderDate' => new \DateTimeImmutable(),
                'total' => sprintf('%.2f', $total),
                'currency' => $currency,
                'status' => 'pending',
                'orderViewUrl' => '#', // TODO: Generate proper order view URL
            ]);

        $this->mailer->send($email);
    }
}
