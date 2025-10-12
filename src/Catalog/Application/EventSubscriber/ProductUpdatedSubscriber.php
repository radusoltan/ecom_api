<?php

declare(strict_types=1);

namespace App\Catalog\Application\EventSubscriber;

use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Internationalization\Domain\Model\Locale;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class ProductUpdatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductIndexer $productIndexer,
        private LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductUpdated::class => 'onProductUpdated',
        ];
    }

    public function onProductUpdated(ProductUpdated $event): void
    {
        try {
            $product = $this->productRepository->findById($event->productId);

            if ($product === null) {
                $this->logger->warning('Product not found for reindexing', [
                    'product_id' => $event->productId->toString(),
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
                'locales' => array_map(fn($l) => $l->toString(), $enabledLocales),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to reindex product', [
                'product_id' => $event->productId->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<Locale>
     */
    private function getEnabledLocales($tenantId): array
    {
        // TODO: Get from tenant configuration
        // For now, return default locales
        return [
            Locale::fromString('en_US'),
            Locale::fromString('ro_RO'),
        ];
    }
}
