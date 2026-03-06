<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Slug::class)]
final class SlugTest extends TestCase
{
    // -------------------------------------------------------
    // Construction
    // -------------------------------------------------------

    #[Test]
    public function itCreatesValidSlug(): void
    {
        $slug = Slug::fromString('my-product-name');

        self::assertSame('my-product-name', $slug->value());
    }

    #[Test]
    public function itConvertsToLowercase(): void
    {
        $slug = Slug::fromString('My-Product');

        self::assertSame('my-product', $slug->value());
    }

    #[Test]
    public function itTrimsSurroundingWhitespace(): void
    {
        $slug = Slug::fromString('  product-name  ');

        self::assertSame('product-name', $slug->value());
    }

    #[Test]
    public function itThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slug cannot be empty');

        Slug::fromString('');
    }

    #[Test]
    public function itThrowsOnWhitespaceOnlyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Slug::fromString('   ');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSlugProvider(): array
    {
        return [
            'with space' => ['product name'],
            'starting with hyphen' => ['-product'],
            'ending with hyphen' => ['product-'],
            'with underscore' => ['product_name'],
            'with special chars' => ['product@name'],
            'with double hyphen' => ['product--name'],
        ];
    }

    #[Test]
    #[DataProvider('invalidSlugProvider')]
    public function itThrowsOnInvalidSlugFormat(string $invalid): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Slug::fromString($invalid);
    }

    // -------------------------------------------------------
    // Equality
    // -------------------------------------------------------

    #[Test]
    public function itEqualsSameValue(): void
    {
        $a = Slug::fromString('product-name');
        $b = Slug::fromString('product-name');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualDifferentValue(): void
    {
        $a = Slug::fromString('product-a');
        $b = Slug::fromString('product-b');

        self::assertFalse($a->equals($b));
    }

    // -------------------------------------------------------
    // __toString
    // -------------------------------------------------------

    #[Test]
    public function itCastsToString(): void
    {
        $slug = Slug::fromString('my-slug-123');

        self::assertSame('my-slug-123', (string) $slug);
    }

    // -------------------------------------------------------
    // Valid formats
    // -------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function validSlugProvider(): array
    {
        return [
            'single word' => ['product', 'product'],
            'multiple words' => ['my-product-name', 'my-product-name'],
            'with numbers' => ['product-123', 'product-123'],
            'all numbers' => ['123', '123'],
            'alphanumeric mixed' => ['abc123', 'abc123'],
        ];
    }

    #[Test]
    #[DataProvider('validSlugProvider')]
    public function itAcceptsValidSlugs(string $input, string $expected): void
    {
        $slug = Slug::fromString($input);

        self::assertSame($expected, $slug->value());
    }
}
