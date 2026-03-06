<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\ValueObject\OrderProductId;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class OrderWithPromotionsTest extends TestCase
{
    private TenantId $tenantId;
    private OrderId $orderId;
    private Address $address;
    private array $lines;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::generate();
        $this->orderId = OrderId::generate();
        $this->address = Address::create('123 Main St', 'New York', 'NY', '10001', 'US');

        $this->lines = [
            OrderLine::create(
                OrderProductId::generate(),
                'Product 1',
                2,
                Money::fromScalars(5000, 'USD') // $50.00
            ),
        ];
    }

    public function testPlaceOrderWithoutPromotions(): void
    {
        $order = Order::place(
            $this->orderId,
            $this->tenantId,
            'customer@example.com',
            $this->lines,
            $this->address,
            $this->address
        );

        $this->assertSame(10000, $order->subtotal()->getAmount()); // $100.00
        $this->assertSame(10000, $order->total()->getAmount()); // $100.00 (no discount)
        $this->assertNull($order->discountAmount());
        $this->assertSame([], $order->appliedPromotions());
        $this->assertNull($order->couponCode());
    }

    public function testPlaceOrderWithCouponCode(): void
    {
        $appliedPromotions = [
            [
                'promotionId' => 'promo_123',
                'name' => 'Summer Sale',
                'type' => 'coupon',
                'discountAmount' => 2000, // $20.00
                'priceAfterDiscount' => 8000, // $80.00
            ],
        ];

        $discountAmount = Money::fromScalars(2000, 'USD');

        $order = Order::place(
            $this->orderId,
            $this->tenantId,
            'customer@example.com',
            $this->lines,
            $this->address,
            $this->address,
            $appliedPromotions,
            $discountAmount,
            'SUMMER20'
        );

        $this->assertSame(10000, $order->subtotal()->getAmount()); // $100.00
        $this->assertSame(8000, $order->total()->getAmount()); // $80.00 (after $20 discount)
        $this->assertSame(2000, $order->discountAmount()->getAmount());
        $this->assertSame('SUMMER20', $order->couponCode());
        $this->assertCount(1, $order->appliedPromotions());
        $this->assertSame('promo_123', $order->appliedPromotions()[0]['promotionId']);
    }

    public function testPlaceOrderWithMultiplePromotions(): void
    {
        $appliedPromotions = [
            [
                'promotionId' => 'promo_cart',
                'name' => 'Cart Rule 10%',
                'type' => 'cart_rule',
                'discountAmount' => 1000, // $10.00
                'priceAfterDiscount' => 9000, // $90.00
            ],
            [
                'promotionId' => 'promo_catalog',
                'name' => 'Catalog Rule 5%',
                'type' => 'catalog_rule',
                'discountAmount' => 450, // $4.50
                'priceAfterDiscount' => 8550, // $85.50
            ],
            [
                'promotionId' => 'promo_coupon',
                'name' => 'Extra Coupon',
                'type' => 'coupon',
                'discountAmount' => 855, // $8.55
                'priceAfterDiscount' => 7695, // $76.95
            ],
        ];

        $totalDiscount = Money::fromScalars(2305, 'USD'); // $23.05

        $order = Order::place(
            $this->orderId,
            $this->tenantId,
            'customer@example.com',
            $this->lines,
            $this->address,
            $this->address,
            $appliedPromotions,
            $totalDiscount,
            'EXTRA10'
        );

        $this->assertSame(10000, $order->subtotal()->getAmount()); // $100.00
        $this->assertSame(7695, $order->total()->getAmount()); // $76.95 (after stacked discounts)
        $this->assertSame(2305, $order->discountAmount()->getAmount()); // $23.05
        $this->assertSame('EXTRA10', $order->couponCode());
        $this->assertCount(3, $order->appliedPromotions());
    }

    public function testThrowsExceptionWhenMoreThan3Promotions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot apply more than 3 promotions to an order');

        $appliedPromotions = [
            ['promotionId' => 'promo_1', 'name' => 'Promo 1', 'type' => 'cart_rule', 'discountAmount' => 1000, 'priceAfterDiscount' => 9000],
            ['promotionId' => 'promo_2', 'name' => 'Promo 2', 'type' => 'catalog_rule', 'discountAmount' => 900, 'priceAfterDiscount' => 8100],
            ['promotionId' => 'promo_3', 'name' => 'Promo 3', 'type' => 'coupon', 'discountAmount' => 810, 'priceAfterDiscount' => 7290],
            ['promotionId' => 'promo_4', 'name' => 'Promo 4', 'type' => 'coupon', 'discountAmount' => 729, 'priceAfterDiscount' => 6561],
        ];

        Order::place(
            $this->orderId,
            $this->tenantId,
            'customer@example.com',
            $this->lines,
            $this->address,
            $this->address,
            $appliedPromotions,
            Money::fromScalars(3439, 'USD'),
            'INVALID'
        );
    }

    public function testReconstituteOrderWithPromotions(): void
    {
        $appliedPromotions = [
            [
                'promotionId' => 'promo_123',
                'name' => 'Test Promotion',
                'type' => 'cart_rule',
                'discountAmount' => 1500,
                'priceAfterDiscount' => 8500,
            ],
        ];

        $discountAmount = Money::fromScalars(1500, 'USD');

        $order = Order::reconstituteFromPersistence(
            $this->orderId,
            $this->tenantId,
            'customer@example.com',
            \App\Order\Domain\Model\OrderStatus::pending(),
            $this->lines,
            $this->address,
            $this->address,
            new \DateTimeImmutable('2025-01-01 10:00:00'),
            new \DateTimeImmutable('2025-01-01 10:00:00'),
            $appliedPromotions,
            $discountAmount,
            'TESTCODE'
        );

        $this->assertSame(10000, $order->subtotal()->getAmount());
        $this->assertSame(8500, $order->total()->getAmount());
        $this->assertSame(1500, $order->discountAmount()->getAmount());
        $this->assertSame('TESTCODE', $order->couponCode());
        $this->assertCount(1, $order->appliedPromotions());
        $this->assertSame('promo_123', $order->appliedPromotions()[0]['promotionId']);
    }

    public function testSubtotalAndTotalCalculation(): void
    {
        $lines = [
            OrderLine::create(OrderProductId::generate(), 'Product 1', 2, Money::fromScalars(5000, 'USD')),
            OrderLine::create(OrderProductId::generate(), 'Product 2', 1, Money::fromScalars(3000, 'USD')),
        ];

        $discount = Money::fromScalars(2600, 'USD'); // 20% discount on $13,000 = $2,600

        $order = Order::place(
            $this->orderId,
            $this->tenantId,
            'customer@example.com',
            $lines,
            $this->address,
            $this->address,
            [],
            $discount,
            null
        );

        // Subtotal: (2 * $50.00) + (1 * $30.00) = $130.00
        $this->assertSame(13000, $order->subtotal()->getAmount());

        // Total: $130.00 - $26.00 = $104.00
        $this->assertSame(10400, $order->total()->getAmount());
    }
}
