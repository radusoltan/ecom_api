<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Command;

use App\Cart\Application\Command\RemoveItemFromCart;
use App\Cart\Application\Command\RemoveItemFromCartHandler;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\CartItemId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class RemoveItemFromCartHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CartRepositoryInterface $cartRepository;
    private RemoveItemFromCartHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->handler = new RemoveItemFromCartHandler($this->cartRepository);
    }

    public function testItRemovesItemFromCart(): void
    {
        $cart = $this->createCartWithOneItem();
        $itemId = $cart->items()[0]->id()->toString();

        $command = new RemoveItemFromCart(
            cartId: $cart->id()->toString(),
            cartItemId: $itemId,
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn($cart);
        $this->cartRepository->expects($this->once())->method('save');

        ($this->handler)($command);

        $this->assertCount(0, $cart->items());
    }

    public function testItThrowsWhenCartNotFound(): void
    {
        $command = new RemoveItemFromCart(
            cartId: CartId::generate()->toString(),
            cartItemId: CartItemId::generate()->toString(),
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn(null);
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(CartNotFoundException::class);

        ($this->handler)($command);
    }

    private function createCartWithOneItem(): Cart
    {
        $cart = Cart::create(
            CartId::generate(),
            TenantId::fromString(self::TENANT_ID),
            null,
            SessionId::generate(),
        );

        $cart->addItem(
            ProductId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            null,
            Quantity::fromInt(2),
            Money::fromScalars(1999, 'EUR'),
        );

        return $cart;
    }
}
