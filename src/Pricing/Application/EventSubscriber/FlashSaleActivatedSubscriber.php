<?php

declare(strict_types=1);

namespace App\Pricing\Application\EventSubscriber;

use App\Pricing\Domain\Event\FlashSaleActivated;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class FlashSaleActivatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FlashSaleActivated::class => 'onFlashSaleActivated',
        ];
    }

    public function onFlashSaleActivated(FlashSaleActivated $event): void
    {
        $this->logger->info('Flash sale activated - ready for customer notifications', [
            'flash_sale_id' => $event->flashSaleId()->toString(),
            'tenant_id' => $event->tenantId()->toString(),
            'name' => $event->name(),
            'product_count' => count($event->productIds()),
        ]);

        // TODO: Send email notifications to subscribed customers
        // TODO: Send webhook notifications to external systems
        // TODO: Trigger push notifications for mobile apps
    }
}
