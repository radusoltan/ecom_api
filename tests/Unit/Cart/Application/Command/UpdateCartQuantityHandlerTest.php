<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Command;

use App\Cart\Application\Command\UpdateCartQuantity;
use App\Cart\Application\Command\UpdateCartQuantityHandler;
use App\Cart\Application\Service\StockValidator;
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

final class UpdateCartQuantityHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CartRepositoryInterface $cartRepository;
    private StockValidator $stockValidator;
    private UpdateCartQuantityHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->stockValidator = $this->createMock(StockValidator::class);

        $this->handler = new UpdateCartQuantityHandler(
            $this->cartRepository,
            $this->stockValidator,
        );
    }

    public function testItUpdatesQuantity(): void
    {
        $cart = $this->createCartWithOneItem();
        $item = $cart->items()[0];

        $command = new UpdateCartQuantity(
            cartId: $cart->id()->toString(),
            cartItemId: $item->id()->toString(),
            newQuantity: 5,
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn($cart);
        $this->stockValidator->expects($this->once())->method('validateStockAvailability');
        $this->cartRepository->expects($this->once())->method('save');

        ($this->handler)($command);

        $this->assertSame(5, $cart->items()[0]->quantity()->toInt());
    }

    public function testItThrowsWhenCartNotFound(): void
    {
        $command = new UpdateCartQuantity(
            cartId: CartId::generate()->toString(),
            cartItemId: CartItemId::generate()->toString(),
            newQuantity: 3,
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn(null);
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(CartNotFoundException::class);

        ($this->handler)($command);
    }

    public function testItValidatesStockBeforeUpdate(): void
    {
        $cart = $this->createCartWithOneItem();
        $item = $cart->items()[0];

        $command = new UpdateCartQuantity(
            cartId: $cart->id()->toString(),
            cartItemId: $item->id()->toString(),
            newQuantity: 10,
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn($cart);
        $this->stockValidator
            ->expects($this->once())
            ->method('validateStockAvailability')
            ->with(
                $this->isInstanceOf(ProductId::class),
                $item->variantId(),
                $this->isInstanceOf(TenantId::class),
                10,
            );
        $this->cartRepository->expects($this->once())->method('save');

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
