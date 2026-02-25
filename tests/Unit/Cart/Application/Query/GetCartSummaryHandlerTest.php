<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Query;

use App\Cart\Application\DTO\CartSummaryDTO;
use App\Cart\Application\Query\GetCartSummary;
use App\Cart\Application\Query\GetCartSummaryHandler;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetCartSummaryHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CartRepositoryInterface $cartRepository;
    private GetCartSummaryHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->handler = new GetCartSummaryHandler($this->cartRepository);
    }

    public function testItReturnsCartSummaryDTO(): void
    {
        $cart = $this->createCartWithItems();

        $this->cartRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->isInstanceOf(CartId::class))
            ->willReturn($cart);

        $query = new GetCartSummary(cartId: $cart->id()->toString());

        $result = ($this->handler)($query);

        $this->assertInstanceOf(CartSummaryDTO::class, $result);
        $this->assertSame($cart->id()->toString(), $result->id);
        $this->assertSame('active', $result->status);
        $this->assertSame(3, $result->itemCount);
        $this->assertSame(8997, $result->totalAmount);
        $this->assertSame('EUR', $result->totalCurrency);
    }

    public function testItReturnsNullWhenCartNotFound(): void
    {
        $this->cartRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $query = new GetCartSummary(cartId: CartId::generate()->toString());

        $result = ($this->handler)($query);

        $this->assertNull($result);
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
