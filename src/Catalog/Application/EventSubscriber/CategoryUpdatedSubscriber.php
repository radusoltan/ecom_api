<?php

declare(strict_types=1);

namespace App\Catalog\Application\EventSubscriber;

use App\Catalog\Domain\Event\CategoryUpdated;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\CategoryIndexer;
use App\Internationalization\Domain\Model\Locale;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CategoryUpdatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private CategoryIndexer $categoryIndexer,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CategoryUpdated::class => 'onCategoryUpdated',
        ];
    }

    public function onCategoryUpdated(CategoryUpdated $event): void
    {
        try {
            $category = $this->categoryRepository->findById($event->categoryId);

            if (null === $category) {
                $this->logger->warning('Category not found for reindexing', [
                    'category_id' => $event->categoryId->toString(),
                ]);

                return;
            }

            // Reindex in all enabled locales
            $enabledLocales = $this->getEnabledLocales($category->tenantId());

            foreach ($enabledLocales as $locale) {
                $this->categoryIndexer->updateCategory($category, $locale);
            }

            $this->logger->info('Category reindexed in Elasticsearch', [
                'category_id' => $event->categoryId->toString(),
                'locales' => array_map(fn ($l) => $l->toString(), $enabledLocales),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to reindex category', [
                'category_id' => $event->categoryId->toString(),
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
