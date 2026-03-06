<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\GetCategories;
use App\Catalog\Application\Query\GetCategoriesHandler;
use App\Catalog\Application\Query\GetCategoryBySlug;
use App\Catalog\Application\Query\GetCategoryBySlugHandler;
use App\Catalog\Application\Query\GetProductById;
use App\Catalog\Application\Query\GetProductByIdHandler;
use App\Catalog\Application\Query\GetProductBySlug;
use App\Catalog\Application\Query\GetProductBySlugHandler;
use App\Catalog\Application\Query\GetProducts;
use App\Catalog\Application\Query\GetProductsHandler;
use App\Catalog\Domain\Exception\CategoryNotFoundException;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetProductByIdHandler::class)]
#[CoversClass(GetProductBySlugHandler::class)]
#[CoversClass(GetProductsHandler::class)]
#[CoversClass(GetCategoriesHandler::class)]
#[CoversClass(GetCategoryBySlugHandler::class)]
final class CatalogQueryHandlersTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // ---------------------------------------------------------------
    // GetProductByIdHandler
    // ---------------------------------------------------------------

    #[Test]
    public function getProductByIdReturnsProduct(): void
    {
        $productId = ProductId::generate();
        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($this->tenantId);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with($productId)
            ->willReturn($product);

        $handler = new GetProductByIdHandler($repo);
        $result = $handler(new GetProductById($this->tenantId, $productId));

        self::assertSame($product, $result);
    }

    #[Test]
    public function getProductByIdThrowsWhenNotFound(): void
    {
        $productId = ProductId::generate();
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new GetProductByIdHandler($repo);

        $this->expectException(ProductNotFoundException::class);
        $handler(new GetProductById($this->tenantId, $productId));
    }

    #[Test]
    public function getProductByIdThrowsWhenTenantMismatch(): void
    {
        $productId = ProductId::generate();
        $otherTenant = TenantId::fromString('00000000-0000-4000-8000-000000000002');

        $product = $this->createMock(Product::class);
        $product->method('tenantId')->willReturn($otherTenant);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findById')->willReturn($product);

        $handler = new GetProductByIdHandler($repo);

        $this->expectException(ProductNotFoundException::class);
        $handler(new GetProductById($this->tenantId, $productId));
    }

    // ---------------------------------------------------------------
    // GetProductBySlugHandler
    // ---------------------------------------------------------------

    #[Test]
    public function getProductBySlugReturnsProduct(): void
    {
        $slug = Slug::fromString('test-product');
        $product = $this->createMock(Product::class);

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findBySlug')
            ->with($this->tenantId, $slug)
            ->willReturn($product);

        $handler = new GetProductBySlugHandler($repo);
        $result = $handler(new GetProductBySlug($this->tenantId, $slug));

        self::assertSame($product, $result);
    }

    #[Test]
    public function getProductBySlugThrowsWhenNotFound(): void
    {
        $slug = Slug::fromString('nonexistent');
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findBySlug')->willReturn(null);

        $handler = new GetProductBySlugHandler($repo);

        $this->expectException(ProductNotFoundException::class);
        $handler(new GetProductBySlug($this->tenantId, $slug));
    }

    // ---------------------------------------------------------------
    // GetProductsHandler
    // ---------------------------------------------------------------

    #[Test]
    public function getProductsReturnsList(): void
    {
        $products = [$this->createMock(Product::class), $this->createMock(Product::class)];

        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findByTenant')
            ->with($this->tenantId, 100, 0)
            ->willReturn($products);

        $handler = new GetProductsHandler($repo);
        $result = $handler(new GetProducts($this->tenantId));

        self::assertCount(2, $result);
    }

    #[Test]
    public function getProductsReturnsEmptyWhenNone(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->method('findByTenant')->willReturn([]);

        $handler = new GetProductsHandler($repo);
        $result = $handler(new GetProducts($this->tenantId));

        self::assertSame([], $result);
    }

    #[Test]
    public function getProductsPassesLimitAndOffset(): void
    {
        $repo = $this->createMock(ProductRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findByTenant')
            ->with($this->tenantId, 25, 50)
            ->willReturn([]);

        $handler = new GetProductsHandler($repo);
        $handler(new GetProducts($this->tenantId, 25, 50));
    }

    // ---------------------------------------------------------------
    // GetCategoriesHandler
    // ---------------------------------------------------------------

    #[Test]
    public function getCategoriesReturnsList(): void
    {
        $categories = [$this->createMock(Category::class)];

        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findByTenant')
            ->with($this->tenantId)
            ->willReturn($categories);

        $handler = new GetCategoriesHandler($repo);
        $result = $handler(new GetCategories($this->tenantId));

        self::assertCount(1, $result);
    }

    #[Test]
    public function getCategoriesReturnsEmptyWhenNone(): void
    {
        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $repo->method('findByTenant')->willReturn([]);

        $handler = new GetCategoriesHandler($repo);
        $result = $handler(new GetCategories($this->tenantId));

        self::assertSame([], $result);
    }

    // ---------------------------------------------------------------
    // GetCategoryBySlugHandler
    // ---------------------------------------------------------------

    #[Test]
    public function getCategoryBySlugReturnsCategory(): void
    {
        $slug = Slug::fromString('electronics');
        $category = $this->createMock(Category::class);

        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findBySlug')
            ->with($this->tenantId, $slug)
            ->willReturn($category);

        $handler = new GetCategoryBySlugHandler($repo);
        $result = $handler(new GetCategoryBySlug($this->tenantId, $slug));

        self::assertSame($category, $result);
    }

    #[Test]
    public function getCategoryBySlugThrowsWhenNotFound(): void
    {
        $slug = Slug::fromString('nonexistent');
        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $repo->method('findBySlug')->willReturn(null);

        $handler = new GetCategoryBySlugHandler($repo);

        $this->expectException(CategoryNotFoundException::class);
        $handler(new GetCategoryBySlug($this->tenantId, $slug));
    }
}
