<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Console;

use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Console\ReindexElasticsearchCommand;
use App\Catalog\Infrastructure\Elasticsearch\CategoryIndexer;
use App\Catalog\Infrastructure\Elasticsearch\ProductIndexer;
use App\Shared\Application\Service\TenantContextInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ReindexElasticsearchCommand::class)]
#[Group('unit')]
#[Group('infrastructure')]
final class ReindexElasticsearchCommandTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;
    private CategoryRepositoryInterface&MockObject $categoryRepository;
    private ProductIndexer&MockObject $productIndexer;
    private CategoryIndexer&MockObject $categoryIndexer;
    private TenantContextInterface&MockObject $tenantContext;
    private CommandTester $tester;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->productIndexer = $this->createMock(ProductIndexer::class);
        $this->categoryIndexer = $this->createMock(CategoryIndexer::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        $command = new ReindexElasticsearchCommand(
            $this->productRepository,
            $this->categoryRepository,
            $this->productIndexer,
            $this->categoryIndexer,
            $this->tenantContext,
        );

        $this->tester = new CommandTester($command);
    }

    // -----------------------------------------------------------------------
    // Invalid tenant ID
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsFailureForInvalidTenantId(): void
    {
        $exit = $this->tester->execute(['tenant-id' => 'not-a-valid-uuid']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('Invalid tenant ID', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Default (all entities) reindex
    // -----------------------------------------------------------------------

    #[Test]
    public function itReindexesAllEntitiesByDefault(): void
    {
        $product = $this->makeProduct();
        $category = $this->makeCategory();

        $this->productRepository->method('findByTenant')->willReturn([$product]);
        $this->categoryRepository->method('findByTenant')->willReturn([$category]);

        // 2 locales (en_US + ro_RO) x 1 product = 2 indexProduct calls
        $this->productIndexer->expects(self::exactly(2))->method('indexProduct');
        // 2 locales x 1 category = 2 indexCategory calls
        $this->categoryIndexer->expects(self::exactly(2))->method('indexCategory');

        $exit = $this->tester->execute(['tenant-id' => self::TENANT_ID]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Reindexing completed!', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Products only
    // -----------------------------------------------------------------------

    #[Test]
    public function itReindexesProductsOnly(): void
    {
        $product = $this->makeProduct();

        $this->productRepository->method('findByTenant')->willReturn([$product]);
        $this->categoryRepository->expects(self::never())->method('findByTenant');

        $this->productIndexer->expects(self::exactly(2))->method('indexProduct');
        $this->categoryIndexer->expects(self::never())->method('indexCategory');

        $exit = $this->tester->execute([
            'tenant-id' => self::TENANT_ID,
            '--entity' => 'products',
        ]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Categories only
    // -----------------------------------------------------------------------

    #[Test]
    public function itReindexesCategoriesOnly(): void
    {
        $category = $this->makeCategory();

        $this->categoryRepository->method('findByTenant')->willReturn([$category]);
        $this->productRepository->expects(self::never())->method('findByTenant');

        $this->productIndexer->expects(self::never())->method('indexProduct');
        $this->categoryIndexer->expects(self::exactly(2))->method('indexCategory');

        $exit = $this->tester->execute([
            'tenant-id' => self::TENANT_ID,
            '--entity' => 'categories',
        ]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // No products found
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesNoProductsFound(): void
    {
        $this->productRepository->method('findByTenant')->willReturn([]);
        $this->categoryRepository->method('findByTenant')->willReturn([]);

        $this->productIndexer->expects(self::never())->method('indexProduct');

        $exit = $this->tester->execute([
            'tenant-id' => self::TENANT_ID,
            '--entity' => 'products',
        ]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('No products found for this tenant', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // No categories found
    // -----------------------------------------------------------------------

    #[Test]
    public function itHandlesNoCategoriesFound(): void
    {
        $this->productRepository->method('findByTenant')->willReturn([]);
        $this->categoryRepository->method('findByTenant')->willReturn([]);

        $this->categoryIndexer->expects(self::never())->method('indexCategory');

        $exit = $this->tester->execute([
            'tenant-id' => self::TENANT_ID,
            '--entity' => 'categories',
        ]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('No categories found for this tenant', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Specific locale
    // -----------------------------------------------------------------------

    #[Test]
    public function itReindexesWithSpecificLocale(): void
    {
        $product = $this->makeProduct();
        $this->productRepository->method('findByTenant')->willReturn([$product]);

        // Only 1 locale = 1 call
        $this->productIndexer->expects(self::once())->method('indexProduct');

        $exit = $this->tester->execute([
            'tenant-id' => self::TENANT_ID,
            '--entity' => 'products',
            '--locale' => 'en_US',
        ]);

        self::assertSame(Command::SUCCESS, $exit);
    }

    // -----------------------------------------------------------------------
    // Exception during product indexing is caught
    // -----------------------------------------------------------------------

    #[Test]
    public function itContinuesWhenProductIndexingFails(): void
    {
        $product = $this->makeProduct();
        $this->productRepository->method('findByTenant')->willReturn([$product]);
        $this->categoryRepository->method('findByTenant')->willReturn([]);

        $this->productIndexer
            ->method('indexProduct')
            ->willThrowException(new \Exception('ES down'));

        $exit = $this->tester->execute(['tenant-id' => self::TENANT_ID]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Failed to index product', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Exception during category indexing is caught
    // -----------------------------------------------------------------------

    #[Test]
    public function itContinuesWhenCategoryIndexingFails(): void
    {
        $this->productRepository->method('findByTenant')->willReturn([]);
        $category = $this->makeCategory();
        $this->categoryRepository->method('findByTenant')->willReturn([$category]);

        $this->categoryIndexer
            ->method('indexCategory')
            ->willThrowException(new \Exception('Index write error'));

        $exit = $this->tester->execute(['tenant-id' => self::TENANT_ID]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Failed to index category', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Multiple products and locales — progress bar coverage
    // -----------------------------------------------------------------------

    #[Test]
    public function itIndexesMultipleProductsAcrossLocales(): void
    {
        $products = [$this->makeProduct(), $this->makeProduct()];
        $this->productRepository->method('findByTenant')->willReturn($products);
        $this->categoryRepository->method('findByTenant')->willReturn([]);

        // 2 products x 2 locales = 4 calls
        $this->productIndexer->expects(self::exactly(4))->method('indexProduct');

        $exit = $this->tester->execute(['tenant-id' => self::TENANT_ID]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('Indexed 2 products in 2 locales', $this->tester->getDisplay());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeProduct(): Product&MockObject
    {
        $id = ProductId::fromString('00000000-0000-4000-8000-000000000002');

        $product = $this->createMock(Product::class);
        $product->method('id')->willReturn($id);

        return $product;
    }

    private function makeCategory(): Category&MockObject
    {
        $id = CategoryId::fromString('00000000-0000-4000-8000-000000000003');

        $category = $this->createMock(Category::class);
        $category->method('id')->willReturn($id);

        return $category;
    }
}
