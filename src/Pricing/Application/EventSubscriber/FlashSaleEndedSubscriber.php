<?php

declare(strict_types=1);

namespace App\Pricing\Application\EventSubscriber;

use App\Pricing\Domain\Event\FlashSaleEnded;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class FlashSaleEndedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FlashSaleEnded::class => 'onFlashSaleEnded',
        ];
    }

    public function onFlashSaleEnded(FlashSaleEnded $event): void
    {
        $this->logger->info('Flash sale ended', [
            'flash_sale_id' => $event->flashSaleId()->toString(),
            'tenant_id' => $event->tenantId()->toString(),
        ]);

        // TODO: Send "last chance" notifications if configured
        // TODO: Update cache/search indices
        // TODO: Trigger analytics events
    }
}
