<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Domain\Exception;

use App\Cart\Domain\Exception\CartItemNotFoundException;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Exception\InsufficientStockException;
use App\Cart\Domain\Exception\InvalidQuantityException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CartItemNotFoundException::class)]
#[CoversClass(CartNotFoundException::class)]
#[CoversClass(InsufficientStockException::class)]
#[CoversClass(InvalidQuantityException::class)]
final class CartExceptionsTest extends TestCase
{
    // -------------------------------------------------------
    // CartNotFoundException
    // -------------------------------------------------------

    #[Test]
    public function cartNotFoundExceptionExtendsRuntimeException(): void
    {
        $exception = new CartNotFoundException('Cart not found');

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function cartNotFoundExceptionWithIdContainsCartId(): void
    {
        $cartId = 'abc-123-cart';

        $exception = CartNotFoundException::withId($cartId);

        self::assertStringContainsString($cartId, $exception->getMessage());
    }

    #[Test]
    public function cartNotFoundExceptionWithIdReturnsCorrectInstance(): void
    {
        $exception = CartNotFoundException::withId('some-cart-id');

        self::assertInstanceOf(CartNotFoundException::class, $exception);
    }

    #[Test]
    public function cartNotFoundExceptionWithIdFormatsMessageCorrectly(): void
    {
        $cartId = '01JNQ5FXYZ123456789ABCDEFG';

        $exception = CartNotFoundException::withId($cartId);

        self::assertSame(
            sprintf('Cart with ID "%s" not found', $cartId),
            $exception->getMessage()
        );
    }

    #[Test]
    public function cartNotFoundExceptionCanBeConstructedDirectly(): void
    {
        $message = 'Custom cart not found message';

        $exception = new CartNotFoundException($message);

        self::assertSame($message, $exception->getMessage());
    }

    #[Test]
    public function cartNotFoundExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(CartNotFoundException::class);
        $this->expectExceptionMessage('Cart with ID "missing-id" not found');

        throw CartNotFoundException::withId('missing-id');
    }

    // -------------------------------------------------------
    // CartItemNotFoundException
    // -------------------------------------------------------

    #[Test]
    public function cartItemNotFoundExceptionExtendsRuntimeException(): void
    {
        $exception = new CartItemNotFoundException('Item not found');

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function cartItemNotFoundExceptionCanBeConstructedWithMessage(): void
    {
        $message = 'Cart item product-xyz not found in cart';

        $exception = new CartItemNotFoundException($message);

        self::assertSame($message, $exception->getMessage());
    }

    #[Test]
    public function cartItemNotFoundExceptionCanBeConstructedWithNoArgs(): void
    {
        $exception = new CartItemNotFoundException();

        self::assertInstanceOf(CartItemNotFoundException::class, $exception);
    }

    #[Test]
    public function cartItemNotFoundExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(CartItemNotFoundException::class);

        throw new CartItemNotFoundException('Item missing');
    }

    #[Test]
    public function cartItemNotFoundExceptionIsDistinctFromCartNotFoundException(): void
    {
        $itemException = new CartItemNotFoundException();
        $cartException = new CartNotFoundException();

        self::assertNotSame(get_class($itemException), get_class($cartException));
        self::assertNotInstanceOf(CartNotFoundException::class, $itemException);
        self::assertNotInstanceOf(CartItemNotFoundException::class, $cartException);
    }

    // -------------------------------------------------------
    // InvalidQuantityException
    // -------------------------------------------------------

    #[Test]
    public function invalidQuantityExceptionExtendsInvalidArgumentException(): void
    {
        $exception = new InvalidQuantityException('Invalid quantity');

        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    #[Test]
    public function invalidQuantityExceptionCanBeConstructedWithMessage(): void
    {
        $message = 'Quantity must be greater than zero';

        $exception = new InvalidQuantityException($message);

        self::assertSame($message, $exception->getMessage());
    }

    #[Test]
    public function invalidQuantityExceptionCanBeConstructedWithNoArgs(): void
    {
        $exception = new InvalidQuantityException();

        self::assertInstanceOf(InvalidQuantityException::class, $exception);
    }

    #[Test]
    public function invalidQuantityExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(InvalidQuantityException::class);
        $this->expectExceptionMessage('Quantity -1 is not allowed');

        throw new InvalidQuantityException('Quantity -1 is not allowed');
    }

    #[Test]
    public function invalidQuantityExceptionIsNotSubclassOfRuntimeException(): void
    {
        // InvalidQuantityException extends \InvalidArgumentException, NOT RuntimeException
        $exception = new InvalidQuantityException();

        self::assertNotInstanceOf(\RuntimeException::class, $exception);
    }

    // -------------------------------------------------------
    // InsufficientStockException
    // -------------------------------------------------------

    #[Test]
    public function insufficientStockExceptionExtendsRuntimeException(): void
    {
        $exception = new InsufficientStockException('Not enough stock');

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    #[Test]
    public function insufficientStockExceptionForProductContainsProductId(): void
    {
        $productId = 'prod-abc-123';

        $exception = InsufficientStockException::forProduct($productId, 5, 2);

        self::assertStringContainsString($productId, $exception->getMessage());
    }

    #[Test]
    public function insufficientStockExceptionForProductContainsRequestedAndAvailable(): void
    {
        $exception = InsufficientStockException::forProduct('prod-xyz', 10, 3);

        self::assertStringContainsString('10', $exception->getMessage());
        self::assertStringContainsString('3', $exception->getMessage());
    }

    #[Test]
    public function insufficientStockExceptionForProductReturnsCorrectInstance(): void
    {
        $exception = InsufficientStockException::forProduct('any-product', 1, 0);

        self::assertInstanceOf(InsufficientStockException::class, $exception);
    }

    #[Test]
    public function insufficientStockExceptionForProductFormatsMessageCorrectly(): void
    {
        $productId = 'product-001';
        $requested = 7;
        $available = 2;

        $exception = InsufficientStockException::forProduct($productId, $requested, $available);

        self::assertSame(
            sprintf(
                'Insufficient stock for product "%s". Requested: %d, Available: %d',
                $productId,
                $requested,
                $available
            ),
            $exception->getMessage()
        );
    }

    #[Test]
    public function insufficientStockExceptionForProductWithZeroAvailable(): void
    {
        $exception = InsufficientStockException::forProduct('out-of-stock-product', 1, 0);

        self::assertStringContainsString('0', $exception->getMessage());
    }

    #[Test]
    public function insufficientStockExceptionCanBeThrownAndCaught(): void
    {
        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('Requested: 3, Available: 1');

        throw InsufficientStockException::forProduct('prod-yyy', 3, 1);
    }

    // -------------------------------------------------------
    // Exception hierarchy
    // -------------------------------------------------------

    #[Test]
    public function allRuntimeExceptionsAreThrowable(): void
    {
        $exceptions = [
            new CartNotFoundException(),
            new CartItemNotFoundException(),
            new InsufficientStockException(),
        ];

        foreach ($exceptions as $exception) {
            self::assertInstanceOf(\Throwable::class, $exception);
        }
    }

    #[Test]
    public function invalidQuantityExceptionIsThrowable(): void
    {
        $exception = new InvalidQuantityException();

        self::assertInstanceOf(\Throwable::class, $exception);
    }
}
