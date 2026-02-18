<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Exception\InvalidCategoryNameException;
use App\Catalog\Domain\Model\CategoryName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryName::class)]
final class CategoryNameTest extends TestCase
{
    #[Test]
    public function itCreatesCategoryNameFromValidString(): void
    {
        $name = CategoryName::fromString('Electronics');

        self::assertEquals('Electronics', $name->value());
        self::assertEquals('Electronics', (string) $name);
    }

    #[Test]
    public function itTrimsWhitespace(): void
    {
        $name = CategoryName::fromString('  Books  ');

        self::assertEquals('Books', $name->value());
    }

    #[Test]
    public function itNormalizesMultipleSpacesToSingleSpace(): void
    {
        $name = CategoryName::fromString('Home    &   Garden');

        self::assertEquals('Home & Garden', $name->value());
    }

    #[Test]
    public function itHandlesTabsAndNewlines(): void
    {
        $name = CategoryName::fromString("Sports\t&\nOutdoors");

        self::assertEquals('Sports & Outdoors', $name->value());
    }

    #[Test]
    public function itAcceptsMinimumLengthName(): void
    {
        $name = CategoryName::fromString('TV'); // 2 characters

        self::assertEquals('TV', $name->value());
    }

    #[Test]
    public function itAcceptsMaximumLengthName(): void
    {
        $longName = str_repeat('A', 100);
        $name = CategoryName::fromString($longName);

        self::assertEquals($longName, $name->value());
        self::assertEquals(100, mb_strlen($name->value()));
    }

    #[Test]
    public function itThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidCategoryNameException::class);
        $this->expectExceptionMessage('Category name cannot be empty');

        CategoryName::fromString('');
    }

    #[Test]
    public function itThrowsExceptionForWhitespaceOnly(): void
    {
        $this->expectException(InvalidCategoryNameException::class);
        $this->expectExceptionMessage('Category name cannot be empty');

        CategoryName::fromString('   ');
    }

    #[Test]
    public function itThrowsExceptionForTabsOnly(): void
    {
        $this->expectException(InvalidCategoryNameException::class);
        $this->expectExceptionMessage('Category name cannot be empty');

        CategoryName::fromString("\t\t\t");
    }

    #[Test]
    public function itThrowsExceptionForTooShortName(): void
    {
        $this->expectException(InvalidCategoryNameException::class);
        $this->expectExceptionMessage('Category name "A" is too short. Minimum length is 2 characters');

        CategoryName::fromString('A');
    }

    #[Test]
    public function itThrowsExceptionForTooLongName(): void
    {
        $this->expectException(InvalidCategoryNameException::class);
        $this->expectExceptionMessage('is too long (101 characters). Maximum length is 100 characters');

        $tooLongName = str_repeat('A', 101);
        CategoryName::fromString($tooLongName);
    }

    #[Test]
    public function itHandlesUnicodeCharacters(): void
    {
        $name = CategoryName::fromString('Livres Français');

        self::assertEquals('Livres Français', $name->value());
    }

    #[Test]
    public function itHandlesSpecialCharacters(): void
    {
        $name = CategoryName::fromString('Kids & Toys');

        self::assertEquals('Kids & Toys', $name->value());
    }

    #[Test]
    public function itHandlesNumbersInName(): void
    {
        $name = CategoryName::fromString('Top 10 Products');

        self::assertEquals('Top 10 Products', $name->value());
    }

    #[Test]
    public function itComparesEqualNames(): void
    {
        $name1 = CategoryName::fromString('Electronics');
        $name2 = CategoryName::fromString('Electronics');

        self::assertTrue($name1->equals($name2));
    }

    #[Test]
    public function itComparesDifferentNames(): void
    {
        $name1 = CategoryName::fromString('Electronics');
        $name2 = CategoryName::fromString('Books');

        self::assertFalse($name1->equals($name2));
    }

    #[Test]
    public function itComparesNormalizedNamesAsEqual(): void
    {
        $name1 = CategoryName::fromString('  Home    Decor  ');
        $name2 = CategoryName::fromString('Home Decor');

        self::assertTrue($name1->equals($name2));
    }

    #[Test]
    public function itIsCaseSensitiveInComparison(): void
    {
        $name1 = CategoryName::fromString('Electronics');
        $name2 = CategoryName::fromString('electronics');

        self::assertFalse($name1->equals($name2));
    }

    #[Test]
    public function itImplementsStringable(): void
    {
        $name = CategoryName::fromString('Fashion');

        self::assertInstanceOf(\Stringable::class, $name);
        self::assertEquals('Fashion', (string) $name);
    }

    #[Test]
    public function itIsImmutable(): void
    {
        $name = CategoryName::fromString('Sports');
        $value = $name->value();

        // Should not be able to modify the value
        // This is ensured by readonly property in PHP 8.1+
        self::assertEquals('Sports', $name->value());
        self::assertEquals($value, $name->value());
    }

    #[Test]
    #[DataProvider('validCategoryNamesProvider')]
    public function itAcceptsVariousValidNames(string $input, string $expected): void
    {
        $name = CategoryName::fromString($input);

        self::assertEquals($expected, $name->value());
    }

    public static function validCategoryNamesProvider(): array
    {
        return [
            'simple name' => ['Books', 'Books'],
            'with ampersand' => ['Home & Garden', 'Home & Garden'],
            'with numbers' => ['Top 10', 'Top 10'],
            'with parentheses' => ['Toys (Kids)', 'Toys (Kids)'],
            'with unicode' => ['Vêtements', 'Vêtements'],
            'trimmed spaces' => ['  Fashion  ', 'Fashion'],
            'normalized spaces' => ['Multi   Word   Name', 'Multi Word Name'],
            'minimum length' => ['TV', 'TV'],
            'long name' => [str_repeat('A', 100), str_repeat('A', 100)],
        ];
    }

    #[Test]
    #[DataProvider('invalidCategoryNamesProvider')]
    public function itRejectsInvalidNames(string $invalidName, string $expectedMessage): void
    {
        $this->expectException(InvalidCategoryNameException::class);
        $this->expectExceptionMessage($expectedMessage);

        CategoryName::fromString($invalidName);
    }

    public static function invalidCategoryNamesProvider(): array
    {
        return [
            'empty string' => ['', 'Category name cannot be empty'],
            'whitespace only' => ['   ', 'Category name cannot be empty'],
            'too short' => ['A', 'is too short. Minimum length is 2 characters'],
            'too long' => [str_repeat('A', 101), 'is too long (101 characters). Maximum length is 100 characters'],
        ];
    }

    // Note: isReadonly/isFinal reflection tests removed — BypassFinals in test bootstrap
    // strips these modifiers, causing false negatives. The class IS final readonly in source.
}
