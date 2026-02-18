<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Elasticsearch;

use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use Elastic\Elasticsearch\Client;

final readonly class CategoryIndexer
{
    public function __construct(
        private Client $client,
        private IndexManager $indexManager,
    ) {
    }

    public function indexCategory(Category $category, Locale $locale): void
    {
        $indexName = $this->indexManager->getCategoryIndexName($category->tenantId(), $locale);

        // Ensure index exists
        $this->indexManager->createCategoryIndex($category->tenantId(), $locale);

        $document = $this->categoryToDocument($category, $locale);

        $params = [
            'index' => $indexName,
            'id' => $category->id()->toString(),
            'body' => $document,
        ];

        $this->client->index($params);
    }

    /**
     * @param Category[] $categories
     */
    public function indexCategories(array $categories, Locale $locale): void
    {
        if (empty($categories)) {
            return;
        }

        // Get tenant ID from first category (all should have same tenant)
        $tenantId = $categories[0]->tenantId();
        $indexName = $this->indexManager->getCategoryIndexName($tenantId, $locale);

        // Ensure index exists
        $this->indexManager->createCategoryIndex($tenantId, $locale);

        $params = ['body' => []];

        foreach ($categories as $category) {
            $params['body'][] = [
                'index' => [
                    '_index' => $indexName,
                    '_id' => $category->id()->toString(),
                ],
            ];

            $params['body'][] = $this->categoryToDocument($category, $locale);
        }

        if (empty($params['body'])) {
            return;
        }

        $this->client->bulk($params);
    }

    public function updateCategory(Category $category, Locale $locale): void
    {
        // Same as index - Elasticsearch will update if exists
        $this->indexCategory($category, $locale);
    }

    public function deleteCategory(CategoryId $categoryId, TenantId $tenantId, Locale $locale): void
    {
        $indexName = $this->indexManager->getCategoryIndexName($tenantId, $locale);

        if (!$this->indexManager->indexExists($indexName)) {
            return;
        }

        $params = [
            'index' => $indexName,
            'id' => $categoryId->toString(),
        ];

        try {
            $this->client->delete($params);
        } catch (\Exception) {
            // Document might not exist - ignore
        }
    }

    public function reindexTenant(TenantId $tenantId, array $categories, array $locales): void
    {
        foreach ($locales as $locale) {
            $this->indexCategories($categories, $locale);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryToDocument(Category $category, Locale $locale): array
    {
        // TODO: When Category supports translations, use: $category->getName($locale)
        // For now, use the single name value
        $name = $category->name()->value();
        $description = $category->description() ?? '';

        return [
            'id' => $category->id()->toString(),
            'tenant_id' => $category->tenantId()->toString(),
            'name' => $name,
            'description' => $description,
            'slug' => $category->slug()->value(),
            'parent_id' => $category->parentId()?->toString(),
            'position' => $category->position(),
            'is_active' => $category->isActive(),
            'show_on_front' => $category->showOnFront(),
            'locale' => $locale->toString(),
            'created_at' => $category->createdAt()->format('c'),
            'updated_at' => $category->updatedAt()->format('c'),
        ];
    }
}
