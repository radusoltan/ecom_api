<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\DTO;

use App\Catalog\Application\DTO\AdminProductListDto;
use App\Catalog\Application\DTO\OptionDTO;
use App\Catalog\Application\DTO\OptionValueDTO;
use App\Catalog\Application\DTO\ProductImportResult;
use App\Catalog\Application\DTO\StorefrontCategoryDto;
use App\Catalog\Application\DTO\StorefrontProductDto;
use App\Catalog\Application\DTO\VariantDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OptionDTO::class)]
#[CoversClass(OptionValueDTO::class)]
#[CoversClass(ProductImportResult::class)]
#[CoversClass(StorefrontCategoryDto::class)]
#[CoversClass(StorefrontProductDto::class)]
#[CoversClass(VariantDTO::class)]
#[CoversClass(AdminProductListDto::class)]
final class CatalogDTOsTest extends TestCase
{
    // --- StorefrontProductDto ---

    #[Test]
    public function storefrontProductDtoConstruction(): void
    {
        $dto = new StorefrontProductDto(
            id: 'prod-1',
            slug: 'cool-product',
            name: 'Cool Product',
            price: ['amount' => 2999, 'currency' => 'USD'],
            primaryImage: ['urlSm' => '/sm.jpg', 'urlMd' => '/md.jpg', 'urlLg' => '/lg.jpg'],
            isFeatured: true,
            rating: 4.5,
            availability: 'in_stock',
            breadcrumbs: [['name' => 'Electronics', 'slug' => 'electronics']],
            description: 'A cool product',
        );

        self::assertSame('prod-1', $dto->id);
        self::assertSame('cool-product', $dto->slug);
        self::assertSame('Cool Product', $dto->name);
        self::assertSame(2999, $dto->price['amount']);
        self::assertSame('USD', $dto->price['currency']);
        self::assertTrue($dto->isFeatured);
        self::assertSame(4.5, $dto->rating);
        self::assertSame('in_stock', $dto->availability);
        self::assertNotNull($dto->breadcrumbs);
        self::assertSame('A cool product', $dto->description);
    }

    #[Test]
    public function storefrontProductDtoFromArray(): void
    {
        $dto = StorefrontProductDto::fromArray([
            'id' => 'prod-2',
            'slug' => 'another-product',
            'name' => 'Another Product',
            'price' => ['amount' => 1500, 'currency' => 'EUR'],
            'isFeatured' => true,
            'rating' => 3.8,
        ]);

        self::assertSame('prod-2', $dto->id);
        self::assertSame('another-product', $dto->slug);
        self::assertSame('Another Product', $dto->name);
        self::assertSame(1500, $dto->price['amount']);
        self::assertTrue($dto->isFeatured);
        self::assertSame(3.8, $dto->rating);
        self::assertNull($dto->primaryImage);
        self::assertNull($dto->availability);
        self::assertNull($dto->breadcrumbs);
        self::assertNull($dto->description);
    }

    #[Test]
    public function storefrontProductDtoFromEmptyArray(): void
    {
        $dto = StorefrontProductDto::fromArray([]);

        self::assertSame('', $dto->id);
        self::assertSame('', $dto->slug);
        self::assertSame('', $dto->name);
        self::assertSame(0, $dto->price['amount']);
        self::assertSame('USD', $dto->price['currency']);
        self::assertFalse($dto->isFeatured);
        self::assertNull($dto->rating);
    }

    #[Test]
    public function storefrontProductDtoDefaults(): void
    {
        $dto = new StorefrontProductDto(
            id: 'p-1',
            slug: 'slug',
            name: 'Name',
            price: ['amount' => 100, 'currency' => 'USD'],
        );

        self::assertNull($dto->primaryImage);
        self::assertFalse($dto->isFeatured);
        self::assertNull($dto->rating);
        self::assertNull($dto->availability);
        self::assertNull($dto->breadcrumbs);
        self::assertNull($dto->description);
    }

    #[Test]
    public function adminProductListDtoConstruction(): void
    {
        $dto = new AdminProductListDto(
            id: 'prod-1',
            sku: 'SKU-001',
            name: 'Admin Product',
            slug: 'admin-product',
            priceAmount: 1999,
            priceCurrency: 'USD',
            categoryId: 'cat-1',
            stockQuantity: 12,
            trackInventory: true,
            active: true,
            isFeatured: false,
            primaryImage: ['url' => '/image.jpg', 'position' => 0, 'isPrimary' => true],
            description: 'Short admin description',
            createdAt: '2026-03-10 10:00:00+00',
            updatedAt: '2026-03-10 11:00:00+00',
            variantCount: 4,
        );

        self::assertSame('prod-1', $dto->id);
        self::assertSame('SKU-001', $dto->sku);
        self::assertSame('Admin Product', $dto->name);
        self::assertSame(1999, $dto->priceAmount);
        self::assertSame('USD', $dto->priceCurrency);
        self::assertSame(12, $dto->stockQuantity);
        self::assertTrue($dto->trackInventory);
        self::assertTrue($dto->active);
        self::assertFalse($dto->isFeatured);
        self::assertSame('Short admin description', $dto->description);
        self::assertSame(4, $dto->variantCount);
    }

    // --- StorefrontCategoryDto ---

    #[Test]
    public function storefrontCategoryDtoConstruction(): void
    {
        $dto = new StorefrontCategoryDto(
            id: 'cat-1',
            slug: 'electronics',
            name: 'Electronics',
            image: ['urlSm' => '/sm.jpg'],
            showOnFront: true,
            childrenCount: 5,
            description: 'All electronics',
        );

        self::assertSame('cat-1', $dto->id);
        self::assertSame('electronics', $dto->slug);
        self::assertSame('Electronics', $dto->name);
        self::assertNotNull($dto->image);
        self::assertTrue($dto->showOnFront);
        self::assertSame(5, $dto->childrenCount);
        self::assertSame('All electronics', $dto->description);
    }

    #[Test]
    public function storefrontCategoryDtoFromArray(): void
    {
        $dto = StorefrontCategoryDto::fromArray([
            'id' => 'cat-2',
            'slug' => 'clothing',
            'name' => 'Clothing',
            'showOnFront' => true,
            'childrenCount' => 3,
        ]);

        self::assertSame('cat-2', $dto->id);
        self::assertSame('clothing', $dto->slug);
        self::assertSame('Clothing', $dto->name);
        self::assertNull($dto->image);
        self::assertTrue($dto->showOnFront);
        self::assertSame(3, $dto->childrenCount);
        self::assertNull($dto->description);
    }

    #[Test]
    public function storefrontCategoryDtoFromEmptyArray(): void
    {
        $dto = StorefrontCategoryDto::fromArray([]);

        self::assertSame('', $dto->id);
        self::assertSame('', $dto->slug);
        self::assertSame('', $dto->name);
        self::assertNull($dto->image);
        self::assertFalse($dto->showOnFront);
        self::assertSame(0, $dto->childrenCount);
    }

    // --- VariantDTO ---

    #[Test]
    public function variantDtoConstruction(): void
    {
        $dto = new VariantDTO(
            id: 'var-1',
            sku: 'SKU-001',
            optionValueMap: ['color' => 'red', 'size' => 'L'],
            priceAmount: 4999,
            priceCurrency: 'USD',
            stockQuantity: 50,
            trackInventory: true,
            allowBackorder: false,
            isActive: true,
            isAvailable: true,
            images: [['url' => '/img.jpg', 'position' => 0, 'isPrimary' => true]],
        );

        self::assertSame('var-1', $dto->id);
        self::assertSame('SKU-001', $dto->sku);
        self::assertSame('red', $dto->optionValueMap['color']);
        self::assertSame('L', $dto->optionValueMap['size']);
        self::assertSame(4999, $dto->priceAmount);
        self::assertSame('USD', $dto->priceCurrency);
        self::assertSame(50, $dto->stockQuantity);
        self::assertTrue($dto->trackInventory);
        self::assertFalse($dto->allowBackorder);
        self::assertTrue($dto->isActive);
        self::assertTrue($dto->isAvailable);
        self::assertCount(1, $dto->images);
    }

    #[Test]
    public function variantDtoDefaultImages(): void
    {
        $dto = new VariantDTO(
            id: 'var-2',
            sku: 'SKU-002',
            optionValueMap: [],
            priceAmount: 1000,
            priceCurrency: 'EUR',
            stockQuantity: 0,
            trackInventory: false,
            allowBackorder: true,
            isActive: false,
            isAvailable: false,
        );

        self::assertSame([], $dto->images);
        self::assertSame([], $dto->optionValueMap);
        self::assertFalse($dto->isActive);
        self::assertTrue($dto->allowBackorder);
    }

    // --- OptionDTO ---

    #[Test]
    public function optionDtoConstruction(): void
    {
        $value1 = new OptionValueDTO(id: 'ov-1', code: 'red', nameTranslations: ['en' => 'Red', 'de' => 'Rot'], position: 0);
        $value2 = new OptionValueDTO(id: 'ov-2', code: 'blue', nameTranslations: ['en' => 'Blue'], position: 1);

        $dto = new OptionDTO(
            id: 'opt-1',
            code: 'color',
            nameTranslations: ['en' => 'Color', 'de' => 'Farbe'],
            position: 0,
            values: [$value1, $value2],
        );

        self::assertSame('opt-1', $dto->id);
        self::assertSame('color', $dto->code);
        self::assertSame('Color', $dto->nameTranslations['en']);
        self::assertSame('Farbe', $dto->nameTranslations['de']);
        self::assertSame(0, $dto->position);
        self::assertCount(2, $dto->values);
    }

    #[Test]
    public function optionDtoDefaultValues(): void
    {
        $dto = new OptionDTO(
            id: 'opt-2',
            code: 'size',
            nameTranslations: ['en' => 'Size'],
            position: 1,
        );

        self::assertSame([], $dto->values);
    }

    // --- OptionValueDTO ---

    #[Test]
    public function optionValueDtoConstruction(): void
    {
        $dto = new OptionValueDTO(
            id: 'ov-1',
            code: 'xl',
            nameTranslations: ['en' => 'Extra Large', 'fr' => 'Très Grand'],
            position: 3,
        );

        self::assertSame('ov-1', $dto->id);
        self::assertSame('xl', $dto->code);
        self::assertSame('Extra Large', $dto->nameTranslations['en']);
        self::assertSame('Très Grand', $dto->nameTranslations['fr']);
        self::assertSame(3, $dto->position);
    }

    // --- ProductImportResult ---

    #[Test]
    public function importResultSuccessful(): void
    {
        $result = new ProductImportResult(
            totalRows: 100,
            imported: 90,
            updated: 10,
            skipped: 0,
            errors: [],
        );

        self::assertTrue($result->isSuccessful());
        self::assertFalse($result->hasPartialSuccess());
        self::assertSame(0, $result->errorCount());
    }

    #[Test]
    public function importResultPartialSuccess(): void
    {
        $result = new ProductImportResult(
            totalRows: 50,
            imported: 30,
            updated: 5,
            skipped: 10,
            errors: [5 => ['message' => 'Invalid SKU']],
        );

        self::assertFalse($result->isSuccessful());
        self::assertTrue($result->hasPartialSuccess());
        self::assertSame(1, $result->errorCount());
    }

    #[Test]
    public function importResultAllFailed(): void
    {
        $result = new ProductImportResult(
            totalRows: 10,
            imported: 0,
            updated: 0,
            skipped: 8,
            errors: [1 => ['message' => 'Bad data'], 2 => ['message' => 'Missing field']],
        );

        self::assertFalse($result->isSuccessful());
        self::assertFalse($result->hasPartialSuccess());
        self::assertSame(2, $result->errorCount());
    }

    #[Test]
    public function importResultToArray(): void
    {
        $result = new ProductImportResult(
            totalRows: 20,
            imported: 15,
            updated: 3,
            skipped: 1,
            errors: [7 => ['message' => 'Duplicate']],
        );

        $array = $result->toArray();

        self::assertSame(20, $array['total_rows']);
        self::assertSame(15, $array['imported']);
        self::assertSame(3, $array['updated']);
        self::assertSame(1, $array['skipped']);
        self::assertSame(1, $array['error_count']);
        self::assertFalse($array['is_successful']);
        self::assertTrue($array['has_partial_success']);
    }
}
