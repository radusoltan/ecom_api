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
    public function it_creates_category_name_from_valid_string(): void
    {
        $name = CategoryName::fromString('Electronics');

        self::assertEquals('Electronics', $name->value());
        self::assertEquals('Electronics', (string) $name);
    }

    #[Test]
    public function it_trims_whitespace(): void
    {
        $name = CategoryName::fromString('  Books  ');

        self::assertEquals('Books', $name->value());
    }

    #[Test]
    public function it_normalizes_multiple_spaces_to_single_space(): void
    {
        $name = CategoryName::fromString('Home    &   Garden');

        self::assertEquals('Home & Garden', $name->value());
    }

    #[Test]
    public function it_handles_tabs_and_newlines(): void
    {
        $name = CategoryName::fromString("Sports\t&\nOutdoors");

        self::assertEquals('Sports & Outdoors', $name->value());
    }

    #[Test]
    public function it_accepts_minimum_length_name(): void
    {
        $name = CategoryName::fromString('TV'); // 2 characters

        self::assertEquals('TV', $name->value());
    }

    #[Test]
    public function it_accepts_maximum_length_name(): void
    {
        $longName = str_repeat('A', 100);
        $name = CategoryName::fromString($longName);

        self::assertEquals($longName, $name->value());
        self::assertEquals(100, mb_strlen($name->value()));
    }

    #[Test]
    public function it_throws_exception_for_empty_string(): void
    {
        $this->expectException(InvalidCategoryNameException::class);
        $this->expectExceptionMessage('Category name cannot be empty');

        CategoryName::fromString('');
    }

    #[Test]
    public function it_throws_exception_for_whitespace_only(): void
    {
        $this->expectException(InvalidCategoryNameException::class);
        $this->expectExceptionMessage('Category name cannot be empty');

        CategoryName::fromString('   ');
    }

    #[Test]
    public function it_throws_exception_for_tabs_only(): void
    {
        $this->expectException(InvalidCategoryNameException::class);
        $this->expectExceptionMessage('Category name cannot be empty');

        CategoryName::fromString("\t\t\t");
    }

    #[Test]
    public function it_throws_exception_for_too_short_name(): void
    {
        $this->expectException(InvalidCategoryNameException::class);
        $this->expectExceptionMessage('Category name "A" is too short. Minimum length is 2 characters');

        CategoryName::fromString('A');
    }

    #[Test]
    public function it_throws_exception_for_too_long_name(): void
    {
        $this->expectException(InvalidCategoryNameException::class);
        $this->expectExceptionMessage('is too long (101 characters). Maximum length is 100 characters');

        $tooLongName = str_repeat('A', 101);
        CategoryName::fromString($tooLongName);
    }

    #[Test]
    public function it_handles_unicode_characters(): void
    {
        $name = CategoryName::fromString('Livres Français');

        self::assertEquals('Livres Français', $name->value());
    }

    #[Test]
    public function it_handles_special_characters(): void
    {
        $name = CategoryName::fromString('Kids & Toys');

        self::assertEquals('Kids & Toys', $name->value());
    }

    #[Test]
    public function it_handles_numbers_in_name(): void
    {
        $name = CategoryName::fromString('Top 10 Products');

        self::assertEquals('Top 10 Products', $name->value());
    }

    #[Test]
    public function it_compares_equal_names(): void
    {
        $name1 = CategoryName::fromString('Electronics');
        $name2 = CategoryName::fromString('Electronics');

        self::assertTrue($name1->equals($name2));
    }

    #[Test]
    public function it_compares_different_names(): void
    {
        $name1 = CategoryName::fromString('Electronics');
        $name2 = CategoryName::fromString('Books');

        self::assertFalse($name1->equals($name2));
    }

    #[Test]
    public function it_compares_normalized_names_as_equal(): void
    {
        $name1 = CategoryName::fromString('  Home    Decor  ');
        $name2 = CategoryName::fromString('Home Decor');

        self::assertTrue($name1->equals($name2));
    }

    #[Test]
    public function it_is_case_sensitive_in_comparison(): void
    {
        $name1 = CategoryName::fromString('Electronics');
        $name2 = CategoryName::fromString('electronics');

        self::assertFalse($name1->equals($name2));
    }

    #[Test]
    public function it_implements_stringable(): void
    {
        $name = CategoryName::fromString('Fashion');

        self::assertInstanceOf(\Stringable::class, $name);
        self::assertEquals('Fashion', (string) $name);
    }

    #[Test]
    public function it_is_immutable(): void
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
    public function it_accepts_various_valid_names(string $input, string $expected): void
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
    public function it_rejects_invalid_names(string $invalidName, string $expectedMessage): void
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

    #[Test]
    public function it_is_readonly(): void
    {
        $name = CategoryName::fromString('Electronics');
        $reflection = new \ReflectionClass($name);

        self::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function it_is_final(): void
    {
        $reflection = new \ReflectionClass(CategoryName::class);

        self::assertTrue($reflection->isFinal());
    }
}
