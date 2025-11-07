<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Domain\Model;

use App\Cart\Domain\Model\CartId;
use PHPUnit\Framework\TestCase;

final class CartIdTest extends TestCase
{
    public function testItGeneratesValidCartId(): void
    {
        $cartId = CartId::generate();
        $this->assertInstanceOf(CartId::class, $cartId);
    }

    public function testItCreatesCartIdFromValidString(): void
    {
        // Generate a real ULID first
        $generatedCartId = CartId::generate();
        $ulidString = $generatedCartId->toString();

        // Now recreate from string
        $cartId = CartId::fromString($ulidString);
        $this->assertEquals($ulidString, $cartId->toString());
    }

    public function testItThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CartId cannot be empty');
        CartId::fromString('');
    }

    public function testItThrowsExceptionForInvalidUlidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CartId format');
        CartId::fromString('invalid-ulid');
    }

    public function testItConvertsToString(): void
    {
        $cartId = CartId::generate();
        $string = $cartId->toString();
        $this->assertIsString($string);
        $this->assertNotEmpty($string);
    }

    public function testItComparesEqualCartIds(): void
    {
        $ulidString = CartId::generate()->toString();
        $cartId1 = CartId::fromString($ulidString);
        $cartId2 = CartId::fromString($ulidString);
        $this->assertTrue($cartId1->equals($cartId2));
    }

    public function testItComparesUnequalCartIds(): void
    {
        $cartId1 = CartId::generate();
        $cartId2 = CartId::generate();
        $this->assertFalse($cartId1->equals($cartId2));
    }

    public function testItImplementsStringable(): void
    {
        $cartId = CartId::generate();
        $string = (string) $cartId;
        $this->assertEquals($cartId->toString(), $string);
    }
}
