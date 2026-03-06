<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Elasticsearch;

use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Infrastructure\Elasticsearch\CategoryIndexer;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
use PHPUnit\Framework\TestCase;

final class CategoryIndexerTest extends TestCase
{
    private Client $client;
    private IndexManager $indexManager;
    private CategoryIndexer $indexer;
    private Locale $locale;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->indexManager = $this->createMock(IndexManager::class);

        $this->indexer = new CategoryIndexer(
            $this->client,
            $this->indexManager,
        );

        $this->locale = Locale::fromString('en_US');
    }

    private function createCategory(
        ?CategoryId $parentId = null,
        bool $active = true,
        bool $showOnFront = true,
    ): Category {
        return Category::reconstituteFromPersistence(
            id: CategoryId::fromString('00000000-0000-4000-8000-000000000010'),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            name: CategoryName::fromString('Electronics'),
            description: 'Electronic devices',
            slug: Slug::fromString('electronics'),
            parentId: $parentId,
            position: 1,
            active: $active,
            showOnFront: $showOnFront,
            coverImage: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            updatedAt: new \DateTimeImmutable('2026-01-02'),
        );
    }

    public function testIndexCategoryCreatesIndexAndIndexesDocument(): void
    {
        $category = $this->createCategory();

        $this->indexManager->method('getCategoryIndexName')->willReturn('categories_en');
        $this->indexManager->expects(self::once())->method('createCategoryIndex');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')
            ->with(self::callback(function (array $params) {
                return 'categories_en' === $params['index']
                    && '00000000-0000-4000-8000-000000000010' === $params['id']
                    && 'Electronics' === $params['body']['name']
                    && 'Electronic devices' === $params['body']['description']
                    && 'electronics' === $params['body']['slug']
                    && true === $params['body']['is_active']
                    && true === $params['body']['show_on_front']
                    && null === $params['body']['parent_id']
                    && 1 === $params['body']['position'];
            }))
            ->willReturn($esResponse);

        $this->indexer->indexCategory($category, $this->locale);
    }

    public function testIndexCategoryWithParent(): void
    {
        $parentId = CategoryId::fromString('00000000-0000-4000-8000-000000000020');
        $category = $this->createCategory(parentId: $parentId);

        $this->indexManager->method('getCategoryIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')
            ->with(self::callback(function (array $params) {
                return '00000000-0000-4000-8000-000000000020' === $params['body']['parent_id'];
            }))
            ->willReturn($esResponse);

        $this->indexer->indexCategory($category, $this->locale);
    }

    public function testIndexCategoriesEmptyArrayDoesNothing(): void
    {
        $this->client->expects(self::never())->method('bulk');

        $this->indexer->indexCategories([], $this->locale);
    }

    public function testIndexCategoriesBulkIndexes(): void
    {
        $category = $this->createCategory();

        $this->indexManager->method('getCategoryIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('bulk')
            ->with(self::callback(function (array $params) {
                return 2 === count($params['body']); // action + document
            }))
            ->willReturn($esResponse);

        $this->indexer->indexCategories([$category], $this->locale);
    }

    public function testUpdateCategoryDelegatesToIndex(): void
    {
        $category = $this->createCategory();

        $this->indexManager->method('getCategoryIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')->willReturn($esResponse);

        $this->indexer->updateCategory($category, $this->locale);
    }

    public function testDeleteCategoryWhenIndexExists(): void
    {
        $categoryId = CategoryId::fromString('00000000-0000-4000-8000-000000000010');
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->indexManager->method('getCategoryIndexName')->willReturn('idx');
        $this->indexManager->method('indexExists')->willReturn(true);

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('delete')
            ->with(self::callback(function (array $params) {
                return 'idx' === $params['index']
                    && '00000000-0000-4000-8000-000000000010' === $params['id'];
            }))
            ->willReturn($esResponse);

        $this->indexer->deleteCategory($categoryId, $tenantId, $this->locale);
    }

    public function testDeleteCategoryWhenIndexDoesNotExist(): void
    {
        $categoryId = CategoryId::fromString('00000000-0000-4000-8000-000000000010');
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->indexManager->method('getCategoryIndexName')->willReturn('idx');
        $this->indexManager->method('indexExists')->willReturn(false);

        $this->client->expects(self::never())->method('delete');

        $this->indexer->deleteCategory($categoryId, $tenantId, $this->locale);
    }

    public function testDeleteCategoryIgnoresNotFoundException(): void
    {
        $categoryId = CategoryId::fromString('00000000-0000-4000-8000-000000000010');
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->indexManager->method('getCategoryIndexName')->willReturn('idx');
        $this->indexManager->method('indexExists')->willReturn(true);

        $this->client->method('delete')->willThrowException(new \RuntimeException('Not found'));

        // Should not throw
        $this->indexer->deleteCategory($categoryId, $tenantId, $this->locale);

        self::assertTrue(true);
    }

    public function testReindexTenantIndexesAllLocales(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $category = $this->createCategory();

        $this->indexManager->method('getCategoryIndexName')->willReturn('idx');

        $locale1 = Locale::fromString('en_US');
        $locale2 = Locale::fromString('fr_FR');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::exactly(2))->method('bulk')->willReturn($esResponse);

        $this->indexer->reindexTenant($tenantId, [$category], [$locale1, $locale2]);
    }

    public function testInactiveCategoryIndexedCorrectly(): void
    {
        $category = $this->createCategory(active: false, showOnFront: false);

        $this->indexManager->method('getCategoryIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')
            ->with(self::callback(function (array $params) {
                return false === $params['body']['is_active']
                    && false === $params['body']['show_on_front'];
            }))
            ->willReturn($esResponse);

        $this->indexer->indexCategory($category, $this->locale);
    }

    public function testCategoryDocumentContainsLocale(): void
    {
        $category = $this->createCategory();

        $this->indexManager->method('getCategoryIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')
            ->with(self::callback(function (array $params) {
                return 'en_US' === $params['body']['locale'];
            }))
            ->willReturn($esResponse);

        $this->indexer->indexCategory($category, $this->locale);
    }

    public function testCategoryDocumentIncludesTimestamps(): void
    {
        $category = $this->createCategory();

        $this->indexManager->method('getCategoryIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')
            ->with(self::callback(function (array $params) {
                return isset($params['body']['created_at'])
                    && isset($params['body']['updated_at'])
                    && str_contains($params['body']['created_at'], '2026-01-01')
                    && str_contains($params['body']['updated_at'], '2026-01-02');
            }))
            ->willReturn($esResponse);

        $this->indexer->indexCategory($category, $this->locale);
    }
}
