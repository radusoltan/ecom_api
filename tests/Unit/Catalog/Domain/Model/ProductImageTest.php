<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\ProductImage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductImage::class)]
final class ProductImageTest extends TestCase
{
    // -------------------------------------------------------
    // Construction
    // -------------------------------------------------------

    #[Test]
    public function itCreatesImageWithValidUrl(): void
    {
        $image = ProductImage::create('https://example.com/image.jpg', 0, true);

        self::assertSame('https://example.com/image.jpg', $image->url());
        self::assertSame(0, $image->position());
        self::assertTrue($image->isPrimary());
    }

    #[Test]
    public function itCreatesImageWithDefaultsNotPrimary(): void
    {
        $image = ProductImage::create('https://cdn.shop.com/product.png');

        self::assertSame('https://cdn.shop.com/product.png', $image->url());
        self::assertSame(0, $image->position());
        self::assertFalse($image->isPrimary());
    }

    #[Test]
    public function itCreatesImageAtSpecificPosition(): void
    {
        $image = ProductImage::create('https://example.com/img.jpg', 3, false);

        self::assertSame(3, $image->position());
        self::assertFalse($image->isPrimary());
    }

    #[Test]
    public function itThrowsWhenUrlIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Image URL cannot be empty');

        ProductImage::create('');
    }

    // -------------------------------------------------------
    // toArray / fromArray round-trip
    // -------------------------------------------------------

    #[Test]
    public function itConvertsToArray(): void
    {
        $image = ProductImage::create('https://example.com/photo.jpg', 2, true);
        $array = $image->toArray();

        self::assertSame('https://example.com/photo.jpg', $array['url']);
        self::assertSame(2, $array['position']);
        self::assertTrue($array['isPrimary']);
    }

    #[Test]
    public function itCreatesFromArray(): void
    {
        $data = [
            'url' => 'https://example.com/image.jpg',
            'position' => 1,
            'isPrimary' => true,
        ];

        $image = ProductImage::fromArray($data);

        self::assertSame('https://example.com/image.jpg', $image->url());
        self::assertSame(1, $image->position());
        self::assertTrue($image->isPrimary());
    }

    #[Test]
    public function itCreatesFromArrayWithDefaults(): void
    {
        $data = ['url' => 'https://example.com/image.jpg'];

        $image = ProductImage::fromArray($data);

        self::assertSame(0, $image->position());
        self::assertFalse($image->isPrimary());
    }

    #[Test]
    public function roundTripToArrayAndBack(): void
    {
        $original = ProductImage::create('https://example.com/roundtrip.jpg', 5, true);
        $reconstructed = ProductImage::fromArray($original->toArray());

        self::assertSame($original->url(), $reconstructed->url());
        self::assertSame($original->position(), $reconstructed->position());
        self::assertSame($original->isPrimary(), $reconstructed->isPrimary());
    }
}
