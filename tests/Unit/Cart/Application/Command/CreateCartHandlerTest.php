<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Command;

use App\Cart\Application\Command\CreateCart;
use App\Cart\Application\Command\CreateCartHandler;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CreateCartHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CartRepositoryInterface $cartRepository;
    private CreateCartHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->handler = new CreateCartHandler($this->cartRepository);
    }

    public function testItCreatesCartForCustomer(): void
    {
        $cartId = CartId::generate();
        $customerId = CustomerId::generate();
        $sessionId = SessionId::generate();

        $command = new CreateCart(
            cartId: $cartId->toString(),
            tenantId: self::TENANT_ID,
            customerId: $customerId->toString(),
            sessionId: $sessionId->toString(),
        );

        $this->cartRepository
            ->expects($this->once())
            ->method('findByCustomerId')
            ->willReturn(null);

        $this->cartRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Cart::class));

        ($this->handler)($command);
    }

    public function testItCreatesCartForGuest(): void
    {
        $sessionId = SessionId::generate();

        $command = new CreateCart(
            cartId: CartId::generate()->toString(),
            tenantId: self::TENANT_ID,
            customerId: null,
            sessionId: $sessionId->toString(),
        );

        $this->cartRepository
            ->expects($this->once())
            ->method('findBySessionId')
            ->willReturn(null);

        $this->cartRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Cart::class));

        ($this->handler)($command);
    }

    public function testItThrowsWhenBothCustomerAndSessionAreNull(): void
    {
        $command = new CreateCart(
            cartId: CartId::generate()->toString(),
            tenantId: self::TENANT_ID,
            customerId: null,
            sessionId: null,
        );

        $this->cartRepository
            ->expects($this->never())
            ->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Either customerId or sessionId must be provided');

        ($this->handler)($command);
    }

    public function testItDoesNotCreateDuplicateCartForCustomer(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $customerId = CustomerId::generate();
        $existingCart = Cart::create(CartId::generate(), $tenantId, $customerId, SessionId::generate());

        $command = new CreateCart(
            cartId: CartId::generate()->toString(),
            tenantId: self::TENANT_ID,
            customerId: $customerId->toString(),
            sessionId: null,
        );

        $this->cartRepository
            ->expects($this->once())
            ->method('findByCustomerId')
            ->willReturn($existingCart);

        $this->cartRepository
            ->expects($this->never())
            ->method('save');

        ($this->handler)($command);
    }

    public function testItDoesNotCreateDuplicateCartForSession(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $sessionId = SessionId::generate();
        $existingCart = Cart::create(CartId::generate(), $tenantId, null, $sessionId);

        $command = new CreateCart(
            cartId: CartId::generate()->toString(),
            tenantId: self::TENANT_ID,
            customerId: null,
            sessionId: $sessionId->toString(),
        );

        $this->cartRepository
            ->expects($this->once())
            ->method('findBySessionId')
            ->willReturn($existingCart);

        $this->cartRepository
            ->expects($this->never())
            ->method('save');

        ($this->handler)($command);
    }
}
