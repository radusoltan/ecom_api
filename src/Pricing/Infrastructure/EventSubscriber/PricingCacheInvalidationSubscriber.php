<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\EventSubscriber;

use App\Pricing\Domain\Event\FlashSaleActivated;
use App\Pricing\Domain\Event\FlashSaleEnded;
use App\Pricing\Domain\Event\PriceListActivated;
use App\Pricing\Domain\Event\PriceListCreated;
use App\Pricing\Domain\Event\PriceListDeactivated;
use App\Pricing\Domain\Event\PromotionActivated;
use App\Pricing\Domain\Event\PromotionCreated;
use App\Pricing\Domain\Event\PromotionDeactivated;
use App\Pricing\Domain\Event\PromotionUpdated;
use App\Shared\Infrastructure\Cache\CacheService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class PricingCacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CacheService $cacheService,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PriceListCreated::class => 'onPriceListCreated',
            PriceListActivated::class => 'onPriceListActivated',
            PriceListDeactivated::class => 'onPriceListDeactivated',
            PromotionCreated::class => 'onPromotionCreated',
            PromotionUpdated::class => 'onPromotionUpdated',
            PromotionActivated::class => 'onPromotionActivated',
            PromotionDeactivated::class => 'onPromotionDeactivated',
            FlashSaleActivated::class => 'onFlashSaleActivated',
            FlashSaleEnded::class => 'onFlashSaleEnded',
        ];
    }

    public function onPriceListCreated(PriceListCreated $event): void
    {
        $this->invalidateTags(
            $this->tenantResourceTags($event->tenantId(), 'price_lists'),
            PriceListCreated::class
        );
    }

    public function onPriceListActivated(PriceListActivated $event): void
    {
        $this->invalidateTags(
            $this->tenantResourceTags($event->tenantId(), 'price_lists'),
            PriceListActivated::class
        );
    }

    public function onPriceListDeactivated(PriceListDeactivated $event): void
    {
        $this->invalidateTags(
            $this->tenantResourceTags($event->tenantId(), 'price_lists'),
            PriceListDeactivated::class
        );
    }

    public function onPromotionCreated(PromotionCreated $event): void
    {
        $this->invalidateTags(
            $this->tenantResourceTags($event->tenantId()->toString(), 'promotions'),
            PromotionCreated::class
        );
    }

    public function onPromotionUpdated(PromotionUpdated $event): void
    {
        $this->invalidateTags(
            $this->tenantResourceTags($event->tenantId()->toString(), 'promotions'),
            PromotionUpdated::class
        );
    }

    public function onPromotionActivated(PromotionActivated $event): void
    {
        $tenantId = $event->tenantId()->toString();

        $this->invalidateTags([
            ...$this->tenantResourceTags($tenantId, 'promotions'),
            $this->cacheService->tenantResourceTag($tenantId, 'products'),
        ], PromotionActivated::class);
    }

    public function onPromotionDeactivated(PromotionDeactivated $event): void
    {
        $tenantId = $event->tenantId()->toString();

        $this->invalidateTags([
            ...$this->tenantResourceTags($tenantId, 'promotions'),
            $this->cacheService->tenantResourceTag($tenantId, 'products'),
        ], PromotionDeactivated::class);
    }

    public function onFlashSaleActivated(FlashSaleActivated $event): void
    {
        $tenantId = $event->tenantId()->toString();

        $this->invalidateTags([
            ...$this->tenantResourceTags($tenantId, 'promotions'),
            $this->cacheService->tenantResourceTag($tenantId, 'products'),
        ], FlashSaleActivated::class);
    }

    public function onFlashSaleEnded(FlashSaleEnded $event): void
    {
        $tenantId = $event->tenantId()->toString();

        $this->invalidateTags([
            ...$this->tenantResourceTags($tenantId, 'promotions'),
            $this->cacheService->tenantResourceTag($tenantId, 'products'),
        ], FlashSaleEnded::class);
    }

    /**
     * @return array<string>
     */
    private function tenantResourceTags(string $tenantId, string $resource, ?string $itemTag = null): array
    {
        $tags = [$this->cacheService->tenantResourceTag($tenantId, $resource)];

        if (null !== $itemTag) {
            $tags[] = $itemTag;
        }

        return $tags;
    }

    /**
     * @param array<string> $tags
     */
    private function invalidateTags(array $tags, string $eventName): void
    {
        $uniqueTags = array_values(array_unique($tags));
        $this->cacheService->invalidateTags($uniqueTags);

        $this->logger->debug('Cache invalidation triggered', [
            'event' => $eventName,
            'tags' => $uniqueTags,
        ]);
    }
}
