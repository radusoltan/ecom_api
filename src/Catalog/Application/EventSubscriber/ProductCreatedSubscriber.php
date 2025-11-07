<?php

declare(strict_types=1);

namespace App\Catalog\Application\EventSubscriber;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Internationalization\Domain\Model\Locale;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class ProductCreatedSubscriber implements EventSubscriberInterface
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
        ];
    }

    public function onProductCreated(ProductCreated $event): void
    {
        try {
            $product = $this->productRepository->findById($event->productId);

            if (null === $product) {
                $this->logger->warning('Product not found for indexing', [
                    'product_id' => $event->productId->toString(),
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
                'locales' => array_map(fn ($l) => $l->toString(), $enabledLocales),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to index product', [
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
