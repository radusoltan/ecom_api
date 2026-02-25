<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Command;

use App\Cart\Application\Command\AssignCartToCustomer;
use App\Cart\Application\Command\AssignCartToCustomerHandler;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class AssignCartToCustomerHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CartRepositoryInterface $cartRepository;
    private AssignCartToCustomerHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->handler = new AssignCartToCustomerHandler($this->cartRepository);
    }

    public function testItAssignsCartToCustomer(): void
    {
        $cart = $this->createActiveCart();
        $customerId = CustomerId::generate();

        $command = new AssignCartToCustomer(
            cartId: $cart->id()->toString(),
            customerId: $customerId->toString(),
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn($cart);
        $this->cartRepository->expects($this->once())->method('save');

        ($this->handler)($command);

        $this->assertNotNull($cart->customerId());
        $this->assertTrue($cart->customerId()->equals($customerId));
    }

    public function testItThrowsWhenCartNotFound(): void
    {
        $command = new AssignCartToCustomer(
            cartId: CartId::generate()->toString(),
            customerId: CustomerId::generate()->toString(),
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn(null);
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(CartNotFoundException::class);

        ($this->handler)($command);
    }

    public function testItThrowsWhenCartIsInactive(): void
    {
        $cart = $this->createActiveCart();
        $cart->markAsExpired();

        $command = new AssignCartToCustomer(
            cartId: $cart->id()->toString(),
            customerId: CustomerId::generate()->toString(),
        );

        $this->cartRepository->expects($this->once())->method('findById')->willReturn($cart);
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot assign inactive cart to customer');

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
