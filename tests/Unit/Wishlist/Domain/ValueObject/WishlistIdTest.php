<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Domain\ValueObject;

use App\Wishlist\Domain\ValueObject\WishlistId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WishlistId::class)]
final class WishlistIdTest extends TestCase
{
    // -------------------------------------------------------
    // generate() / fromString()
    // -------------------------------------------------------

    #[Test]
    public function itGeneratesUniqueIds(): void
    {
        $id1 = WishlistId::generate();
        $id2 = WishlistId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itCreatesFromValidString(): void
    {
        $value = '550e8400-e29b-41d4-a716-446655440000';

        $id = WishlistId::fromString($value);

        self::assertSame($value, $id->toString());
        self::assertSame($value, $id->value());
    }

    #[Test]
    public function itThrowsWhenCreatedFromEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Wishlist ID cannot be empty');

        WishlistId::fromString('');
    }

    // -------------------------------------------------------
    // equals()
    // -------------------------------------------------------

    #[Test]
    public function itReturnsTrueForEqualIds(): void
    {
        $value = '550e8400-e29b-41d4-a716-446655440000';
        $id1 = WishlistId::fromString($value);
        $id2 = WishlistId::fromString($value);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function itReturnsFalseForDifferentIds(): void
    {
        $id1 = WishlistId::fromString('550e8400-e29b-41d4-a716-446655440001');
        $id2 = WishlistId::fromString('550e8400-e29b-41d4-a716-446655440002');

        self::assertFalse($id1->equals($id2));
    }

    // -------------------------------------------------------
    // __toString()
    // -------------------------------------------------------

    #[Test]
    public function itCastsToString(): void
    {
        $value = '550e8400-e29b-41d4-a716-446655440000';
        $id = WishlistId::fromString($value);

        self::assertSame($value, (string) $id);
    }

    #[Test]
    public function itRoundTripsViaString(): void
    {
        $id = WishlistId::generate();
        $restored = WishlistId::fromString($id->toString());

        self::assertTrue($id->equals($restored));
    }
}
