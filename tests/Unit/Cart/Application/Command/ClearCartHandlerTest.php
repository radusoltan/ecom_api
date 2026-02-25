<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Command;

use App\Cart\Application\Command\ClearCart;
use App\Cart\Application\Command\ClearCartHandler;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ClearCartHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CartRepositoryInterface $cartRepository;
    private ClearCartHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->handler = new ClearCartHandler($this->cartRepository);
    }

    public function testItClearsCart(): void
    {
        $cart = $this->createCartWithItems();

        $command = new ClearCart(cartId: $cart->id()->toString());

        $this->cartRepository->expects($this->once())->method('findById')->willReturn($cart);
        $this->cartRepository->expects($this->once())->method('save');

        ($this->handler)($command);

        $this->assertCount(0, $cart->items());
        $this->assertTrue($cart->isEmpty());
    }

    public function testItThrowsWhenCartNotFound(): void
    {
        $command = new ClearCart(cartId: CartId::generate()->toString());

        $this->cartRepository->expects($this->once())->method('findById')->willReturn(null);
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(CartNotFoundException::class);

        ($this->handler)($command);
    }

    private function createCartWithItems(): Cart
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

        $cart->addItem(
            ProductId::fromString('550e8400-e29b-41d4-a716-446655440002'),
            null,
            Quantity::fromInt(1),
            Money::fromScalars(4999, 'EUR'),
        );

        return $cart;
    }
}
