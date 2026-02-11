<?php

declare(strict_types=1);

namespace App\Cart\Application\EventSubscriber;

use App\Cart\Domain\Event\CartAbandoned;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles CartAbandoned domain events by sending abandonment recovery emails.
 *
 * Business Rules:
 * - Send abandonment email with cart summary
 * - Include yellow/gold header for attention
 * - Show items in cart and total amount
 * - Provide clear CTA to complete purchase
 * - Email failures should not block cart processing
 */
final readonly class CartAbandonedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private string $senderEmail = 'carts@ecommerce.local',
        private string $senderName = 'E-Commerce Platform',
        private string $defaultLocale = 'en',
        private string $storefrontUrl = 'http://localhost:3000'
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartAbandoned::class => 'onCartAbandoned',
        ];
    }

    public function onCartAbandoned(CartAbandoned $event): void
    {
        try {
            $this->sendAbandonmentEmail($event);

            $this->logger->info('Cart abandonment email sent', [
                'cartId' => $event->cartId->toString(),
                'customerEmail' => $event->customerEmail,
                'tenantId' => $event->tenantId->toString(),
                'itemCount' => $event->itemCount,
                'total' => $event->cartTotal->getAmount(),
            ]);
        } catch (\Throwable $exception) {
            // Log error but don't throw - email failure shouldn't block cart processing
            $this->logger->error('Failed to send cart abandonment email', [
                'cartId' => $event->cartId->toString(),
                'customerEmail' => $event->customerEmail,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    private function sendAbandonmentEmail(CartAbandoned $event): void
    {
        $locale = $this->defaultLocale; // TODO: Get from customer preferences or tenant settings

        $total = $event->cartTotal->getAmount();
        $currency = $event->cartTotal->getCurrency()->getCurrencyCode();

        // Calculate cart expiry (7 days from last update as per Cart domain model)
        $expiresAt = $event->abandonedAt->modify('+7 days');

        // Build cart URL (includes tenant context)
        $cartUrl = sprintf('%s/cart', $this->storefrontUrl);

        $email = (new TemplatedEmail())
            ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
            ->to($event->customerEmail)
            ->subject($this->translator->trans('emails.cart.abandoned.title', [], 'emails', $locale))
            ->htmlTemplate('emails/cart/cart_abandoned.html.twig')
            ->context([
                'locale' => $locale,
                'customerName' => 'Valued Customer', // TODO: Get from customer entity
                'cartId' => $event->cartId->toString(),
                'itemCount' => $event->itemCount,
                'total' => sprintf('%.2f', $total / 100), // Convert minor units (cents) to major units
                'currency' => $currency,
                'expiresAt' => $expiresAt,
                'cartUrl' => $cartUrl,
                // 'items' => [], // TODO: Fetch cart items for display
                // 'discount' => '10%', // TODO: Optional discount offer
            ]);

        $this->mailer->send($email);
    }
}
