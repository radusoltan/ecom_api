<?php

declare(strict_types=1);

namespace App\Catalog\Application\EventSubscriber;

use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\CategoryIndexer;
use App\Internationalization\Domain\Model\Locale;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CategoryCreatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private CategoryIndexer $categoryIndexer,
        private LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CategoryCreated::class => 'onCategoryCreated',
        ];
    }

    public function onCategoryCreated(CategoryCreated $event): void
    {
        try {
            $category = $this->categoryRepository->findById($event->categoryId);

            if ($category === null) {
                $this->logger->warning('Category not found for indexing', [
                    'category_id' => $event->categoryId->toString(),
                ]);
                return;
            }

            // Index in all enabled locales
            $enabledLocales = $this->getEnabledLocales($category->tenantId());

            foreach ($enabledLocales as $locale) {
                $this->categoryIndexer->indexCategory($category, $locale);
            }

            $this->logger->info('Category indexed in Elasticsearch', [
                'category_id' => $event->categoryId->toString(),
                'locales' => array_map(fn($l) => $l->toString(), $enabledLocales),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to index category', [
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
