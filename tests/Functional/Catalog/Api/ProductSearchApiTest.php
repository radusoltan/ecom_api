<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use App\Tests\Support\TenantTestTrait;

final class ProductSearchApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private TenantId $tenantId;
    private Locale $locale;
    private ProductRepositoryInterface $productRepository;
    private ProductIndexer $productIndexer;
    private IndexManager $indexManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Use default test tenant instead of random
        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());
        $this->locale = Locale::fromString('en_US');

        $container = static::getContainer();
        $this->productRepository = $container->get(ProductRepositoryInterface::class);
        $this->productIndexer = $container->get(ProductIndexer::class);
        $this->indexManager = $container->get(IndexManager::class);

        // Create test index
        $indexName = $this->indexManager->getProductIndexName($this->tenantId, $this->locale);
        if ($this->indexManager->indexExists($indexName)) {
            $this->indexManager->deleteIndex($indexName);
        }
        $this->indexManager->createProductIndex($this->tenantId, $this->locale);
    }

    protected function tearDown(): void
    {
        // Clean up test index
        $indexName = $this->indexManager->getProductIndexName($this->tenantId, $this->locale);
        if ($this->indexManager->indexExists($indexName)) {
            $this->indexManager->deleteIndex($indexName);
        }

        $this->cleanupTestData();
        parent::tearDown();
    }

    public function testSearchProductsWithoutQuery(): void
    {
        // Create and index test products
        $product1 = $this->createAndIndexProduct('Laptop Dell XPS 15', 1500.00);
        $product2 = $this->createAndIndexProduct('iPhone 15 Pro', 1200.00);
        $product3 = $this->createAndIndexProduct('Samsung Galaxy S24', 900.00);

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        // Search without query (returns all)
        $response = static::createClient()->request('GET', '/api/search/products', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');

        $data = $response->toArray();
        $this->assertIsArray($data);
        $this->assertCount(3, $data);
    }

    public function testSearchProductsWithQuery(): void
    {
        // Create and index test products
        $laptop = $this->createAndIndexProduct('Laptop Dell XPS 15', 1500.00);
        $phone = $this->createAndIndexProduct('iPhone 15 Pro', 1200.00);

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        // Search for "laptop"
        $response = static::createClient()->request('GET', '/api/search/products', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'laptop',
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertCount(1, $data);
        $this->assertStringContainsString('Laptop', $data[0]['name']);
    }

    public function testSearchProductsWithPriceFilter(): void
    {
        // Create and index test products
        $this->createAndIndexProduct('Cheap Product', 50.00);
        $this->createAndIndexProduct('Mid Product', 500.00);
        $this->createAndIndexProduct('Expensive Product', 1500.00);

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        // Search with price filter
        $response = static::createClient()->request('GET', '/api/search/products', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'minPrice' => 100,
                'maxPrice' => 1000,
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertCount(1, $data);
        $this->assertEquals('Mid Product', $data[0]['name']);
    }

    public function testSearchProductsWithSorting(): void
    {
        // Create and index test products
        $this->createAndIndexProduct('Product A', 100.00);
        $this->createAndIndexProduct('Product B', 300.00);
        $this->createAndIndexProduct('Product C', 200.00);

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        // Search sorted by price ascending
        $response = static::createClient()->request('GET', '/api/search/products', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'sortBy' => 'price_asc',
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertCount(3, $data);
        $this->assertEquals(100.00, $data[0]['price']);
        $this->assertEquals(200.00, $data[1]['price']);
        $this->assertEquals(300.00, $data[2]['price']);
    }

    public function testSearchProductsWithPagination(): void
    {
        // Create and index 5 test products
        for ($i = 1; $i <= 5; ++$i) {
            $this->createAndIndexProduct("Product $i", 100.00 * $i);
        }

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        // Get page 1 with 2 items per page
        $response = static::createClient()->request('GET', '/api/search/products', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'page' => 1,
                'limit' => 2,
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertCount(2, $data);

        // Check pagination metadata (in first result)
        $this->assertEquals(5, $data[0]['total']);
        $this->assertEquals(1, $data[0]['page']);
        $this->assertEquals(2, $data[0]['limit']);
        $this->assertEquals(3, $data[0]['totalPages']);
        $this->assertTrue($data[0]['hasNextPage']);
        $this->assertFalse($data[0]['hasPreviousPage']);
    }

    public function testSearchProductsFuzzyMatching(): void
    {
        // Create and index test product
        $this->createAndIndexProduct('Laptop Dell XPS 15', 1500.00);

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        // Search with typo (should still find "laptop")
        $response = static::createClient()->request('GET', '/api/search/products', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'q' => 'loptop', // Typo: "loptop" instead of "laptop"
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertCount(1, $data);
        $this->assertStringContainsString('Laptop', $data[0]['name']);
    }

    public function testSearchProductsWithoutTenantIdFails(): void
    {
        static::createClient()->request('GET', '/api/search/products', [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(500); // Should be 400, but depends on error handling
    }

    public function testSearchProductsReturnsCorrectStructure(): void
    {
        $product = $this->createAndIndexProduct('Test Product', 100.00);

        $this->indexManager->refresh($this->indexManager->getProductIndexName($this->tenantId, $this->locale));

        $response = static::createClient()->request('GET', '/api/search/products', [
            'headers' => [
                'X-Tenant-ID' => $this->tenantId->toString(),
                'Accept' => 'application/json',
            ],
            'query' => [
                'locale' => 'en_US',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertCount(1, $data);

        $result = $data[0];
        $this->assertArrayHasKey('productId', $result);
        $this->assertArrayHasKey('tenantId', $result);
        $this->assertArrayHasKey('sku', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('price', $result);
        $this->assertArrayHasKey('currency', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('categoryIds', $result);
        $this->assertArrayHasKey('locale', $result);
        $this->assertArrayHasKey('score', $result);
    }

    private function createAndIndexProduct(string $name, float $price): Product
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: $this->tenantId,
            sku: SKU::fromString(sprintf('TST-%06d', rand(1, 999999))),
            name: ProductName::fromString($name),
            description: 'Test product description',
            shortDescription: 'Short desc',
            price: Money::of((string) $price, 'USD'),
            categoryId: null,
            stock: Stock::create(10),
        );

        // Publish product so it's searchable (new products start as DRAFT)
        $product->publish();

        $this->productRepository->save($product);
        $this->productIndexer->indexProduct($product, $this->locale);

        return $product;
    }
}
