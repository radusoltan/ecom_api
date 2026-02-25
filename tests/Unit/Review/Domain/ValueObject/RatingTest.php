<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Domain\ValueObject;

use App\Review\Domain\ValueObject\Rating;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rating::class)]
final class RatingTest extends TestCase
{
    #[Test]
    #[TestWith([1])]
    #[TestWith([2])]
    #[TestWith([3])]
    #[TestWith([4])]
    #[TestWith([5])]
    public function fromIntCreatesValidRating(int $value): void
    {
        $rating = Rating::fromInt($value);

        self::assertSame($value, $rating->value());
        self::assertSame((string) $value, (string) $rating);
    }

    #[Test]
    public function fromIntWithZeroThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rating must be between 1 and 5');

        Rating::fromInt(0);
    }

    #[Test]
    public function fromIntWithSixThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rating must be between 1 and 5');

        Rating::fromInt(6);
    }

    #[Test]
    public function fromIntWithNegativeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Rating::fromInt(-1);
    }

    #[Test]
    public function equalsReturnsTrueForSameRating(): void
    {
        $a = Rating::fromInt(4);
        $b = Rating::fromInt(4);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentRating(): void
    {
        $a = Rating::fromInt(3);
        $b = Rating::fromInt(5);

        self::assertFalse($a->equals($b));
    }
}
