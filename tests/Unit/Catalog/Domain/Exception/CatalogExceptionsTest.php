<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Exception;

use App\Catalog\Domain\Exception\CategoryNotFoundException;
use App\Catalog\Domain\Exception\InvalidCategoryNameException;
use App\Catalog\Domain\Exception\InvalidProductNameException;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryNotFoundException::class)]
#[CoversClass(InvalidCategoryNameException::class)]
#[CoversClass(InvalidProductNameException::class)]
#[CoversClass(ProductNotFoundException::class)]
final class CatalogExceptionsTest extends TestCase
{
    #[Test]
    public function categoryNameEmptyException(): void
    {
        $e = InvalidCategoryNameException::empty();
        self::assertStringContainsString('cannot be empty', $e->getMessage());
    }

    #[Test]
    public function categoryNameTooShortException(): void
    {
        $e = InvalidCategoryNameException::tooShort('Ab', 3);
        self::assertStringContainsString('too short', $e->getMessage());
        self::assertStringContainsString('Ab', $e->getMessage());
        self::assertStringContainsString('3', $e->getMessage());
    }

    #[Test]
    public function categoryNameTooLongException(): void
    {
        $name = str_repeat('x', 300);
        $e = InvalidCategoryNameException::tooLong($name, 255);
        self::assertStringContainsString('too long', $e->getMessage());
        self::assertStringContainsString('300', $e->getMessage());
        self::assertStringContainsString('255', $e->getMessage());
    }

    #[Test]
    public function productNameEmptyException(): void
    {
        $e = InvalidProductNameException::empty();
        self::assertStringContainsString('cannot be empty', $e->getMessage());
    }

    #[Test]
    public function productNameTooShortException(): void
    {
        $e = InvalidProductNameException::tooShort('X', 2);
        self::assertStringContainsString('too short', $e->getMessage());
        self::assertStringContainsString('X', $e->getMessage());
    }

    #[Test]
    public function productNameTooLongException(): void
    {
        $name = str_repeat('y', 500);
        $e = InvalidProductNameException::tooLong($name, 255);
        self::assertStringContainsString('too long', $e->getMessage());
        self::assertStringContainsString('500', $e->getMessage());
    }

    // --- ProductNotFoundException ---

    #[Test]
    public function productNotFoundWithId(): void
    {
        $id = ProductId::generate();
        $e = ProductNotFoundException::withId($id);

        self::assertInstanceOf(\DomainException::class, $e);
        self::assertStringContainsString($id->toString(), $e->getMessage());
    }

    #[Test]
    public function productNotFoundWithSku(): void
    {
        $sku = SKU::fromString('ABC-123456');
        $e = ProductNotFoundException::withSKU($sku);

        self::assertStringContainsString('ABC-123456', $e->getMessage());
    }

    #[Test]
    public function productNotFoundWithSlug(): void
    {
        $slug = Slug::fromString('cool-product');
        $e = ProductNotFoundException::withSlug($slug);

        self::assertStringContainsString('cool-product', $e->getMessage());
    }

    // --- CategoryNotFoundException ---

    #[Test]
    public function categoryNotFoundWithId(): void
    {
        $id = CategoryId::generate();
        $e = CategoryNotFoundException::withId($id);

        self::assertInstanceOf(\DomainException::class, $e);
        self::assertStringContainsString($id->toString(), $e->getMessage());
    }

    #[Test]
    public function categoryNotFoundWithSlug(): void
    {
        $slug = Slug::fromString('electronics');
        $e = CategoryNotFoundException::withSlug($slug);

        self::assertStringContainsString('electronics', $e->getMessage());
    }
}
