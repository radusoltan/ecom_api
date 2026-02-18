<?php

declare(strict_types=1);

namespace App\Order\Application\EventSubscriber;

use App\Order\Domain\Event\FulfillmentStatusChanged;
use App\Order\Domain\Repository\FulfillmentRepositoryInterface;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles FulfillmentStatusChanged events when order is shipped.
 *
 * Business Rules:
 * - Send shipping notification when fulfillment status becomes "shipping"
 * - Include tracking number and carrier information
 * - Include estimated delivery date if available
 * - Failures should be logged but not block fulfillment status update
 */
final readonly class FulfillmentShippedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private FulfillmentRepositoryInterface $fulfillmentRepository,
        private OrderRepositoryInterface $orderRepository,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private string $senderEmail = 'orders@ecommerce.local',
        private string $senderName = 'E-Commerce Platform',
        private string $defaultLocale = 'en',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FulfillmentStatusChanged::class => 'onFulfillmentStatusChanged',
        ];
    }

    public function onFulfillmentStatusChanged(FulfillmentStatusChanged $event): void
    {
        try {
            // Only send notification when status changes to "shipping"
            if ($this->shouldNotifyCustomer($event->toStatus)) {
                $this->sendShippingNotification($event);

                $this->logger->info('Order shipping notification sent', [
                    'fulfillmentId' => $event->fulfillmentId->toString(),
                    'tenantId' => $event->tenantId->toString(),
                ]);
            }
        } catch (\Throwable $exception) {
            // Log error but don't throw - email failure shouldn't block status update
            $this->logger->error('Failed to send shipping notification', [
                'fulfillmentId' => $event->fulfillmentId->toString(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    private function shouldNotifyCustomer(FulfillmentStatus $toStatus): bool
    {
        // Only notify when status becomes "shipping"
        return $toStatus->equals(FulfillmentStatus::shipping());
    }

    private function sendShippingNotification(FulfillmentStatusChanged $event): void
    {
        // Fetch fulfillment to get tracking details
        $fulfillment = $this->fulfillmentRepository->findById($event->fulfillmentId);

        if (null === $fulfillment) {
            $this->logger->warning('Cannot send shipping notification - fulfillment not found', [
                'fulfillmentId' => $event->fulfillmentId->toString(),
            ]);

            return;
        }

        // Fetch order to get customer email
        $order = $this->orderRepository->findById($fulfillment->orderId());

        if (null === $order) {
            $this->logger->warning('Cannot send shipping notification - order not found', [
                'orderId' => $fulfillment->orderId()->toString(),
            ]);

            return;
        }

        $locale = $this->defaultLocale; // TODO: Get from customer preferences or tenant settings

        $total = $order->total()->getAmount() / 100;
        $currency = $order->total()->getCurrency()->getCurrencyCode();

        // Build tracking URL (placeholder - implement carrier-specific tracking URLs)
        $trackingUrl = $this->buildTrackingUrl(
            $fulfillment->carrier(),
            $fulfillment->trackingNumber()
        );

        $email = (new TemplatedEmail())
            ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
            ->to($order->customerEmail())
            ->subject($this->translator->trans('emails.order.shipped.title', [], 'emails', $locale))
            ->htmlTemplate('emails/order/order_shipped.html.twig')
            ->context([
                'locale' => $locale,
                'customerName' => 'Customer', // TODO: Get from customer entity
                'orderId' => $order->id()->toString(),
                'shippedDate' => $fulfillment->shippedAt() ?? new \DateTimeImmutable(),
                'total' => sprintf('%.2f', $total),
                'currency' => $currency,
                'carrier' => $fulfillment->carrier(),
                'trackingNumber' => $fulfillment->trackingNumber(),
                'trackingUrl' => $trackingUrl,
                'orderViewUrl' => '#', // TODO: Generate proper order view URL
            ]);

        $this->mailer->send($email);
    }

    private function buildTrackingUrl(?string $carrier, ?string $trackingNumber): string
    {
        if (null === $carrier || null === $trackingNumber) {
            return '#';
        }

        // Build carrier-specific tracking URLs
        return match (strtoupper($carrier)) {
            'UPS' => sprintf('https://www.ups.com/track?tracknum=%s', urlencode($trackingNumber)),
            'FEDEX' => sprintf('https://www.fedex.com/fedextrack/?tracknumbers=%s', urlencode($trackingNumber)),
            'DHL' => sprintf('https://www.dhl.com/en/express/tracking.html?AWB=%s', urlencode($trackingNumber)),
            'USPS' => sprintf('https://tools.usps.com/go/TrackConfirmAction?tLabels=%s', urlencode($trackingNumber)),
            default => '#', // Fallback for unknown carriers
        };
    }
}
