<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\ProductImage;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Model\Variant;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\OptionValueCode;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Variant::class)]
#[CoversClass(VariantId::class)]
final class VariantExtendedTest extends TestCase
{
    private function makeVariant(
        array $optionValueMap = ['color' => 'red', 'size' => 'large'],
        int $price = 1999,
        int $stock = 10,
        bool $active = true,
    ): Variant {
        return Variant::create(
            id: VariantId::generate(),
            sku: VariantSKU::fromString('CLO-NIK-000001-red-large'),
            optionValueMap: $optionValueMap,
            price: Money::fromScalars($price, 'USD'),
            stock: Stock::create($stock),
            isActive: $active,
        );
    }

    // -------------------------------------------------------
    // VariantId
    // -------------------------------------------------------

    #[Test]
    public function variantIdGeneratesUniqueValues(): void
    {
        $id1 = VariantId::generate();
        $id2 = VariantId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function variantIdCreatesFromString(): void
    {
        $id = VariantId::fromString('var_test');

        self::assertSame('var_test', $id->toString());
        self::assertSame('var_test', $id->value());
        self::assertSame('var_test', (string) $id);
    }

    #[Test]
    public function variantIdThrowsOnEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Variant ID cannot be empty');

        VariantId::fromString('');
    }

    // -------------------------------------------------------
    // Variant creation
    // -------------------------------------------------------

    #[Test]
    public function itCreatesVariantWithValidData(): void
    {
        $id = VariantId::generate();
        $sku = VariantSKU::fromString('CLO-NIK-000001-blue-small');
        $optionMap = ['color' => 'blue', 'size' => 'small'];
        $price = Money::fromScalars(2500, 'EUR');
        $stock = Stock::create(5);

        $variant = Variant::create($id, $sku, $optionMap, $price, $stock, true);

        self::assertTrue($variant->getId()->equals($id));
        self::assertTrue($variant->getSku()->equals($sku));
        self::assertSame($optionMap, $variant->getOptionValueMap());
        self::assertSame(2500, $variant->getPrice()->getAmount());
        self::assertSame(5, $variant->getStock()->quantity());
        self::assertTrue($variant->isActive());
        self::assertCount(0, $variant->getImages());
    }

    #[Test]
    public function itThrowsWhenOptionValueMapIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Variant must have at least one option value');

        Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('CLO-NIK-000001-red'),
            [],
            Money::fromScalars(100, 'USD'),
            Stock::create(1),
        );
    }

    #[Test]
    public function itThrowsWhenOptionCodeIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('CLO-NIK-000001-red'),
            ['' => 'red'],
            Money::fromScalars(100, 'USD'),
            Stock::create(1),
        );
    }

    #[Test]
    public function itThrowsWhenValueCodeIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Variant::create(
            VariantId::generate(),
            VariantSKU::fromString('CLO-NIK-000001-red'),
            ['color' => ''],
            Money::fromScalars(100, 'USD'),
            Stock::create(1),
        );
    }

    #[Test]
    public function itReconstitutesFromPersistence(): void
    {
        $id = VariantId::fromString('var_persisted');
        $sku = VariantSKU::fromString('CLO-NIK-000001-red-large');
        $image = ProductImage::create('https://cdn.com/img.jpg', 0, true);
        $createdAt = new \DateTimeImmutable('2024-01-01');
        $updatedAt = new \DateTimeImmutable('2024-06-01');

        $variant = Variant::reconstituteFromPersistence(
            id: $id,
            sku: $sku,
            optionValueMap: ['color' => 'red', 'size' => 'large'],
            price: Money::fromScalars(1999, 'USD'),
            stock: Stock::create(10),
            images: [$image],
            isActive: true,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        self::assertTrue($variant->getId()->equals($id));
        self::assertCount(1, $variant->getImages());
        self::assertSame($createdAt, $variant->getCreatedAt());
        self::assertSame($updatedAt, $variant->getUpdatedAt());
    }

    // -------------------------------------------------------
    // update / updatePrice / updateStock
    // -------------------------------------------------------

    #[Test]
    public function itUpdatesDetails(): void
    {
        $variant = $this->makeVariant(active: true);
        $newPrice = Money::fromScalars(3000, 'USD');
        $newStock = Stock::create(20);

        $variant->update($newPrice, $newStock, false);

        self::assertSame(3000, $variant->getPrice()->getAmount());
        self::assertSame(20, $variant->getStock()->quantity());
        self::assertFalse($variant->isActive());
    }

    #[Test]
    public function itUpdatesPrice(): void
    {
        $variant = $this->makeVariant();
        $newPrice = Money::fromScalars(4500, 'EUR');

        $variant->updatePrice($newPrice);

        self::assertSame(4500, $variant->getPrice()->getAmount());
    }

    #[Test]
    public function itUpdatesStock(): void
    {
        $variant = $this->makeVariant(stock: 10);
        $newStock = Stock::create(50);

        $variant->updateStock($newStock);

        self::assertSame(50, $variant->getStock()->quantity());
    }

    // -------------------------------------------------------
    // activate / deactivate
    // -------------------------------------------------------

    #[Test]
    public function itActivatesVariant(): void
    {
        $variant = $this->makeVariant(active: false);
        self::assertFalse($variant->isActive());

        $variant->activate();

        self::assertTrue($variant->isActive());
    }

    #[Test]
    public function itDeactivatesVariant(): void
    {
        $variant = $this->makeVariant(active: true);
        self::assertTrue($variant->isActive());

        $variant->deactivate();

        self::assertFalse($variant->isActive());
    }

    // -------------------------------------------------------
    // Images
    // -------------------------------------------------------

    #[Test]
    public function itAddsImage(): void
    {
        $variant = $this->makeVariant();
        $image = ProductImage::create('https://cdn.com/photo.jpg', 0, true);

        $variant->addImage($image);

        self::assertCount(1, $variant->getImages());
        self::assertSame('https://cdn.com/photo.jpg', $variant->getImages()[0]->url());
    }

    #[Test]
    public function itRemovesImageByPosition(): void
    {
        $variant = $this->makeVariant();
        $img1 = ProductImage::create('https://cdn.com/img1.jpg', 0, true);
        $img2 = ProductImage::create('https://cdn.com/img2.jpg', 1, false);
        $variant->addImage($img1);
        $variant->addImage($img2);

        $variant->removeImage(0);

        $images = array_values($variant->getImages());
        self::assertCount(1, $images);
        self::assertSame('https://cdn.com/img2.jpg', $images[0]->url());
    }

    // -------------------------------------------------------
    // hasOptionValue / getOptionValue
    // -------------------------------------------------------

    #[Test]
    public function itChecksOptionValue(): void
    {
        $variant = $this->makeVariant(['color' => 'red', 'size' => 'large']);

        $hasRed = $variant->hasOptionValue(
            OptionCode::fromString('color'),
            OptionValueCode::fromString('red'),
        );
        $hasBlue = $variant->hasOptionValue(
            OptionCode::fromString('color'),
            OptionValueCode::fromString('blue'),
        );

        self::assertTrue($hasRed);
        self::assertFalse($hasBlue);
    }

    #[Test]
    public function itGetsOptionValue(): void
    {
        $variant = $this->makeVariant(['color' => 'red', 'size' => 'large']);

        $value = $variant->getOptionValue(OptionCode::fromString('color'));

        self::assertSame('red', $value);
    }

    #[Test]
    public function itReturnsNullForMissingOptionValue(): void
    {
        $variant = $this->makeVariant(['color' => 'red']);

        $value = $variant->getOptionValue(OptionCode::fromString('size'));

        self::assertNull($value);
    }

    // -------------------------------------------------------
    // matchesCombination
    // -------------------------------------------------------

    #[Test]
    public function itMatchesExactCombination(): void
    {
        $variant = $this->makeVariant(['color' => 'red', 'size' => 'large']);

        self::assertTrue($variant->matchesCombination(['color' => 'red', 'size' => 'large']));
    }

    #[Test]
    public function itMatchesCombinationRegardlessOfOrder(): void
    {
        $variant = $this->makeVariant(['color' => 'red', 'size' => 'large']);

        self::assertTrue($variant->matchesCombination(['size' => 'large', 'color' => 'red']));
    }

    #[Test]
    public function itDoesNotMatchDifferentValues(): void
    {
        $variant = $this->makeVariant(['color' => 'red', 'size' => 'large']);

        self::assertFalse($variant->matchesCombination(['color' => 'blue', 'size' => 'large']));
    }

    #[Test]
    public function itDoesNotMatchDifferentKeyCount(): void
    {
        $variant = $this->makeVariant(['color' => 'red', 'size' => 'large']);

        self::assertFalse($variant->matchesCombination(['color' => 'red']));
    }

    #[Test]
    public function itDoesNotMatchDifferentKeys(): void
    {
        $variant = $this->makeVariant(['color' => 'red', 'size' => 'large']);

        self::assertFalse($variant->matchesCombination(['color' => 'red', 'material' => 'large']));
    }

    // -------------------------------------------------------
    // isAvailable
    // -------------------------------------------------------

    #[Test]
    public function itIsAvailableWhenActiveAndInStock(): void
    {
        $variant = $this->makeVariant(stock: 5, active: true);

        self::assertTrue($variant->isAvailable());
    }

    #[Test]
    public function itIsNotAvailableWhenInactive(): void
    {
        $variant = $this->makeVariant(stock: 5, active: false);

        self::assertFalse($variant->isAvailable());
    }

    #[Test]
    public function itIsNotAvailableWhenOutOfStock(): void
    {
        $variant = $this->makeVariant(stock: 0, active: true);

        self::assertFalse($variant->isAvailable());
    }

    // -------------------------------------------------------
    // equals
    // -------------------------------------------------------

    #[Test]
    public function itEqualsSameId(): void
    {
        $id = VariantId::generate();
        $v1 = Variant::create($id, VariantSKU::fromString('CLO-NIK-000001-red'), ['color' => 'red'], Money::fromScalars(100, 'USD'), Stock::create(1));
        $v2 = Variant::create($id, VariantSKU::fromString('CLO-NIK-000002-red'), ['color' => 'red'], Money::fromScalars(200, 'USD'), Stock::create(2));

        self::assertTrue($v1->equals($v2));
    }

    #[Test]
    public function itDoesNotEqualDifferentId(): void
    {
        $v1 = $this->makeVariant(['color' => 'red']);
        $v2 = $this->makeVariant(['color' => 'blue']);

        self::assertFalse($v1->equals($v2));
    }
}
