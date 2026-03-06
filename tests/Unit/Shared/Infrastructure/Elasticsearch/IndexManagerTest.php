<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Elasticsearch;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use App\Shared\Infrastructure\Elasticsearch\SynonymManager;
use Elastic\Elasticsearch\Client;
use PHPUnit\Framework\TestCase;

final class IndexManagerTest extends TestCase
{
    private IndexManager $indexManager;

    protected function setUp(): void
    {
        $client = $this->createMock(Client::class);
        $synonymManager = $this->createMock(SynonymManager::class);
        $synonymManager->method('getSynonymsForLocale')->willReturn([]);

        $this->indexManager = new IndexManager($client, $synonymManager);
    }

    public function testProductIndexNameIncludesTenantId(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $locale = Locale::fromString('en_US');

        $indexName = $this->indexManager->getProductIndexName($tenantId, $locale);

        self::assertStringContainsString('00000000-0000-4000-8000-000000000001', $indexName);
        self::assertStringContainsString('products', $indexName);
        self::assertStringContainsString('enus', $indexName);
    }

    public function testDifferentTenantsGetDifferentProductIndexNames(): void
    {
        $tenant1 = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $tenant2 = TenantId::fromString('11111111-1111-4111-8111-111111111111');
        $locale = Locale::fromString('en_US');

        $index1 = $this->indexManager->getProductIndexName($tenant1, $locale);
        $index2 = $this->indexManager->getProductIndexName($tenant2, $locale);

        self::assertNotSame($index1, $index2);
    }

    public function testDifferentLocalesGetDifferentProductIndexNames(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $localeEn = Locale::fromString('en_US');
        $localeRo = Locale::fromString('ro_RO');

        $indexEn = $this->indexManager->getProductIndexName($tenantId, $localeEn);
        $indexRo = $this->indexManager->getProductIndexName($tenantId, $localeRo);

        self::assertNotSame($indexEn, $indexRo);
        self::assertStringContainsString('enus', $indexEn);
        self::assertStringContainsString('roro', $indexRo);
    }

    public function testProductIndexNameFormat(): void
    {
        $tenantId = TenantId::fromString('bf1b1ff8-0000-4000-8000-000000000001');
        $locale = Locale::fromString('en_US');

        $indexName = $this->indexManager->getProductIndexName($tenantId, $locale);

        self::assertSame('bf1b1ff8-0000-4000-8000-000000000001_products_enus', $indexName);
    }

    public function testCategoryIndexNameIncludesTenantId(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $locale = Locale::fromString('en_US');

        $indexName = $this->indexManager->getCategoryIndexName($tenantId, $locale);

        self::assertStringContainsString('00000000-0000-4000-8000-000000000001', $indexName);
        self::assertStringContainsString('categories', $indexName);
    }

    public function testDifferentTenantsGetDifferentCategoryIndexNames(): void
    {
        $tenant1 = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $tenant2 = TenantId::fromString('22222222-2222-4222-8222-222222222222');
        $locale = Locale::fromString('de_DE');

        $index1 = $this->indexManager->getCategoryIndexName($tenant1, $locale);
        $index2 = $this->indexManager->getCategoryIndexName($tenant2, $locale);

        self::assertNotSame($index1, $index2);
    }
}
