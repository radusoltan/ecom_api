<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Application\Service;

use App\Catalog\Application\Service\SkuGeneratorService;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId as SharedTenantId;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Shared\Domain\ValueObject\Email;
use App\Tests\Support\TenantTestTrait;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SkuGeneratorServiceTest extends KernelTestCase
{
    use TenantTestTrait;
    private SkuGeneratorService $service;
    private TenantRepositoryInterface $tenantRepository;
    private CategoryRepositoryInterface $categoryRepository;
    private Connection $connection;
    /** @var array<string> */
    private array $createdTenantIds = [];
    /** @var array<string> */
    private array $createdCategoryIds = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->service = $container->get(SkuGeneratorService::class);
        $this->tenantRepository = $container->get(TenantRepositoryInterface::class);
        $this->categoryRepository = $container->get(CategoryRepositoryInterface::class);
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->createdTenantIds = [];
        $this->createdCategoryIds = [];

        // Set tenant context for RLS
        $this->setTenantContext($this->getDefaultTenantId()->toString());

        $this->cleanupExistingFixtures();

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS catalog_sku_sequences (
                tenant_id VARCHAR(36) PRIMARY KEY,
                last_value BIGINT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM catalog_sku_sequences');
        foreach ($this->createdCategoryIds as $categoryId) {
            $this->connection->executeStatement(
                'DELETE FROM ext_translations WHERE foreign_key = :id',
                ['id' => $categoryId]
            );
            $this->connection->executeStatement(
                'DELETE FROM catalog_categories WHERE id = :id',
                ['id' => $categoryId]
            );
        }

        foreach ($this->createdTenantIds as $tenantId) {
            $this->connection->executeStatement(
                'DELETE FROM ext_translations WHERE foreign_key = :id',
                ['id' => $tenantId]
            );
            $this->connection->executeStatement(
                'DELETE FROM tenants WHERE id = :id',
                ['id' => $tenantId]
            );
        }

        $this->createdCategoryIds = [];
        $this->createdTenantIds = [];

        parent::tearDown();
    }

    private function cleanupExistingFixtures(): void
    {
        $emails = ['owner@example.com', 'shop@example.com'];

        foreach ($emails as $email) {
            $tenantIds = $this->connection->fetchFirstColumn(
                'SELECT id FROM tenants WHERE owner_email = :email',
                ['email' => $email]
            );

            foreach ($tenantIds as $tenantId) {
                $categoryIds = $this->connection->fetchFirstColumn(
                    'SELECT id FROM catalog_categories WHERE tenant_id = :tenantId',
                    ['tenantId' => $tenantId]
                );

                foreach ($categoryIds as $categoryId) {
                    $this->connection->executeStatement(
                        'DELETE FROM ext_translations WHERE foreign_key = :id',
                        ['id' => $categoryId]
                    );
                }

                $this->connection->executeStatement(
                    'DELETE FROM catalog_categories WHERE tenant_id = :tenantId',
                    ['tenantId' => $tenantId]
                );

                $this->connection->executeStatement(
                    'DELETE FROM ext_translations WHERE foreign_key = :id',
                    ['id' => $tenantId]
                );
            }

            $this->connection->executeStatement(
                'DELETE FROM tenants WHERE owner_email = :email',
                ['email' => $email]
            );
        }
    }

    public function testGeneratesSkuWithCategoryAndTenantCodes(): void
    {
        $tenant = Tenant::create(TenantName::fromString('Test Store'), Email::fromString('owner@example.com'));
        $this->createdTenantIds[] = $tenant->id()->toString();
        $this->tenantRepository->save($tenant);

        $sharedTenantId = SharedTenantId::fromString($tenant->id()->toString());

        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $sharedTenantId,
            name: CategoryName::fromString('Electronics'),
            description: null,
            parentId: null,
            position: 0,
            showOnFront: true
        );
        $this->createdCategoryIds[] = $category->id()->toString();
        $this->categoryRepository->save($category);

        $sku1 = $this->service->generate($sharedTenantId, $category->id());
        $sku2 = $this->service->generate($sharedTenantId, $category->id());

        self::assertSame('ELE-TES-000001', $sku1->value());
        self::assertSame('ELE-TES-000002', $sku2->value());
    }

    public function testGeneratesSkuWithGenericCategoryWhenMissing(): void
    {
        $tenant = Tenant::create(TenantName::fromString('Sample Shop'), Email::fromString('shop@example.com'));
        $this->createdTenantIds[] = $tenant->id()->toString();
        $this->tenantRepository->save($tenant);

        $sharedTenantId = SharedTenantId::fromString($tenant->id()->toString());

        $sku = $this->service->generate($sharedTenantId, null);

        self::assertSame('GEN-SAM-000001', $sku->value());
    }
}
