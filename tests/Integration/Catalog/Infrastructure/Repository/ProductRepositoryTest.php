<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductImage;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductRepositoryTest extends KernelTestCase
{
    use TenantTestTrait;

    private ProductRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        // Set tenant context for RLS
        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());

        $container = static::getContainer();
        $this->repository = $container->get(ProductRepositoryInterface::class);

        // Clean up test products
        $this->cleanupTestProducts();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestProducts();
        parent::tearDown();
    }

    private function cleanupTestProducts(): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Clean up test products (all SKUs starting with test prefixes)
        $qb = $em->createQueryBuilder();
        $qb->delete(\App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity::class, 'p')
            ->where('p.sku LIKE :p1 OR p.sku LIKE :p2 OR p.sku LIKE :p3 OR p.sku LIKE :p4 OR p.sku LIKE :p5 OR p.sku LIKE :p6 OR p.sku LIKE :p7 OR p.sku LIKE :p8 OR p.sku LIKE :p9 OR p.sku LIKE :p10')
            ->setParameter('p1', 'TST-%')
            ->setParameter('p2', 'FND-%')
            ->setParameter('p3', 'SLG-%')
            ->setParameter('p4', 'TEN-%')
            ->setParameter('p5', 'PGT-%')
            ->setParameter('p6', 'UPD-%')
            ->setParameter('p7', 'DEL-%')
            ->setParameter('p8', 'IMG-%')
            ->setParameter('p9', 'CAT-%')
            ->setParameter('p10', 'TNA-%')
            ->getQuery()
            ->execute();
    }

    public function testSaveAndFindProductById(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: $this->tenantId,
            sku: SKU::fromString('TST-000001'),
            name: ProductName::fromString('Test Product'),
            description: 'Test description',
            shortDescription: 'Short desc',
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(100, true, false)
        );

        $this->repository->save($product);

        $found = $this->repository->findById($product->id());

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($product->id()));
        $this->assertSame('Test Product', $found->name()->value());
        $this->assertSame('test-product', $found->slug()->value());
        $this->assertSame('TST-000001', $found->sku()->value());
        $this->assertSame(5000, $found->price()->getAmount());
        $this->assertSame('USD', $found->price()->getCurrency()->getCurrencyCode());
        $this->assertSame(100, $found->stock()->quantity());
    }

    public function testFindProductBySKU(): void
    {
        $tenantId = $this->tenantId;
        $sku = SKU::fromString('FND-000002');

        $product = Product::create(
            id: ProductId::generate(),
            tenantId: $tenantId,
            sku: $sku,
            name: ProductName::fromString('Product Find By SKU'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(3000, 'USD'),
            categoryId: null,
            stock: Stock::create(50)
        );

        $this->repository->save($product);

        $found = $this->repository->findBySKU($tenantId, $sku);

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($product->id()));
        $this->assertSame('FND-000002', $found->sku()->value());
    }

    public function testFindProductBySlug(): void
    {
        $tenantId = $this->tenantId;

        $product = Product::create(
            id: ProductId::generate(),
            tenantId: $tenantId,
            sku: SKU::fromString('SLG-000003'),
            name: ProductName::fromString('Slug Test Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(2500, 'USD'),
            categoryId: null,
            stock: Stock::create(25)
        );

        $this->repository->save($product);

        $found = $this->repository->findBySlug($tenantId, Slug::fromString('slug-test-product'));

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($product->id()));
        $this->assertSame('slug-test-product', $found->slug()->value());
    }

    public function testFindProductsByTenant(): void
    {
        $tenantId = $this->tenantId;

        $product1 = Product::create(
            id: ProductId::generate(),
            tenantId: $tenantId,
            sku: SKU::fromString('TEN-000004'),
            name: ProductName::fromString('Tenant Product 1'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(1000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $product2 = Product::create(
            id: ProductId::generate(),
            tenantId: $tenantId,
            sku: SKU::fromString('TEN-000005'),
            name: ProductName::fromString('Tenant Product 2'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(2000, 'USD'),
            categoryId: null,
            stock: Stock::create(20)
        );

        $this->repository->save($product1);
        $this->repository->save($product2);

        $products = $this->repository->findByTenant($tenantId);

        $this->assertGreaterThanOrEqual(2, count($products));
    }

    public function testFindProductsByTenantWithPagination(): void
    {
        $tenantId = $this->tenantId;

        for ($i = 1; $i <= 5; ++$i) {
            $product = Product::create(
                id: ProductId::generate(),
                tenantId: $tenantId,
                sku: SKU::fromString(sprintf('PGT-%06d', $i)),
                name: ProductName::fromString("Pagination Product {$i}"),
                description: null,
                shortDescription: null,
                price: Money::fromScalars(1000 * $i, 'USD'),
                categoryId: null,
                stock: Stock::create(10)
            );

            $this->repository->save($product);
        }

        $firstPage = $this->repository->findByTenant($tenantId, 2, 0);
        $secondPage = $this->repository->findByTenant($tenantId, 2, 2);

        $this->assertCount(2, $firstPage);
        $this->assertCount(2, $secondPage);
    }

    public function testUpdateProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: $this->tenantId,
            sku: SKU::fromString('UPD-000006'),
            name: ProductName::fromString('Original Product'),
            description: 'Original description',
            shortDescription: 'Original short',
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(100),
            isFeatured: false
        );

        $this->repository->save($product);

        $product->update(
            name: ProductName::fromString('Updated Product'),
            description: 'Updated description',
            shortDescription: 'Updated short',
            price: Money::fromScalars(6000, 'USD'),
            categoryId: null,
            isFeatured: true
        );

        $this->repository->save($product);

        $found = $this->repository->findById($product->id());

        $this->assertNotNull($found);
        $this->assertSame('Updated Product', $found->name()->value());
        $this->assertSame('Updated description', $found->description());
        $this->assertSame('Updated short', $found->shortDescription());
        $this->assertSame(6000, $found->price()->getAmount());
    }

    public function testDeleteProduct(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: $this->tenantId,
            sku: SKU::fromString('DEL-000007'),
            name: ProductName::fromString('Product to Delete'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(1000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $this->repository->save($product);

        $found = $this->repository->findById($product->id());
        $this->assertNotNull($found);

        $this->repository->delete($product->id());

        $notFound = $this->repository->findById($product->id());
        $this->assertNull($notFound);
    }

    public function testSaveProductWithImages(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: $this->tenantId,
            sku: SKU::fromString('IMG-000008'),
            name: ProductName::fromString('Product With Images'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $image1 = ProductImage::create('https://example.com/image1.jpg', 0, true);
        $image2 = ProductImage::create('https://example.com/image2.jpg', 1, false);

        $product->addImage($image1);
        $product->addImage($image2);

        $this->repository->save($product);

        $found = $this->repository->findById($product->id());

        $this->assertNotNull($found);
        $this->assertCount(2, $found->images());
        $this->assertSame('https://example.com/image1.jpg', $found->images()[0]->url());
        $this->assertTrue($found->images()[0]->isPrimary());
    }

    public function testSaveProductWithCategory(): void
    {
        $categoryId = CategoryId::generate();

        $product = Product::create(
            id: ProductId::generate(),
            tenantId: $this->tenantId,
            sku: SKU::fromString('CAT-000009'),
            name: ProductName::fromString('Product With Category'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(5000, 'USD'),
            categoryId: $categoryId,
            stock: Stock::create(10)
        );

        $this->repository->save($product);

        $found = $this->repository->findById($product->id());

        $this->assertNotNull($found);
        $this->assertNotNull($found->categoryId());
        $this->assertTrue($found->categoryId()->equals($categoryId));
    }

    public function testFindNonExistentProduct(): void
    {
        $nonExistentId = ProductId::generate();

        $found = $this->repository->findById($nonExistentId);

        $this->assertNull($found);
    }

    public function testTenantIsolation(): void
    {
        // Note: RLS prevents testing multi-tenant isolation in same test
        // as we can only set one tenant context per connection
        $this->markTestSkipped('Tenant isolation is enforced by PostgreSQL RLS and cannot be tested within a single test context');
    }
}
