<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductImage;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductRepositoryTest extends KernelTestCase
{
    private ProductRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

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

        $qb = $em->createQueryBuilder();
        $qb->delete(\App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity::class, 'p')
            ->where('p.sku LIKE :testSkuPrefix')
            ->setParameter('testSkuPrefix', '%-TST-%')
            ->getQuery()
            ->execute();
    }

    public function testSaveAndFindProductById(): void
    {
        $product = Product::create(
            id: ProductId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('ELC-TST-000001'),
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
        $this->assertSame('ELC-TST-000001', $found->sku()->value());
        $this->assertSame(5000, $found->price()->getAmount());
        $this->assertSame('USD', $found->price()->getCurrency()->getCurrencyCode());
        $this->assertSame(100, $found->stock()->quantity());
    }

    public function testFindProductBySKU(): void
    {
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');
        $sku = SKU::fromString('FND-TST-000002');

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
        $this->assertSame('FND-TST-000002', $found->sku()->value());
    }

    public function testFindProductBySlug(): void
    {
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');

        $product = Product::create(
            id: ProductId::generate(),
            tenantId: $tenantId,
            sku: SKU::fromString('SLG-TST-000003'),
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
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');

        $product1 = Product::create(
            id: ProductId::generate(),
            tenantId: $tenantId,
            sku: SKU::fromString('TEN-TST-000004'),
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
            sku: SKU::fromString('TEN-TST-000005'),
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
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');

        for ($i = 1; $i <= 5; $i++) {
            $product = Product::create(
                id: ProductId::generate(),
                tenantId: $tenantId,
                sku: SKU::fromString(sprintf('PGT-TST-%06d', $i)),
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
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('UPD-TST-000006'),
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
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('DEL-TST-000007'),
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
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('IMG-TST-000008'),
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
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            sku: SKU::fromString('CAT-TST-000009'),
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
        $tenant1Id = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');
        $tenant2Id = TenantId::fromString('8c4d7d8b-4a09-4b6e-8b5d-0123456789ab');

        $product1 = Product::create(
            id: ProductId::generate(),
            tenantId: $tenant1Id,
            sku: SKU::fromString('TNA-TST-100001'),
            name: ProductName::fromString('Tenant 1 Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(1000, 'USD'),
            categoryId: null,
            stock: Stock::create(10)
        );

        $product2 = Product::create(
            id: ProductId::generate(),
            tenantId: $tenant2Id,
            sku: SKU::fromString('TNB-TST-100001'),
            name: ProductName::fromString('Tenant 2 Product'),
            description: null,
            shortDescription: null,
            price: Money::fromScalars(2000, 'USD'),
            categoryId: null,
            stock: Stock::create(20)
        );

        $this->repository->save($product1);
        $this->repository->save($product2);

        $tenant1Products = $this->repository->findByTenant($tenant1Id);
        $tenant2Products = $this->repository->findByTenant($tenant2Id);

        $tenant1SKUs = array_map(fn($p) => $p->sku()->value(), $tenant1Products);
        $tenant2SKUs = array_map(fn($p) => $p->sku()->value(), $tenant2Products);

        $this->assertContains('TNA-TST-100001', $tenant1SKUs);
        $this->assertNotContains('TNB-TST-100001', $tenant1SKUs);
        $this->assertContains('TNB-TST-100001', $tenant2SKUs);
        $this->assertNotContains('TNA-TST-100001', $tenant2SKUs);
    }
}
