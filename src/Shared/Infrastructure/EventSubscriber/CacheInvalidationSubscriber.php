<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\EventSubscriber;

use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\CategoryTranslationsUpdated;
use App\Catalog\Domain\Event\CategoryUpdated;
use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductDeactivated;
use App\Catalog\Domain\Event\ProductPublished;
use App\Catalog\Domain\Event\ProductTranslationsUpdated;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Internationalization\Domain\Event\TranslationCreated;
use App\Internationalization\Domain\Event\TranslationDeleted;
use App\Internationalization\Domain\Event\TranslationUpdated;
use App\Shared\Infrastructure\Cache\CacheService;
use App\Tenant\Domain\Event\TenantDeactivated;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CacheInvalidationSubscriber
{
    public function __construct(
        private CacheService $cacheService,
        private LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onProductCreated(ProductCreated $event): void
    {
        $this->invalidateTags([
            ...$this->tenantResourceTags($event->tenantId->toString(), 'products'),
            $this->cacheService->tenantResourceTag($event->tenantId->toString(), 'categories'),
        ], ProductCreated::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onProductUpdated(ProductUpdated $event): void
    {
        $this->invalidateTags(
            $this->tenantResourceTags(
                $event->tenantId->toString(),
                'products',
                $this->cacheService->tag('product', $event->productId->toString())
            ),
            ProductUpdated::class
        );
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onProductPublished(ProductPublished $event): void
    {
        $this->invalidateTags(
            $this->tenantResourceTags($event->tenantId->toString(), 'products'),
            ProductPublished::class
        );
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onProductDeactivated(ProductDeactivated $event): void
    {
        $this->invalidateTags(
            $this->tenantResourceTags(
                $event->tenantId->toString(),
                'products',
                $this->cacheService->tag('product', $event->productId->toString())
            ),
            ProductDeactivated::class
        );
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onProductTranslationsUpdated(ProductTranslationsUpdated $event): void
    {
        $tenantId = $event->tenantId()->toString();

        $this->invalidateTags([
            ...$this->tenantResourceTags(
                $tenantId,
                'products',
                $this->cacheService->tag('i18n', 'product', $event->productId()->toString())
            ),
            $this->cacheService->tenantResourceTag($tenantId, 'i18n'),
            $this->cacheService->tenantResourceTag($tenantId, 'i18n:'.$event->locale()->toString()),
        ], ProductTranslationsUpdated::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onCategoryCreated(CategoryCreated $event): void
    {
        $this->invalidateTags(
            $this->tenantResourceTags($event->tenantId->toString(), 'categories'),
            CategoryCreated::class
        );
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onCategoryUpdated(CategoryUpdated $event): void
    {
        $this->invalidateTags(
            $this->tenantResourceTags($event->tenantId->toString(), 'categories'),
            CategoryUpdated::class
        );
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onCategoryTranslationsUpdated(CategoryTranslationsUpdated $event): void
    {
        $tenantId = $event->tenantId()->toString();

        $this->invalidateTags([
            ...$this->tenantResourceTags($tenantId, 'categories'),
            $this->cacheService->tenantResourceTag($tenantId, 'i18n'),
            $this->cacheService->tenantResourceTag($tenantId, 'i18n:'.$event->locale()->toString()),
        ], CategoryTranslationsUpdated::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onTranslationCreated(TranslationCreated $event): void
    {
        $this->invalidateTranslationTags($event->tenantId->toString(), $event->locale, TranslationCreated::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onTranslationUpdated(TranslationUpdated $event): void
    {
        $this->invalidateTranslationTags($event->tenantId->toString(), $event->locale, TranslationUpdated::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onTranslationDeleted(TranslationDeleted $event): void
    {
        $this->invalidateTranslationTags($event->tenantId->toString(), $event->locale, TranslationDeleted::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onTenantDeactivated(TenantDeactivated $event): void
    {
        $this->invalidateTags([$this->cacheService->tenantTag($event->tenantId->toString())], TenantDeactivated::class);
    }

    private function invalidateTranslationTags(string $tenantId, string $locale, string $eventName): void
    {
        $this->invalidateTags([
            ...$this->tenantResourceTags($tenantId, 'i18n'),
            $this->cacheService->tenantResourceTag($tenantId, 'i18n:'.$locale),
        ], $eventName);
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
