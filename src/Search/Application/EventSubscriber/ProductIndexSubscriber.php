<?php

declare(strict_types=1);

namespace App\Search\Application\EventSubscriber;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductDeactivated;
use App\Catalog\Domain\Event\ProductDiscontinued;
use App\Catalog\Domain\Event\ProductReactivated;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Product Index Subscriber.
 *
 * Keeps Elasticsearch product index synchronized with Catalog domain events.
 *
 * Architecture:
 * - Search context subscribes to Catalog domain events (loose coupling)
 * - Graceful error handling (indexing failures don't block domain operations)
 * - Multi-tenant support (separate indices per tenant)
 * - Multi-locale support (index products in all enabled locales)
 *
 * Events handled:
 * - ProductCreated: Add product to index
 * - ProductUpdated: Update product in index
 * - ProductDeactivated: Remove from index (or mark inactive)
 * - ProductDiscontinued: Remove from index
 * - ProductReactivated: Re-add to index
 */
final readonly class ProductIndexSubscriber implements EventSubscriberInterface
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
            ProductCreated::class => 'onProductCreated',
            ProductUpdated::class => 'onProductUpdated',
            ProductDeactivated::class => 'onProductDeactivated',
            ProductDiscontinued::class => 'onProductDiscontinued',
            ProductReactivated::class => 'onProductReactivated',
        ];
    }

    public function onProductCreated(ProductCreated $event): void
    {
        try {
            $product = $this->productRepository->findById($event->productId);

            if (null === $product) {
                $this->logger->warning('Product not found for indexing', [
                    'product_id' => $event->productId->toString(),
                    'tenant_id' => $event->tenantId->toString(),
                    'event' => 'ProductCreated',
                ]);

                return;
            }

            // Index in all enabled locales
            $enabledLocales = $this->getEnabledLocales($product->tenantId());

            foreach ($enabledLocales as $locale) {
                $this->productIndexer->indexProduct($product, $locale);
            }

            $this->logger->info('Product indexed in Elasticsearch', [
                'product_id' => $event->productId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'sku' => $event->sku->value(),
                'locales' => array_map(fn ($l) => $l->toString(), $enabledLocales),
            ]);
        } catch (\Exception $e) {
            // Graceful error handling - indexing failures shouldn't block domain operations
            $this->logger->error('Failed to index product', [
                'product_id' => $event->productId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // TODO: Consider sending notification to ops team
            // TODO: Consider adding to retry queue
        }
    }

    public function onProductUpdated(ProductUpdated $event): void
    {
        try {
            $product = $this->productRepository->findById($event->productId);

            if (null === $product) {
                $this->logger->warning('Product not found for reindexing', [
                    'product_id' => $event->productId->toString(),
                    'tenant_id' => $event->tenantId->toString(),
                    'event' => 'ProductUpdated',
                ]);

                return;
            }

            // Reindex in all enabled locales
            $enabledLocales = $this->getEnabledLocales($product->tenantId());

            foreach ($enabledLocales as $locale) {
                $this->productIndexer->updateProduct($product, $locale);
            }

            $this->logger->info('Product reindexed in Elasticsearch', [
                'product_id' => $event->productId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'locales' => array_map(fn ($l) => $l->toString(), $enabledLocales),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to reindex product', [
                'product_id' => $event->productId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function onProductDeactivated(ProductDeactivated $event): void
    {
        try {
            // Option 1: Remove from index entirely
            // Option 2: Keep in index but mark as inactive (chosen - allows better analytics)

            $product = $this->productRepository->findById($event->productId);

            if (null === $product) {
                $this->logger->warning('Product not found for deactivation update', [
                    'product_id' => $event->productId->toString(),
                    'tenant_id' => $event->tenantId->toString(),
                    'event' => 'ProductDeactivated',
                ]);

                return;
            }

            // Update product in index (status will be 'inactive')
            $enabledLocales = $this->getEnabledLocales($product->tenantId());

            foreach ($enabledLocales as $locale) {
                $this->productIndexer->updateProduct($product, $locale);
            }

            $this->logger->info('Product marked as inactive in Elasticsearch', [
                'product_id' => $event->productId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'locales' => array_map(fn ($l) => $l->toString(), $enabledLocales),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update deactivated product in index', [
                'product_id' => $event->productId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function onProductDiscontinued(ProductDiscontinued $event): void
    {
        try {
            // Discontinued products should be removed from search index
            $enabledLocales = $this->getEnabledLocales($event->tenantId);

            foreach ($enabledLocales as $locale) {
                $this->productIndexer->deleteProduct(
                    $event->productId,
                    $event->tenantId,
                    $locale
                );
            }

            $this->logger->info('Discontinued product removed from Elasticsearch', [
                'product_id' => $event->productId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'locales' => array_map(fn ($l) => $l->toString(), $enabledLocales),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove discontinued product from index', [
                'product_id' => $event->productId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function onProductReactivated(ProductReactivated $event): void
    {
        try {
            $product = $this->productRepository->findById($event->productId);

            if (null === $product) {
                $this->logger->warning('Product not found for reactivation', [
                    'product_id' => $event->productId->toString(),
                    'tenant_id' => $event->tenantId->toString(),
                    'event' => 'ProductReactivated',
                ]);

                return;
            }

            // Re-index product (status will be 'active')
            $enabledLocales = $this->getEnabledLocales($product->tenantId());

            foreach ($enabledLocales as $locale) {
                $this->productIndexer->indexProduct($product, $locale);
            }

            $this->logger->info('Product reactivated and indexed in Elasticsearch', [
                'product_id' => $event->productId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'locales' => array_map(fn ($l) => $l->toString(), $enabledLocales),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to reindex reactivated product', [
                'product_id' => $event->productId->toString(),
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
