<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\ValueObject;

use App\Media\Domain\ValueObject\OwnerType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OwnerType::class)]
final class OwnerTypeTest extends TestCase
{
    // -------------------------------------------------------------------
    // fromString — valid cases
    // -------------------------------------------------------------------

    /**
     * @return array<string, array{string, OwnerType}>
     */
    public static function validOwnerTypes(): array
    {
        return [
            'product' => ['product', OwnerType::PRODUCT],
            'category' => ['category', OwnerType::CATEGORY],
            'user' => ['user', OwnerType::USER],
        ];
    }

    #[Test]
    #[DataProvider('validOwnerTypes')]
    public function itCreatesFromValidString(string $value, OwnerType $expected): void
    {
        self::assertSame($expected, OwnerType::fromString($value));
    }

    // -------------------------------------------------------------------
    // fromString — invalid cases
    // -------------------------------------------------------------------

    #[Test]
    public function itThrowsForUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported owner type "brand".');

        OwnerType::fromString('brand');
    }

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OwnerType::fromString('');
    }

    // -------------------------------------------------------------------
    // Enum values
    // -------------------------------------------------------------------

    #[Test]
    public function itExposesCorrectStringValues(): void
    {
        self::assertSame('product', OwnerType::PRODUCT->value);
        self::assertSame('category', OwnerType::CATEGORY->value);
        self::assertSame('user', OwnerType::USER->value);
    }
}
