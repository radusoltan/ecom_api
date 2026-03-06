<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Elasticsearch;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductImage;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\ValueObject\ProductStatus;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
use PHPUnit\Framework\TestCase;

final class ProductIndexerTest extends TestCase
{
    private Client $client;
    private IndexManager $indexManager;
    private EntityManagerInterface $entityManager;
    private Connection $dbConnection;
    private ProductIndexer $indexer;
    private Locale $locale;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->indexManager = $this->createMock(IndexManager::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->dbConnection = $this->createMock(Connection::class);

        $this->indexer = new ProductIndexer(
            $this->client,
            $this->indexManager,
            $this->entityManager,
            $this->dbConnection,
        );

        $this->locale = Locale::fromString('en_US');
    }

    private function createProduct(
        ?CategoryId $categoryId = null,
        bool $active = true,
        bool $featured = false,
        array $images = [],
    ): Product {
        return Product::reconstituteFromPersistence(
            id: ProductId::fromString('00000000-0000-4000-8000-000000000002'),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            sku: SKU::fromString('PRD-100001'),
            name: ProductName::fromString('Test Product'),
            description: 'A test product',
            shortDescription: 'Short desc',
            slug: Slug::fromString('test-product'),
            price: Money::fromScalars(9999, 'USD'),
            categoryId: $categoryId,
            stock: Stock::create(10),
            images: $images,
            status: $active ? ProductStatus::active() : ProductStatus::draft(),
            type: ProductType::simple(),
            isFeatured: $featured,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            updatedAt: new \DateTimeImmutable('2026-01-02'),
        );
    }

    private function mockNoRatingStats(): void
    {
        $this->dbConnection->method('executeQuery')
            ->willThrowException(new \Doctrine\DBAL\Exception\DatabaseRequired('Table not found'));
    }

    private function mockConfigurableProductNotFound(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->entityManager->method('getRepository')->willReturn($repo);
    }

    public function testIndexProductCreatesIndexAndIndexesDocument(): void
    {
        $product = $this->createProduct();
        $this->mockNoRatingStats();
        $this->mockConfigurableProductNotFound();
        $this->entityManager->method('find')->willReturn(null);

        $this->indexManager->method('getProductIndexName')->willReturn('products_en');
        $this->indexManager->expects(self::once())->method('createProductIndex');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')
            ->with(self::callback(function (array $params) {
                return 'products_en' === $params['index']
                    && '00000000-0000-4000-8000-000000000002' === $params['id']
                    && 'PRD-100001' === $params['body']['sku']
                    && 'Test Product' === $params['body']['name']
                    && 'active' === $params['body']['status']
                    && 99.99 === $params['body']['price'];
            }))
            ->willReturn($esResponse);

        $this->indexer->indexProduct($product, $this->locale);
    }

    public function testIndexProductWithCategoryHierarchy(): void
    {
        $categoryId = CategoryId::fromString('00000000-0000-4000-8000-000000000010');
        $product = $this->createProduct(categoryId: $categoryId);
        $this->mockNoRatingStats();
        $this->mockConfigurableProductNotFound();

        $parentCategoryId = '00000000-0000-4000-8000-000000000020';

        $categoryEntity = $this->createMock(CategoryEntity::class);
        $categoryEntity->method('getParentId')->willReturn($parentCategoryId);

        $parentEntity = $this->createMock(CategoryEntity::class);
        $parentEntity->method('getParentId')->willReturn(null);

        $this->entityManager->method('find')->willReturnCallback(
            function (string $class, string $id) use ($categoryId, $parentCategoryId, $categoryEntity, $parentEntity) {
                if ($id === $categoryId->toString()) {
                    return $categoryEntity;
                }
                if ($id === $parentCategoryId) {
                    return $parentEntity;
                }

                return null;
            }
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')
            ->with(self::callback(function (array $params) use ($categoryId, $parentCategoryId) {
                $catIds = $params['body']['category_ids'];

                return in_array($categoryId->toString(), $catIds, true)
                    && in_array($parentCategoryId, $catIds, true);
            }))
            ->willReturn($esResponse);

        $this->indexer->indexProduct($product, $this->locale);
    }

    public function testIndexProductWithImage(): void
    {
        $image = ProductImage::create('https://example.com/img.jpg', 0, true);
        $product = $this->createProduct(images: [$image]);
        $this->mockNoRatingStats();
        $this->mockConfigurableProductNotFound();
        $this->entityManager->method('find')->willReturn(null);

        $this->indexManager->method('getProductIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')
            ->with(self::callback(function (array $params) {
                return 'https://example.com/img.jpg' === $params['body']['image_url'];
            }))
            ->willReturn($esResponse);

        $this->indexer->indexProduct($product, $this->locale);
    }

    public function testIndexProductsEmptyArrayDoesNothing(): void
    {
        $this->client->expects(self::never())->method('bulk');

        $this->indexer->indexProducts([], $this->locale);
    }

    public function testIndexProductsBulkIndexes(): void
    {
        $product = $this->createProduct();
        $this->mockNoRatingStats();
        $this->mockConfigurableProductNotFound();
        $this->entityManager->method('find')->willReturn(null);

        $this->indexManager->method('getProductIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('bulk')
            ->with(self::callback(function (array $params) {
                return 2 === count($params['body']); // action + document
            }))
            ->willReturn($esResponse);

        $this->indexer->indexProducts([$product], $this->locale);
    }

    public function testDeleteProductWhenIndexExists(): void
    {
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000002');
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->indexManager->method('getCategoryIndexName')->willReturn('idx');
        $this->indexManager->method('getProductIndexName')->willReturn('idx');
        $this->indexManager->method('indexExists')->willReturn(true);

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('delete')->willReturn($esResponse);

        $this->indexer->deleteProduct($productId, $tenantId, $this->locale);
    }

    public function testDeleteProductWhenIndexDoesNotExist(): void
    {
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000002');
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->indexManager->method('getProductIndexName')->willReturn('idx');
        $this->indexManager->method('indexExists')->willReturn(false);

        $this->client->expects(self::never())->method('delete');

        $this->indexer->deleteProduct($productId, $tenantId, $this->locale);
    }

    public function testDeleteProductIgnoresNotFoundException(): void
    {
        $productId = ProductId::fromString('00000000-0000-4000-8000-000000000002');
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $this->indexManager->method('getProductIndexName')->willReturn('idx');
        $this->indexManager->method('indexExists')->willReturn(true);

        $this->client->method('delete')->willThrowException(new \RuntimeException('Not found'));

        // Should not throw
        $this->indexer->deleteProduct($productId, $tenantId, $this->locale);

        self::assertTrue(true);
    }

    public function testUpdateProductDelegatesToIndex(): void
    {
        $product = $this->createProduct();
        $this->mockNoRatingStats();
        $this->mockConfigurableProductNotFound();
        $this->entityManager->method('find')->willReturn(null);

        $this->indexManager->method('getProductIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')->willReturn($esResponse);

        $this->indexer->updateProduct($product, $this->locale);
    }

    public function testReindexTenantIndexesAllLocales(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $product = $this->createProduct();
        $this->mockNoRatingStats();
        $this->mockConfigurableProductNotFound();
        $this->entityManager->method('find')->willReturn(null);

        $this->indexManager->method('getProductIndexName')->willReturn('idx');

        $locale1 = Locale::fromString('en_US');
        $locale2 = Locale::fromString('de_DE');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        // Should call bulk once per locale
        $this->client->expects(self::exactly(2))->method('bulk')->willReturn($esResponse);

        $this->indexer->reindexTenant($tenantId, [$product], [$locale1, $locale2]);
    }

    public function testProductWithoutCategoryHasEmptyCategoryIds(): void
    {
        $product = $this->createProduct(categoryId: null);
        $this->mockNoRatingStats();
        $this->mockConfigurableProductNotFound();

        $this->indexManager->method('getProductIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')
            ->with(self::callback(function (array $params) {
                return [] === $params['body']['category_ids'];
            }))
            ->willReturn($esResponse);

        $this->indexer->indexProduct($product, $this->locale);
    }

    public function testInactiveProductIndexedWithInactiveStatus(): void
    {
        $product = $this->createProduct(active: false);
        $this->mockNoRatingStats();
        $this->mockConfigurableProductNotFound();
        $this->entityManager->method('find')->willReturn(null);

        $this->indexManager->method('getProductIndexName')->willReturn('idx');

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $this->client->expects(self::once())->method('index')
            ->with(self::callback(function (array $params) {
                return 'inactive' === $params['body']['status'];
            }))
            ->willReturn($esResponse);

        $this->indexer->indexProduct($product, $this->locale);
    }
}
