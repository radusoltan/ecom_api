<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\ValueObject;

use App\Media\Domain\ValueObject\SizeLabel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SizeLabel::class)]
final class SizeLabelTest extends TestCase
{
    // -------------------------------------------------------------------
    // fromString — valid cases
    // -------------------------------------------------------------------

    /**
     * @return array<string, array{string, SizeLabel}>
     */
    public static function validSizeLabels(): array
    {
        return [
            'sm' => ['sm', SizeLabel::SMALL],
            'md' => ['md', SizeLabel::MEDIUM],
            'lg' => ['lg', SizeLabel::LARGE],
            'xl' => ['xl', SizeLabel::EXTRA_LARGE],
        ];
    }

    #[Test]
    #[DataProvider('validSizeLabels')]
    public function itCreatesFromValidString(string $value, SizeLabel $expected): void
    {
        self::assertSame($expected, SizeLabel::fromString($value));
    }

    // -------------------------------------------------------------------
    // fromString — invalid cases
    // -------------------------------------------------------------------

    #[Test]
    public function itThrowsForUnknownSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported thumbnail size "xxl".');

        SizeLabel::fromString('xxl');
    }

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SizeLabel::fromString('');
    }

    // -------------------------------------------------------------------
    // Enum values
    // -------------------------------------------------------------------

    #[Test]
    public function itExposesCorrectStringValues(): void
    {
        self::assertSame('sm', SizeLabel::SMALL->value);
        self::assertSame('md', SizeLabel::MEDIUM->value);
        self::assertSame('lg', SizeLabel::LARGE->value);
        self::assertSame('xl', SizeLabel::EXTRA_LARGE->value);
    }
}
