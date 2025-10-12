<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Elasticsearch;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use App\Shared\Infrastructure\Elasticsearch\SynonymManager;
use Elastic\Elasticsearch\Client;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class IndexManagerTest extends KernelTestCase
{
    private IndexManager $indexManager;
    private Client $client;
    private SynonymManager $synonymManager;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->client = $container->get(Client::class);
        $this->synonymManager = $container->get(SynonymManager::class);
        $this->indexManager = new IndexManager($this->client, $this->synonymManager);
        $this->tenantId = TenantId::generate();
    }

    protected function tearDown(): void
    {
        // Clean up test indices
        $locales = [
            Locale::fromString('en_US'),
            Locale::fromString('ro_RO'),
        ];

        foreach ($locales as $locale) {
            $productIndex = $this->indexManager->getProductIndexName($this->tenantId, $locale);
            $categoryIndex = $this->indexManager->getCategoryIndexName($this->tenantId, $locale);

            $this->indexManager->deleteIndex($productIndex);
            $this->indexManager->deleteIndex($categoryIndex);
        }

        parent::tearDown();
    }

    public function testCreateProductIndexCreatesIndex(): void
    {
        $locale = Locale::fromString('en_US');

        $this->indexManager->createProductIndex($this->tenantId, $locale);

        $indexName = $this->indexManager->getProductIndexName($this->tenantId, $locale);
        $this->assertTrue($this->indexManager->indexExists($indexName));
    }

    public function testCreateProductIndexIsIdempotent(): void
    {
        $locale = Locale::fromString('en_US');

        $this->indexManager->createProductIndex($this->tenantId, $locale);
        $this->indexManager->createProductIndex($this->tenantId, $locale); // Should not throw

        $indexName = $this->indexManager->getProductIndexName($this->tenantId, $locale);
        $this->assertTrue($this->indexManager->indexExists($indexName));
    }

    public function testCreateCategoryIndexCreatesIndex(): void
    {
        $locale = Locale::fromString('ro_RO');

        $this->indexManager->createCategoryIndex($this->tenantId, $locale);

        $indexName = $this->indexManager->getCategoryIndexName($this->tenantId, $locale);
        $this->assertTrue($this->indexManager->indexExists($indexName));
    }

    public function testCreateCategoryIndexIsIdempotent(): void
    {
        $locale = Locale::fromString('ro_RO');

        $this->indexManager->createCategoryIndex($this->tenantId, $locale);
        $this->indexManager->createCategoryIndex($this->tenantId, $locale); // Should not throw

        $indexName = $this->indexManager->getCategoryIndexName($this->tenantId, $locale);
        $this->assertTrue($this->indexManager->indexExists($indexName));
    }

    public function testDeleteIndexRemovesIndex(): void
    {
        $locale = Locale::fromString('en_US');

        $this->indexManager->createProductIndex($this->tenantId, $locale);

        $indexName = $this->indexManager->getProductIndexName($this->tenantId, $locale);
        $this->assertTrue($this->indexManager->indexExists($indexName));

        $this->indexManager->deleteIndex($indexName);
        $this->assertFalse($this->indexManager->indexExists($indexName));
    }

    public function testDeleteNonExistentIndexDoesNotThrow(): void
    {
        $this->indexManager->deleteIndex('non_existent_index');

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testIndexExistsReturnsFalseForNonExistentIndex(): void
    {
        $exists = $this->indexManager->indexExists('definitely_does_not_exist_' . uniqid());

        $this->assertFalse($exists);
    }

    public function testRefreshIndexSucceeds(): void
    {
        $locale = Locale::fromString('en_US');

        $this->indexManager->createProductIndex($this->tenantId, $locale);

        $indexName = $this->indexManager->getProductIndexName($this->tenantId, $locale);
        $this->indexManager->refresh($indexName);

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testRefreshNonExistentIndexDoesNotThrow(): void
    {
        $this->indexManager->refresh('non_existent_index');

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testGetProductIndexNameFormatsCorrectly(): void
    {
        $locale = Locale::fromString('en_US');

        $indexName = $this->indexManager->getProductIndexName($this->tenantId, $locale);

        $this->assertStringStartsWith($this->tenantId->toString(), $indexName);
        $this->assertStringContainsString('products', $indexName);
        $this->assertStringEndsWith('enus', $indexName);
    }

    public function testGetCategoryIndexNameFormatsCorrectly(): void
    {
        $locale = Locale::fromString('ro_RO');

        $indexName = $this->indexManager->getCategoryIndexName($this->tenantId, $locale);

        $this->assertStringStartsWith($this->tenantId->toString(), $indexName);
        $this->assertStringContainsString('categories', $indexName);
        $this->assertStringEndsWith('roro', $indexName);
    }

    public function testProductIndexHasCorrectMappings(): void
    {
        $locale = Locale::fromString('en_US');

        $this->indexManager->createProductIndex($this->tenantId, $locale);

        $indexName = $this->indexManager->getProductIndexName($this->tenantId, $locale);
        $mappings = $this->client->indices()->getMapping(['index' => $indexName])->asArray();

        $properties = $mappings[$indexName]['mappings']['properties'];

        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('tenant_id', $properties);
        $this->assertArrayHasKey('sku', $properties);
        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('description', $properties);
        $this->assertArrayHasKey('price', $properties);
        $this->assertArrayHasKey('status', $properties);

        $this->assertSame('keyword', $properties['id']['type']);
        $this->assertSame('text', $properties['name']['type']);
        $this->assertSame('scaled_float', $properties['price']['type']);
    }

    public function testCategoryIndexHasCorrectMappings(): void
    {
        $locale = Locale::fromString('ro_RO');

        $this->indexManager->createCategoryIndex($this->tenantId, $locale);

        $indexName = $this->indexManager->getCategoryIndexName($this->tenantId, $locale);
        $mappings = $this->client->indices()->getMapping(['index' => $indexName])->asArray();

        $properties = $mappings[$indexName]['mappings']['properties'];

        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('tenant_id', $properties);
        $this->assertArrayHasKey('name', $properties);
        $this->assertArrayHasKey('slug', $properties);
        $this->assertArrayHasKey('parent_id', $properties);
        $this->assertArrayHasKey('position', $properties);
        $this->assertArrayHasKey('is_active', $properties);

        $this->assertSame('keyword', $properties['id']['type']);
        $this->assertSame('text', $properties['name']['type']);
        $this->assertSame('integer', $properties['position']['type']);
        $this->assertSame('boolean', $properties['is_active']['type']);
    }

    public function testMultipleLocalesCreateSeparateIndices(): void
    {
        $locales = [
            Locale::fromString('en_US'),
            Locale::fromString('ro_RO'),
        ];

        foreach ($locales as $locale) {
            $this->indexManager->createProductIndex($this->tenantId, $locale);
        }

        foreach ($locales as $locale) {
            $indexName = $this->indexManager->getProductIndexName($this->tenantId, $locale);
            $this->assertTrue($this->indexManager->indexExists($indexName));
        }
    }
}
