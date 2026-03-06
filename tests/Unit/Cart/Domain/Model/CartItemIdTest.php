<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Domain\Model;

use App\Cart\Domain\Model\CartItemId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CartItemId::class)]
final class CartItemIdTest extends TestCase
{
    #[Test]
    public function itGeneratesValidCartItemId(): void
    {
        $id = CartItemId::generate();

        self::assertInstanceOf(CartItemId::class, $id);
        self::assertNotEmpty($id->toString());
    }

    #[Test]
    public function itCreatesFromValidUlid(): void
    {
        $generated = CartItemId::generate();
        $id = CartItemId::fromString($generated->toString());

        self::assertSame($generated->toString(), $id->toString());
    }

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CartItemId cannot be empty');

        CartItemId::fromString('');
    }

    #[Test]
    public function itThrowsForInvalidUlid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CartItemId format');

        CartItemId::fromString('not-a-valid-ulid');
    }

    #[Test]
    public function itEqualsAnotherIdWithSameValue(): void
    {
        $value = CartItemId::generate()->toString();
        $id1 = CartItemId::fromString($value);
        $id2 = CartItemId::fromString($value);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function itDoesNotEqualDifferentId(): void
    {
        $id1 = CartItemId::generate();
        $id2 = CartItemId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itImplementsStringableMagicMethod(): void
    {
        $id = CartItemId::generate();

        self::assertSame($id->toString(), (string) $id);
    }

    #[Test]
    public function itThrowsForZeroString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CartItemId cannot be empty');

        CartItemId::fromString('0');
    }
}
