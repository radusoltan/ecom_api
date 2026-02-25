<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\Service;

use App\Cart\Application\Service\CartToOrderConverter;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\Quantity;
use App\Cart\Domain\Model\SessionId;
use App\Catalog\Domain\Model\ProductId;
use App\Order\Application\Command\PlaceOrderCommand;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class CartToOrderConverterTest extends TestCase
{
    private CartToOrderConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new CartToOrderConverter();
    }

    public function testConvertSuccess(): void
    {
        $cart = $this->createActiveCartWithItems();

        $command = $this->converter->convert(
            $cart,
            'customer@example.com',
            $this->validShippingAddress(),
            $this->validBillingAddress()
        );

        $this->assertInstanceOf(PlaceOrderCommand::class, $command);
        $this->assertSame('customer@example.com', $command->customerEmail);
        $this->assertSame($cart->tenantId()->toString(), $command->tenantId);
        $this->assertCount(1, $command->lines);
        $this->assertNull($command->couponCode);
    }

    public function testConvertMapsCartItemsToOrderLines(): void
    {
        $cart = Cart::create(CartId::generate(), TenantId::generate(), null, SessionId::generate());
        $productId1 = ProductId::generate();
        $productId2 = ProductId::generate();

        $cart->addItem($productId1, null, Quantity::fromInt(1), Money::fromScalars(1000, 'EUR'));
        $cart->addItem($productId2, null, Quantity::fromInt(3), Money::fromScalars(500, 'EUR'));

        $command = $this->converter->convert(
            $cart,
            'buyer@example.com',
            $this->validShippingAddress(),
            $this->validBillingAddress()
        );

        $this->assertCount(2, $command->lines);
        $this->assertSame($productId1->toString(), $command->lines[0]['productId']);
        $this->assertSame(1, $command->lines[0]['quantity']);
        $this->assertSame(1000, $command->lines[0]['unitPriceAmount']);
        $this->assertSame('EUR', $command->lines[0]['unitPriceCurrency']);
        $this->assertSame($productId2->toString(), $command->lines[1]['productId']);
        $this->assertSame(3, $command->lines[1]['quantity']);
    }

    public function testConvertWithCouponCode(): void
    {
        $cart = $this->createActiveCartWithItems();

        $command = $this->converter->convert(
            $cart,
            'customer@example.com',
            $this->validShippingAddress(),
            $this->validBillingAddress(),
            'SAVE20'
        );

        $this->assertSame('SAVE20', $command->couponCode);
    }

    public function testConvertSanitizesAddresses(): void
    {
        $cart = $this->createActiveCartWithItems();

        $command = $this->converter->convert(
            $cart,
            'customer@example.com',
            ['street' => '  123 Main St  ', 'city' => '  Amsterdam  ', 'state' => '  NH  ', 'postalCode' => '  1011 AB  ', 'country' => '  NL  '],
            ['street' => '  456 Billing St  ', 'city' => '  Rotterdam  ', 'state' => '  ZH  ', 'postalCode' => '  3012 AB  ', 'country' => '  NL  '],
        );

        $this->assertSame('123 Main St', $command->shippingAddress['street']);
        $this->assertSame('Amsterdam', $command->shippingAddress['city']);
        $this->assertSame('456 Billing St', $command->billingAddress['street']);
    }

    public function testConvertTrimsCustomerEmail(): void
    {
        $cart = $this->createActiveCartWithItems();

        $command = $this->converter->convert(
            $cart,
            '  customer@example.com  ',
            $this->validShippingAddress(),
            $this->validBillingAddress()
        );

        $this->assertSame('customer@example.com', $command->customerEmail);
    }

    public function testConvertThrowsWhenCartInactive(): void
    {
        $cart = $this->createActiveCartWithItems();
        $cart->markAsExpired();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert inactive cart to order');

        $this->converter->convert($cart, 'customer@example.com', $this->validShippingAddress(), $this->validBillingAddress());
    }

    public function testConvertThrowsWhenCartEmpty(): void
    {
        $cart = Cart::create(CartId::generate(), TenantId::generate(), null, SessionId::generate());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create order from empty cart');

        $this->converter->convert($cart, 'customer@example.com', $this->validShippingAddress(), $this->validBillingAddress());
    }

    public function testConvertThrowsWhenEmailBlank(): void
    {
        $cart = $this->createActiveCartWithItems();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer email is required for order placement');

        $this->converter->convert($cart, '', $this->validShippingAddress(), $this->validBillingAddress());
    }

    public function testConvertThrowsWhenShippingAddressMissingField(): void
    {
        $cart = $this->createActiveCartWithItems();
        $incomplete = $this->validShippingAddress();
        unset($incomplete['street']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipping address is missing required field: street');

        $this->converter->convert($cart, 'customer@example.com', $incomplete, $this->validBillingAddress());
    }

    public function testConvertThrowsWhenBillingAddressMissingField(): void
    {
        $cart = $this->createActiveCartWithItems();
        $incomplete = $this->validBillingAddress();
        unset($incomplete['city']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billing address is missing required field: city');

        $this->converter->convert($cart, 'customer@example.com', $this->validShippingAddress(), $incomplete);
    }

    private function createActiveCartWithItems(): Cart
    {
        $cart = Cart::create(CartId::generate(), TenantId::generate(), null, SessionId::generate());
        $cart->addItem(ProductId::generate(), null, Quantity::fromInt(2), Money::fromScalars(1999, 'EUR'));

        return $cart;
    }

    private function validShippingAddress(): array
    {
        return ['street' => '123 Main St', 'city' => 'Amsterdam', 'state' => 'NH', 'postalCode' => '1011 AB', 'country' => 'NL'];
    }

    private function validBillingAddress(): array
    {
        return ['street' => '456 Invoice Ave', 'city' => 'Rotterdam', 'state' => 'ZH', 'postalCode' => '3012 AB', 'country' => 'NL'];
    }
}
