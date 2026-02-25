<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Service;

use App\Cart\Application\Service\CartAbandonmentService;
use App\Cart\Domain\Event\CartAbandoned;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class CartAbandonmentServiceTest extends TestCase
{
    private CartRepositoryInterface $cartRepository;
    private CustomerRepositoryInterface $customerRepository;
    private EventDispatcherInterface $eventDispatcher;
    private CartAbandonmentService $service;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->service = new CartAbandonmentService(
            $this->cartRepository,
            $this->customerRepository,
            $this->eventDispatcher,
            new NullLogger(),
        );
    }

    public function testProcessAbandonedCartsReturnsZeroWhenNoneFound(): void
    {
        $this->cartRepository
            ->method('findAbandonedCartsForEmail')
            ->willReturn([]);

        $stats = $this->service->processAbandonedCarts();

        $this->assertSame(0, $stats['processed']);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame(0, $stats['skipped']);
        $this->assertSame(0, $stats['errors']);
    }

    public function testProcessAbandonedCartsDispatchesEventForValidCart(): void
    {
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();
        $cartId = CartId::generate();

        $cart = Cart::create($cartId, $tenantId, $customerId, SessionId::generate());
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(1999, 'EUR'));

        $customer = $this->createMock(Customer::class);
        $customer->method('email')->willReturn(Email::fromString('test@example.com'));

        $this->cartRepository
            ->method('findAbandonedCartsForEmail')
            ->willReturn([$cartId]);

        $this->cartRepository
            ->method('findById')
            ->with($cartId)
            ->willReturn($cart);

        $this->customerRepository
            ->method('findById')
            ->with($customerId, $tenantId)
            ->willReturn($customer);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CartAbandoned::class));

        $this->cartRepository
            ->expects($this->once())
            ->method('markAbandonmentEmailSent')
            ->with($cartId);

        $stats = $this->service->processAbandonedCarts();

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(1, $stats['sent']);
        $this->assertSame(0, $stats['skipped']);
        $this->assertSame(0, $stats['errors']);
    }

    public function testProcessAbandonedCartsSkipsGuestCart(): void
    {
        $cartId = CartId::generate();
        $tenantId = TenantId::generate();

        // Guest cart - no customerId
        $cart = Cart::create($cartId, $tenantId, null, SessionId::generate());
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(500, 'EUR'));

        $this->cartRepository
            ->method('findAbandonedCartsForEmail')
            ->willReturn([$cartId]);

        $this->cartRepository
            ->method('findById')
            ->willReturn($cart);

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $stats = $this->service->processAbandonedCarts();

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function testProcessAbandonedCartsSkipsWhenCartNotFound(): void
    {
        $cartId = CartId::generate();

        $this->cartRepository
            ->method('findAbandonedCartsForEmail')
            ->willReturn([$cartId]);

        $this->cartRepository
            ->method('findById')
            ->willReturn(null);

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $stats = $this->service->processAbandonedCarts();

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function testProcessAbandonedCartsSkipsWhenCustomerNotFound(): void
    {
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();
        $cartId = CartId::generate();

        $cart = Cart::create($cartId, $tenantId, $customerId, SessionId::generate());
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(500, 'EUR'));

        $this->cartRepository
            ->method('findAbandonedCartsForEmail')
            ->willReturn([$cartId]);

        $this->cartRepository
            ->method('findById')
            ->willReturn($cart);

        $this->customerRepository
            ->method('findById')
            ->willReturn(null);

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $stats = $this->service->processAbandonedCarts();

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function testProcessAbandonedCartsSkipsEmptyCart(): void
    {
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();
        $cartId = CartId::generate();

        // Cart with customer but no items
        $cart = Cart::create($cartId, $tenantId, $customerId, SessionId::generate());

        $customer = $this->createMock(Customer::class);
        $customer->method('email')->willReturn(Email::fromString('test@example.com'));

        $this->cartRepository
            ->method('findAbandonedCartsForEmail')
            ->willReturn([$cartId]);

        $this->cartRepository
            ->method('findById')
            ->willReturn($cart);

        $this->customerRepository
            ->method('findById')
            ->willReturn($customer);

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $stats = $this->service->processAbandonedCarts();

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame(1, $stats['skipped']);
    }

    public function testProcessAbandonedCartsCountsErrorsWhenProcessingFails(): void
    {
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();
        $cartId = CartId::generate();

        $cart = Cart::create($cartId, $tenantId, $customerId, SessionId::generate());
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(500, 'EUR'));

        $customer = $this->createMock(Customer::class);
        $customer->method('email')->willReturn(Email::fromString('test@example.com'));

        $this->cartRepository
            ->method('findAbandonedCartsForEmail')
            ->willReturn([$cartId]);

        $this->cartRepository
            ->method('findById')
            ->willReturn($cart);

        $this->customerRepository
            ->method('findById')
            ->willReturn($customer);

        $this->eventDispatcher
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('Dispatch failed'));

        $stats = $this->service->processAbandonedCarts();

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(0, $stats['sent']);
        $this->assertSame(0, $stats['skipped']);
        $this->assertSame(1, $stats['errors']);
    }

    public function testProcessAbandonedCartsHandlesMultipleCarts(): void
    {
        $tenantId = TenantId::generate();

        // Cart 1: valid customer cart with items
        $customerId1 = CustomerId::generate();
        $cartId1 = CartId::generate();
        $cart1 = Cart::create($cartId1, $tenantId, $customerId1, SessionId::generate());
        $cart1->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(1000, 'EUR'));

        // Cart 2: guest cart (no customer) -> skipped
        $cartId2 = CartId::generate();
        $cart2 = Cart::create($cartId2, $tenantId, null, SessionId::generate());
        $cart2->addItem(ProductId::generate(), null, Quantity::fromInt(1), Money::fromScalars(500, 'EUR'));

        $customer1 = $this->createMock(Customer::class);
        $customer1->method('email')->willReturn(Email::fromString('user1@example.com'));

        $this->cartRepository
            ->method('findAbandonedCartsForEmail')
            ->willReturn([$cartId1, $cartId2]);

        $this->cartRepository
            ->method('findById')
            ->willReturnCallback(fn (CartId $id) => match ($id->toString()) {
                $cartId1->toString() => $cart1,
                $cartId2->toString() => $cart2,
                default => null,
            });

        $this->customerRepository
            ->method('findById')
            ->willReturn($customer1);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $stats = $this->service->processAbandonedCarts();

        $this->assertSame(2, $stats['processed']);
        $this->assertSame(1, $stats['sent']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertSame(0, $stats['errors']);
    }
}
