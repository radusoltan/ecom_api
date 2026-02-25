<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Query;

use App\Cart\Application\DTO\CartDTO;
use App\Cart\Application\Query\GetCart;
use App\Cart\Application\Query\GetCartHandler;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetCartHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CartRepositoryInterface $cartRepository;
    private GetCartHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->handler = new GetCartHandler($this->cartRepository);
    }

    public function testItReturnsCartDTO(): void
    {
        $cart = Cart::create(
            CartId::generate(),
            TenantId::fromString(self::TENANT_ID),
            null,
            SessionId::generate(),
        );

        $this->cartRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->isInstanceOf(CartId::class))
            ->willReturn($cart);

        $query = new GetCart(cartId: $cart->id()->toString());

        $result = ($this->handler)($query);

        $this->assertInstanceOf(CartDTO::class, $result);
        $this->assertSame($cart->id()->toString(), $result->id);
        $this->assertSame(self::TENANT_ID, $result->tenantId);
        $this->assertSame('active', $result->status);
        $this->assertSame(0, $result->itemCount);
    }

    public function testItReturnsNullWhenCartNotFound(): void
    {
        $this->cartRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $query = new GetCart(cartId: CartId::generate()->toString());

        $result = ($this->handler)($query);

        $this->assertNull($result);
    }
}
