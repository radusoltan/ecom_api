<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Domain\Model;

use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\CartItemId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CartId::class)]
#[CoversClass(CartItemId::class)]
#[CoversClass(Quantity::class)]
#[CoversClass(SessionId::class)]
final class CartValueObjectsTest extends TestCase
{
    #[Test]
    public function cartIdGenerate(): void
    {
        $id = CartId::generate();
        self::assertNotEmpty($id->toString());
        self::assertSame($id->toString(), (string) $id);
    }

    #[Test]
    public function cartIdFromString(): void
    {
        $id = CartId::generate();
        $reconstructed = CartId::fromString($id->toString());
        self::assertTrue($id->equals($reconstructed));
    }

    #[Test]
    public function cartIdRejectsInvalidUlid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CartId::fromString('not-a-ulid');
    }

    #[Test]
    public function cartIdRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CartId::fromString('');
    }

    #[Test]
    public function cartItemIdGenerate(): void
    {
        $id = CartItemId::generate();
        self::assertNotEmpty($id->toString());
    }

    #[Test]
    public function cartItemIdFromString(): void
    {
        $id = CartItemId::generate();
        $reconstructed = CartItemId::fromString($id->toString());
        self::assertTrue($id->equals($reconstructed));
    }

    #[Test]
    public function cartItemIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CartItemId::fromString('invalid');
    }

    #[Test]
    public function sessionIdGenerate(): void
    {
        $id = SessionId::generate();
        self::assertNotEmpty($id->toString());
        self::assertSame($id->toString(), (string) $id);
    }

    #[Test]
    public function sessionIdFromString(): void
    {
        $id = SessionId::generate();
        $reconstructed = SessionId::fromString($id->toString());
        self::assertTrue($id->equals($reconstructed));
    }

    #[Test]
    public function sessionIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SessionId::fromString('not-uuid');
    }

    #[Test]
    public function quantityFromInt(): void
    {
        $q = Quantity::fromInt(5);
        self::assertSame(5, $q->toInt());
    }

    #[Test]
    public function quantityBoundary(): void
    {
        self::assertSame(1, Quantity::fromInt(1)->toInt());
        self::assertSame(999, Quantity::fromInt(999)->toInt());
    }

    #[Test]
    public function quantityRejectsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Quantity::fromInt(0);
    }

    #[Test]
    public function quantityRejectsOverMax(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Quantity::fromInt(1000);
    }

    #[Test]
    public function quantityAdd(): void
    {
        $q = Quantity::fromInt(3)->add(Quantity::fromInt(4));
        self::assertSame(7, $q->toInt());
    }

    #[Test]
    public function quantitySubtract(): void
    {
        $q = Quantity::fromInt(5)->subtract(Quantity::fromInt(3));
        self::assertSame(2, $q->toInt());
    }

    #[Test]
    public function quantityEquals(): void
    {
        $a = Quantity::fromInt(5);
        $b = Quantity::fromInt(5);
        $c = Quantity::fromInt(3);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    public function quantityComparisons(): void
    {
        $big = Quantity::fromInt(10);
        $small = Quantity::fromInt(3);

        self::assertTrue($big->isGreaterThan($small));
        self::assertFalse($small->isGreaterThan($big));
        self::assertTrue($small->isLessThan($big));
        self::assertFalse($big->isLessThan($small));
    }

    #[Test]
    public function quantityRejectsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Quantity::fromInt(-1);
    }
}
