<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Exception\InvalidProductNameException;
use App\Catalog\Domain\Model\ProductName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductName::class)]
final class ProductNameTest extends TestCase
{
    #[Test]
    public function it_creates_product_name_from_valid_string(): void
    {
        $name = ProductName::fromString('Test Product');

        self::assertEquals('Test Product', $name->value());
        self::assertEquals('Test Product', (string) $name);
    }

    #[Test]
    public function it_trims_whitespace(): void
    {
        $name = ProductName::fromString('  Test Product  ');

        self::assertEquals('Test Product', $name->value());
    }

    #[Test]
    public function it_normalizes_multiple_spaces_to_single_space(): void
    {
        $name = ProductName::fromString('Test    Product   Name');

        self::assertEquals('Test Product Name', $name->value());
    }

    #[Test]
    public function it_handles_tabs_and_newlines(): void
    {
        $name = ProductName::fromString("Test\t\tProduct\n\nName");

        self::assertEquals('Test Product Name', $name->value());
    }

    #[Test]
    public function it_accepts_minimum_length_name(): void
    {
        $name = ProductName::fromString('ABC'); // 3 characters

        self::assertEquals('ABC', $name->value());
    }

    #[Test]
    public function it_accepts_maximum_length_name(): void
    {
        $longName = str_repeat('A', 255);
        $name = ProductName::fromString($longName);

        self::assertEquals($longName, $name->value());
        self::assertEquals(255, mb_strlen($name->value()));
    }

    #[Test]
    public function it_throws_exception_for_empty_string(): void
    {
        $this->expectException(InvalidProductNameException::class);
        $this->expectExceptionMessage('Product name cannot be empty');

        ProductName::fromString('');
    }

    #[Test]
    public function it_throws_exception_for_whitespace_only(): void
    {
        $this->expectException(InvalidProductNameException::class);
        $this->expectExceptionMessage('Product name cannot be empty');

        ProductName::fromString('   ');
    }

    #[Test]
    public function it_throws_exception_for_tabs_only(): void
    {
        $this->expectException(InvalidProductNameException::class);
        $this->expectExceptionMessage('Product name cannot be empty');

        ProductName::fromString("\t\t\t");
    }

    #[Test]
    public function it_throws_exception_for_too_short_name(): void
    {
        $this->expectException(InvalidProductNameException::class);
        $this->expectExceptionMessage('Product name "AB" is too short. Minimum length is 3 characters');

        ProductName::fromString('AB');
    }

    #[Test]
    public function it_throws_exception_for_too_long_name(): void
    {
        $this->expectException(InvalidProductNameException::class);
        $this->expectExceptionMessage('is too long (256 characters). Maximum length is 255 characters');

        $tooLongName = str_repeat('A', 256);
        ProductName::fromString($tooLongName);
    }

    #[Test]
    public function it_handles_unicode_characters(): void
    {
        $name = ProductName::fromString('Produit Français');

        self::assertEquals('Produit Français', $name->value());
    }

    #[Test]
    public function it_handles_special_characters(): void
    {
        $name = ProductName::fromString('Product & Service (2025)');

        self::assertEquals('Product & Service (2025)', $name->value());
    }

    #[Test]
    public function it_handles_numbers_in_name(): void
    {
        $name = ProductName::fromString('iPhone 15 Pro Max');

        self::assertEquals('iPhone 15 Pro Max', $name->value());
    }

    #[Test]
    public function it_compares_equal_names(): void
    {
        $name1 = ProductName::fromString('Test Product');
        $name2 = ProductName::fromString('Test Product');

        self::assertTrue($name1->equals($name2));
    }

    #[Test]
    public function it_compares_different_names(): void
    {
        $name1 = ProductName::fromString('Test Product');
        $name2 = ProductName::fromString('Other Product');

        self::assertFalse($name1->equals($name2));
    }

    #[Test]
    public function it_compares_normalized_names_as_equal(): void
    {
        $name1 = ProductName::fromString('  Test    Product  ');
        $name2 = ProductName::fromString('Test Product');

        self::assertTrue($name1->equals($name2));
    }

    #[Test]
    public function it_is_case_sensitive_in_comparison(): void
    {
        $name1 = ProductName::fromString('Test Product');
        $name2 = ProductName::fromString('test product');

        self::assertFalse($name1->equals($name2));
    }

    #[Test]
    public function it_implements_stringable(): void
    {
        $name = ProductName::fromString('Test Product');

        self::assertInstanceOf(\Stringable::class, $name);
        self::assertEquals('Test Product', (string) $name);
    }

    #[Test]
    public function it_is_immutable(): void
    {
        $name = ProductName::fromString('Test Product');
        $value = $name->value();

        // Should not be able to modify the value
        // This is ensured by readonly property in PHP 8.1+
        self::assertEquals('Test Product', $name->value());
        self::assertEquals($value, $name->value());
    }

    #[Test]
    #[DataProvider('validProductNamesProvider')]
    public function it_accepts_various_valid_names(string $input, string $expected): void
    {
        $name = ProductName::fromString($input);

        self::assertEquals($expected, $name->value());
    }

    public static function validProductNamesProvider(): array
    {
        return [
            'simple name' => ['Simple Product', 'Simple Product'],
            'with numbers' => ['Product 123', 'Product 123'],
            'with special chars' => ['Product & Co.', 'Product & Co.'],
            'with parentheses' => ['Product (New)', 'Product (New)'],
            'with unicode' => ['Café Latté', 'Café Latté'],
            'trimmed spaces' => ['  Spaced Product  ', 'Spaced Product'],
            'normalized spaces' => ['Multi   Space   Name', 'Multi Space Name'],
            'minimum length' => ['ABC', 'ABC'],
            'long name' => [str_repeat('A', 255), str_repeat('A', 255)],
        ];
    }

    #[Test]
    #[DataProvider('invalidProductNamesProvider')]
    public function it_rejects_invalid_names(string $invalidName, string $expectedMessage): void
    {
        $this->expectException(InvalidProductNameException::class);
        $this->expectExceptionMessage($expectedMessage);

        ProductName::fromString($invalidName);
    }

    public static function invalidProductNamesProvider(): array
    {
        return [
            'empty string' => ['', 'Product name cannot be empty'],
            'whitespace only' => ['   ', 'Product name cannot be empty'],
            'too short' => ['AB', 'is too short. Minimum length is 3 characters'],
            'too long' => [str_repeat('A', 256), 'is too long (256 characters). Maximum length is 255 characters'],
        ];
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $name = ProductName::fromString('Test Product');
        $reflection = new \ReflectionClass($name);

        self::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function it_is_final(): void
    {
        $reflection = new \ReflectionClass(ProductName::class);

        self::assertTrue($reflection->isFinal());
    }
}
