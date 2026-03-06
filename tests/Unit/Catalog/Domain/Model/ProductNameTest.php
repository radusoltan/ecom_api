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
    public function itCreatesProductNameFromValidString(): void
    {
        $name = ProductName::fromString('Test Product');

        self::assertEquals('Test Product', $name->value());
        self::assertEquals('Test Product', (string) $name);
    }

    #[Test]
    public function itTrimsWhitespace(): void
    {
        $name = ProductName::fromString('  Test Product  ');

        self::assertEquals('Test Product', $name->value());
    }

    #[Test]
    public function itNormalizesMultipleSpacesToSingleSpace(): void
    {
        $name = ProductName::fromString('Test    Product   Name');

        self::assertEquals('Test Product Name', $name->value());
    }

    #[Test]
    public function itHandlesTabsAndNewlines(): void
    {
        $name = ProductName::fromString("Test\t\tProduct\n\nName");

        self::assertEquals('Test Product Name', $name->value());
    }

    #[Test]
    public function itAcceptsMinimumLengthName(): void
    {
        $name = ProductName::fromString('ABC'); // 3 characters

        self::assertEquals('ABC', $name->value());
    }

    #[Test]
    public function itAcceptsMaximumLengthName(): void
    {
        $longName = str_repeat('A', 255);
        $name = ProductName::fromString($longName);

        self::assertEquals($longName, $name->value());
        self::assertEquals(255, mb_strlen($name->value()));
    }

    #[Test]
    public function itThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidProductNameException::class);
        $this->expectExceptionMessage('Product name cannot be empty');

        ProductName::fromString('');
    }

    #[Test]
    public function itThrowsExceptionForWhitespaceOnly(): void
    {
        $this->expectException(InvalidProductNameException::class);
        $this->expectExceptionMessage('Product name cannot be empty');

        ProductName::fromString('   ');
    }

    #[Test]
    public function itThrowsExceptionForTabsOnly(): void
    {
        $this->expectException(InvalidProductNameException::class);
        $this->expectExceptionMessage('Product name cannot be empty');

        ProductName::fromString("\t\t\t");
    }

    #[Test]
    public function itThrowsExceptionForTooShortName(): void
    {
        $this->expectException(InvalidProductNameException::class);
        $this->expectExceptionMessage('Product name "AB" is too short. Minimum length is 3 characters');

        ProductName::fromString('AB');
    }

    #[Test]
    public function itThrowsExceptionForTooLongName(): void
    {
        $this->expectException(InvalidProductNameException::class);
        $this->expectExceptionMessage('is too long (256 characters). Maximum length is 255 characters');

        $tooLongName = str_repeat('A', 256);
        ProductName::fromString($tooLongName);
    }

    #[Test]
    public function itHandlesUnicodeCharacters(): void
    {
        $name = ProductName::fromString('Produit Français');

        self::assertEquals('Produit Français', $name->value());
    }

    #[Test]
    public function itHandlesSpecialCharacters(): void
    {
        $name = ProductName::fromString('Product & Service (2025)');

        self::assertEquals('Product & Service (2025)', $name->value());
    }

    #[Test]
    public function itHandlesNumbersInName(): void
    {
        $name = ProductName::fromString('iPhone 15 Pro Max');

        self::assertEquals('iPhone 15 Pro Max', $name->value());
    }

    #[Test]
    public function itComparesEqualNames(): void
    {
        $name1 = ProductName::fromString('Test Product');
        $name2 = ProductName::fromString('Test Product');

        self::assertTrue($name1->equals($name2));
    }

    #[Test]
    public function itComparesDifferentNames(): void
    {
        $name1 = ProductName::fromString('Test Product');
        $name2 = ProductName::fromString('Other Product');

        self::assertFalse($name1->equals($name2));
    }

    #[Test]
    public function itComparesNormalizedNamesAsEqual(): void
    {
        $name1 = ProductName::fromString('  Test    Product  ');
        $name2 = ProductName::fromString('Test Product');

        self::assertTrue($name1->equals($name2));
    }

    #[Test]
    public function itIsCaseSensitiveInComparison(): void
    {
        $name1 = ProductName::fromString('Test Product');
        $name2 = ProductName::fromString('test product');

        self::assertFalse($name1->equals($name2));
    }

    #[Test]
    public function itImplementsStringable(): void
    {
        $name = ProductName::fromString('Test Product');

        self::assertInstanceOf(\Stringable::class, $name);
        self::assertEquals('Test Product', (string) $name);
    }

    #[Test]
    public function itIsImmutable(): void
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
    public function itAcceptsVariousValidNames(string $input, string $expected): void
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
    public function itRejectsInvalidNames(string $invalidName, string $expectedMessage): void
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
}
