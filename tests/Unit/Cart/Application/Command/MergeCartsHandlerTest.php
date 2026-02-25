<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Command;

use App\Cart\Application\Command\MergeCarts;
use App\Cart\Application\Command\MergeCartsHandler;
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

final class MergeCartsHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CartRepositoryInterface $cartRepository;
    private MergeCartsHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->handler = new MergeCartsHandler($this->cartRepository);
    }

    public function testItMergesSourceIntoTarget(): void
    {
        $sourceCart = $this->createCartWithOneItem(
            ProductId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            3
        );
        $targetCart = $this->createEmptyCart();

        $command = new MergeCarts(
            sourceCartId: $sourceCart->id()->toString(),
            targetCartId: $targetCart->id()->toString(),
            deleteSourceAfterMerge: false,
        );

        $this->cartRepository
            ->method('findById')
            ->willReturnCallback(function (CartId $id) use ($sourceCart, $targetCart): ?Cart {
                if ($id->equals($sourceCart->id())) {
                    return $sourceCart;
                }
                if ($id->equals($targetCart->id())) {
                    return $targetCart;
                }

                return null;
            });

        $this->cartRepository->expects($this->atLeast(1))->method('save');

        ($this->handler)($command);

        $this->assertCount(1, $targetCart->items());
        $this->assertSame(3, $targetCart->items()[0]->quantity()->toInt());
    }

    public function testItDeletesSourceAfterMerge(): void
    {
        $sourceCart = $this->createCartWithOneItem(
            ProductId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            2
        );
        $targetCart = $this->createEmptyCart();

        $command = new MergeCarts(
            sourceCartId: $sourceCart->id()->toString(),
            targetCartId: $targetCart->id()->toString(),
            deleteSourceAfterMerge: true,
        );

        $this->cartRepository
            ->method('findById')
            ->willReturnCallback(function (CartId $id) use ($sourceCart, $targetCart): ?Cart {
                if ($id->equals($sourceCart->id())) {
                    return $sourceCart;
                }
                if ($id->equals($targetCart->id())) {
                    return $targetCart;
                }

                return null;
            });

        $this->cartRepository->expects($this->once())->method('save')->with($targetCart);
        $this->cartRepository->expects($this->once())->method('remove')->with($sourceCart);

        ($this->handler)($command);
    }

    public function testItClearsSourceWhenNotDeleting(): void
    {
        $sourceCart = $this->createCartWithOneItem(
            ProductId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            2
        );
        $targetCart = $this->createEmptyCart();

        $command = new MergeCarts(
            sourceCartId: $sourceCart->id()->toString(),
            targetCartId: $targetCart->id()->toString(),
            deleteSourceAfterMerge: false,
        );

        $this->cartRepository
            ->method('findById')
            ->willReturnCallback(function (CartId $id) use ($sourceCart, $targetCart): ?Cart {
                if ($id->equals($sourceCart->id())) {
                    return $sourceCart;
                }
                if ($id->equals($targetCart->id())) {
                    return $targetCart;
                }

                return null;
            });

        $this->cartRepository->expects($this->never())->method('remove');

        ($this->handler)($command);

        $this->assertTrue($sourceCart->isEmpty());
    }

    public function testItThrowsWhenSourceCartNotFound(): void
    {
        $command = new MergeCarts(
            sourceCartId: CartId::generate()->toString(),
            targetCartId: CartId::generate()->toString(),
        );

        $this->cartRepository->method('findById')->willReturn(null);
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(CartNotFoundException::class);

        ($this->handler)($command);
    }

    public function testItThrowsWhenTargetCartNotFound(): void
    {
        $sourceCart = $this->createCartWithOneItem(
            ProductId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            1
        );

        $command = new MergeCarts(
            sourceCartId: $sourceCart->id()->toString(),
            targetCartId: CartId::generate()->toString(),
        );

        $this->cartRepository
            ->method('findById')
            ->willReturnCallback(function (CartId $id) use ($sourceCart): ?Cart {
                if ($id->equals($sourceCart->id())) {
                    return $sourceCart;
                }

                return null;
            });

        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(CartNotFoundException::class);

        ($this->handler)($command);
    }

    public function testItReturnsEarlyWhenMergingSameCart(): void
    {
        $cartId = CartId::generate()->toString();

        $command = new MergeCarts(
            sourceCartId: $cartId,
            targetCartId: $cartId,
        );

        $this->cartRepository->expects($this->never())->method('findById');
        $this->cartRepository->expects($this->never())->method('save');

        ($this->handler)($command);
    }

    public function testItThrowsWhenSourceCartIsInactive(): void
    {
        $sourceCart = $this->createCartWithOneItem(
            ProductId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            1
        );
        $sourceCart->markAsConverted();

        $targetCart = $this->createEmptyCart();

        $command = new MergeCarts(
            sourceCartId: $sourceCart->id()->toString(),
            targetCartId: $targetCart->id()->toString(),
        );

        $this->cartRepository
            ->method('findById')
            ->willReturnCallback(function (CartId $id) use ($sourceCart, $targetCart): ?Cart {
                if ($id->equals($sourceCart->id())) {
                    return $sourceCart;
                }
                if ($id->equals($targetCart->id())) {
                    return $targetCart;
                }

                return null;
            });

        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot merge from inactive source cart');

        ($this->handler)($command);
    }

    private function createEmptyCart(): Cart
    {
        return Cart::create(
            CartId::generate(),
            TenantId::fromString(self::TENANT_ID),
            null,
            SessionId::generate(),
        );
    }

    private function createCartWithOneItem(ProductId $productId, int $quantity): Cart
    {
        $cart = $this->createEmptyCart();
        $cart->addItem($productId, null, Quantity::fromInt($quantity), Money::fromScalars(1999, 'EUR'));

        return $cart;
    }
}
