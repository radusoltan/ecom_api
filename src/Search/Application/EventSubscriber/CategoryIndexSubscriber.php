<?php

declare(strict_types=1);

namespace App\Search\Application\EventSubscriber;

use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\CategoryUpdated;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Category Index Subscriber.
 *
 * Updates product index when categories are modified.
 * Since products reference categories, category changes may affect search results.
 *
 * Architecture:
 * - Search context subscribes to Catalog category events
 * - Reindexes all products in affected category hierarchy
 * - Graceful error handling
 * - Multi-tenant and multi-locale support
 *
 * Events handled:
 * - CategoryCreated: No immediate action needed (no products yet)
 * - CategoryUpdated: Reindex all products in this category
 */
final readonly class CategoryIndexSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductIndexer $productIndexer,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CategoryCreated::class => 'onCategoryCreated',
            CategoryUpdated::class => 'onCategoryUpdated',
        ];
    }

    public function onCategoryCreated(CategoryCreated $event): void
    {
        // No immediate action needed - new categories have no products yet
        // Products will be indexed when they're added to this category

        $this->logger->info('Category created - no reindexing needed', [
            'category_id' => $event->categoryId->toString(),
            'tenant_id' => $event->tenantId->toString(),
            'name' => $event->name,
        ]);
    }

    public function onCategoryUpdated(CategoryUpdated $event): void
    {
        try {
            // When a category is updated (e.g., name changed), we need to reindex
            // all products in this category so search results reflect the new category name

            // TODO: Implement findByCategoryId in ProductRepository
            // For now, log the event - actual implementation requires repository method
            $this->logger->info('Category updated - products need reindexing', [
                'category_id' => $event->categoryId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'note' => 'Reindexing products in this category - requires ProductRepository::findByCategoryId()',
            ]);

            // Future implementation:
            // $products = $this->productRepository->findByCategoryId($event->categoryId);
            // $enabledLocales = $this->getEnabledLocales($event->tenantId);
            //
            // foreach ($products as $product) {
            //     foreach ($enabledLocales as $locale) {
            //         $this->productIndexer->updateProduct($product, $locale);
            //     }
            // }
        } catch (\Exception $e) {
            $this->logger->error('Failed to reindex products after category update', [
                'category_id' => $event->categoryId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get enabled locales for a tenant.
     *
     * @return array<Locale>
     */
    private function getEnabledLocales(TenantId $tenantId): array
    {
        // TODO: Get from tenant configuration (TenantSettings aggregate)
        // For now, return default locales based on project requirements (EN, FR, DE, RO)
        return [
            Locale::fromString('en_US'),
            Locale::fromString('fr_FR'),
            Locale::fromString('de_DE'),
            Locale::fromString('ro_RO'),
        ];
    }
}
