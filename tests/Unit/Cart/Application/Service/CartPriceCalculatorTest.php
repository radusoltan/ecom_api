<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Service;

use App\Cart\Application\Service\CartPriceCalculator;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CartPriceCalculatorTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private CartPriceCalculator $calculator;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->calculator = new CartPriceCalculator($this->productRepository);
    }

    public function testCalculateItemPriceReturnsProductPrice(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $expectedPrice = Money::fromScalars(1999, 'EUR');

        $product = $this->createMock(Product::class);
        $product->method('price')->willReturn($expectedPrice);

        $this->productRepository->method('findById')->willReturn($product);

        $result = $this->calculator->calculateItemPrice($productId, null, $tenantId);

        $this->assertSame(1999, $result->getAmount());
        $this->assertSame('EUR', $result->getCurrency()->getCurrencyCode());
    }

    public function testCalculateItemPriceThrowsWhenProductNotFound(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();

        $this->productRepository->method('findById')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Product with ID "' . $productId->toString() . '" not found');

        $this->calculator->calculateItemPrice($productId, null, $tenantId);
    }

    public function testCalculateItemPriceWithVariantIdReturnsBasePrice(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $price = Money::fromScalars(3000, 'EUR');

        $product = $this->createMock(Product::class);
        $product->method('price')->willReturn($price);

        $this->productRepository->method('findById')->willReturn($product);

        $result = $this->calculator->calculateItemPrice($productId, 'variant-abc', $tenantId);

        $this->assertSame(3000, $result->getAmount());
    }

    public function testCalculateItemPriceWithCustomerIdReturnsBasePrice(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $customerId = CustomerId::generate();
        $price = Money::fromScalars(2500, 'EUR');

        $product = $this->createMock(Product::class);
        $product->method('price')->willReturn($price);

        $this->productRepository->method('findById')->willReturn($product);

        $result = $this->calculator->calculateItemPrice($productId, null, $tenantId, $customerId);

        $this->assertSame(2500, $result->getAmount());
    }

    public function testIsPriceStillCurrentReturnsTrueWhenSamePrice(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $cartItemPrice = Money::fromScalars(1999, 'EUR');

        $product = $this->createMock(Product::class);
        $product->method('price')->willReturn(Money::fromScalars(1999, 'EUR'));

        $this->productRepository->method('findById')->willReturn($product);

        $result = $this->calculator->isPriceStillCurrent($productId, null, $tenantId, $cartItemPrice);

        $this->assertTrue($result);
    }

    public function testIsPriceStillCurrentReturnsFalseWhenDifferentPrice(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $cartItemPrice = Money::fromScalars(1999, 'EUR');

        $product = $this->createMock(Product::class);
        $product->method('price')->willReturn(Money::fromScalars(2499, 'EUR'));

        $this->productRepository->method('findById')->willReturn($product);

        $result = $this->calculator->isPriceStillCurrent($productId, null, $tenantId, $cartItemPrice);

        $this->assertFalse($result);
    }

    public function testIsPriceStillCurrentReturnsFalseWhenProductNotFound(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $cartItemPrice = Money::fromScalars(1999, 'EUR');

        $this->productRepository->method('findById')->willReturn(null);

        $result = $this->calculator->isPriceStillCurrent($productId, null, $tenantId, $cartItemPrice);

        $this->assertFalse($result);
    }

    public function testGetPriceChangeDetailsWhenPriceUnchanged(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $oldPrice = Money::fromScalars(1999, 'EUR');

        $product = $this->createMock(Product::class);
        $product->method('price')->willReturn(Money::fromScalars(1999, 'EUR'));

        $this->productRepository->method('findById')->willReturn($product);

        $details = $this->calculator->getPriceChangeDetails($productId, null, $tenantId, $oldPrice);

        $this->assertFalse($details['changed']);
        $this->assertSame(1999, $details['oldPrice']->getAmount());
        $this->assertSame(1999, $details['newPrice']->getAmount());
        $this->assertNull($details['difference']);
    }

    public function testGetPriceChangeDetailsWhenPriceChanged(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $oldPrice = Money::fromScalars(1999, 'EUR');

        $product = $this->createMock(Product::class);
        $product->method('price')->willReturn(Money::fromScalars(2499, 'EUR'));

        $this->productRepository->method('findById')->willReturn($product);

        $details = $this->calculator->getPriceChangeDetails($productId, null, $tenantId, $oldPrice);

        $this->assertTrue($details['changed']);
        $this->assertSame(1999, $details['oldPrice']->getAmount());
        $this->assertSame(2499, $details['newPrice']->getAmount());
        $this->assertSame(500, $details['difference']->getAmount());
    }

    public function testGetPriceChangeDetailsWhenProductNotFound(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $oldPrice = Money::fromScalars(1999, 'EUR');

        $this->productRepository->method('findById')->willReturn(null);

        $details = $this->calculator->getPriceChangeDetails($productId, null, $tenantId, $oldPrice);

        $this->assertTrue($details['changed']);
        $this->assertSame(1999, $details['oldPrice']->getAmount());
        $this->assertNull($details['newPrice']);
        $this->assertNull($details['difference']);
    }
}
