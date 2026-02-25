<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Command;

use App\Cart\Application\Command\AddItemToCart;
use App\Cart\Application\Command\AddItemToCartHandler;
use App\Cart\Application\Service\CartPriceCalculator;
use App\Cart\Application\Service\StockValidator;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Exception\InsufficientStockException;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class AddItemToCartHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const PRODUCT_ID = '550e8400-e29b-41d4-a716-446655440001';

    private CartRepositoryInterface $cartRepository;
    private CartPriceCalculator $priceCalculator;
    private StockValidator $stockValidator;
    private AddItemToCartHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->priceCalculator = $this->createMock(CartPriceCalculator::class);
        $this->stockValidator = $this->createMock(StockValidator::class);

        $this->handler = new AddItemToCartHandler(
            $this->cartRepository,
            $this->priceCalculator,
            $this->stockValidator,
        );
    }

    public function testItAddsItemWithExplicitPrice(): void
    {
        $cart = $this->createActiveCart();

        $command = new AddItemToCart(
            cartId: $cart->id()->toString(),
            productId: self::PRODUCT_ID,
            variantId: null,
            quantity: 2,
            unitPriceAmount: 1999.0,
            unitPriceCurrency: 'EUR',
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn($cart);
        $this->stockValidator->expects($this->once())->method('validateStockAvailability');
        $this->priceCalculator->expects($this->never())->method('calculateItemPrice');
        $this->cartRepository->expects($this->once())->method('save');

        ($this->handler)($command);

        $this->assertCount(1, $cart->items());
        $this->assertSame(1999, $cart->items()[0]->unitPrice()->getAmount());
        $this->assertSame(2, $cart->items()[0]->quantity()->toInt());
    }

    public function testItAddsItemWithFetchedPrice(): void
    {
        $cart = $this->createActiveCart();
        $fetchedPrice = Money::fromScalars(2499, 'EUR');

        $command = new AddItemToCart(
            cartId: $cart->id()->toString(),
            productId: self::PRODUCT_ID,
            variantId: null,
            quantity: 1,
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn($cart);
        $this->stockValidator->expects($this->once())->method('validateStockAvailability');
        $this->priceCalculator->expects($this->once())->method('calculateItemPrice')->willReturn($fetchedPrice);
        $this->cartRepository->expects($this->once())->method('save');

        ($this->handler)($command);

        $this->assertCount(1, $cart->items());
        $this->assertSame(2499, $cart->items()[0]->unitPrice()->getAmount());
    }

    public function testItThrowsWhenCartNotFound(): void
    {
        $command = new AddItemToCart(
            cartId: CartId::generate()->toString(),
            productId: self::PRODUCT_ID,
            variantId: null,
            quantity: 1,
            unitPriceAmount: 999.0,
            unitPriceCurrency: 'EUR',
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn(null);
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(CartNotFoundException::class);

        ($this->handler)($command);
    }

    public function testItValidatesStockBeforeAdding(): void
    {
        $cart = $this->createActiveCart();

        $command = new AddItemToCart(
            cartId: $cart->id()->toString(),
            productId: self::PRODUCT_ID,
            variantId: 'variant-abc',
            quantity: 3,
            unitPriceAmount: 500.0,
            unitPriceCurrency: 'EUR',
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn($cart);
        $this->stockValidator
            ->expects($this->once())
            ->method('validateStockAvailability')
            ->with(
                $this->isInstanceOf(ProductId::class),
                'variant-abc',
                $this->isInstanceOf(TenantId::class),
                3,
            );
        $this->cartRepository->expects($this->once())->method('save');

        ($this->handler)($command);
    }

    public function testItThrowsOnInsufficientStock(): void
    {
        $cart = $this->createActiveCart();

        $command = new AddItemToCart(
            cartId: $cart->id()->toString(),
            productId: self::PRODUCT_ID,
            variantId: null,
            quantity: 100,
            unitPriceAmount: 999.0,
            unitPriceCurrency: 'EUR',
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn($cart);
        $this->stockValidator
            ->expects($this->once())
            ->method('validateStockAvailability')
            ->willThrowException(InsufficientStockException::forProduct(self::PRODUCT_ID, 100, 5));
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(InsufficientStockException::class);

        ($this->handler)($command);
    }

    private function createActiveCart(): Cart
    {
        return Cart::create(
            CartId::generate(),
            TenantId::fromString(self::TENANT_ID),
            null,
            SessionId::generate(),
        );
    }
}
