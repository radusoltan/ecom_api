<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\DTO;

use App\Pricing\Application\DTO\AppliedDiscountDTO;
use App\Pricing\Application\DTO\CartItemPricing;
use App\Pricing\Application\DTO\CartPriceCalculationResult;
use App\Pricing\Application\DTO\CouponValidationResult;
use App\Pricing\Application\DTO\PriceListDTO;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AppliedDiscountDTO::class)]
#[CoversClass(CartItemPricing::class)]
#[CoversClass(CartPriceCalculationResult::class)]
#[CoversClass(CouponValidationResult::class)]
#[CoversClass(PriceListDTO::class)]
final class PricingDTOsTest extends TestCase
{
    // --- AppliedDiscountDTO ---

    #[Test]
    public function appliedDiscountDtoConstruction(): void
    {
        $amount = Money::of('10.00', 'USD');
        $dto = new AppliedDiscountDTO(
            id: 'promo-1',
            type: 'promotion',
            name: 'Summer Sale',
            amount: $amount,
            discountType: 'percentage',
            discountValue: 10.0,
            scope: 'cart',
            targetItemId: null,
        );

        self::assertSame('promo-1', $dto->id);
        self::assertSame('promotion', $dto->type);
        self::assertSame('Summer Sale', $dto->name);
        self::assertSame($amount, $dto->amount);
        self::assertSame('percentage', $dto->discountType);
        self::assertSame(10.0, $dto->discountValue);
        self::assertSame('cart', $dto->scope);
        self::assertNull($dto->targetItemId);
    }

    #[Test]
    public function appliedDiscountDtoToArray(): void
    {
        $dto = new AppliedDiscountDTO(
            id: 'promo-1',
            type: 'coupon',
            name: 'SAVE10',
            amount: Money::of('10.00', 'USD'),
            discountType: 'fixed_amount',
            discountValue: 1000.0,
            scope: 'item',
            targetItemId: 'item-1',
        );

        $array = $dto->toArray();

        self::assertSame('promo-1', $array['id']);
        self::assertSame('coupon', $array['type']);
        self::assertSame('SAVE10', $array['name']);
        self::assertSame('fixed_amount', $array['discount_type']);
        self::assertSame(1000.0, $array['discount_value']);
        self::assertSame('item', $array['scope']);
        self::assertSame('item-1', $array['target_item_id']);
        self::assertIsArray($array['amount']);
        self::assertSame(1000, $array['amount']['value']);
        self::assertSame('USD', $array['amount']['currency']);
    }

    // --- CartItemPricing ---

    #[Test]
    public function cartItemPricingConstruction(): void
    {
        $base = Money::of('50.00', 'USD');
        $final = Money::of('45.00', 'USD');
        $sub = Money::of('100.00', 'USD');
        $total = Money::of('90.00', 'USD');
        $disc = Money::of('10.00', 'USD');

        $dto = new CartItemPricing(
            cartItemId: 'ci-1',
            productId: 'prod-1',
            variantId: 'var-1',
            quantity: 2,
            baseUnitPrice: $base,
            finalUnitPrice: $final,
            rowSubtotal: $sub,
            rowTotal: $total,
            totalDiscount: $disc,
        );

        self::assertSame('ci-1', $dto->cartItemId);
        self::assertSame('prod-1', $dto->productId);
        self::assertSame('var-1', $dto->variantId);
        self::assertSame(2, $dto->quantity);
        self::assertSame($base, $dto->baseUnitPrice);
        self::assertSame($final, $dto->finalUnitPrice);
        self::assertSame([], $dto->appliedDiscounts);
    }

    #[Test]
    public function cartItemPricingToArray(): void
    {
        $dto = new CartItemPricing(
            cartItemId: 'ci-1',
            productId: 'prod-1',
            variantId: null,
            quantity: 1,
            baseUnitPrice: Money::of('25.00', 'EUR'),
            finalUnitPrice: Money::of('20.00', 'EUR'),
            rowSubtotal: Money::of('25.00', 'EUR'),
            rowTotal: Money::of('20.00', 'EUR'),
            totalDiscount: Money::of('5.00', 'EUR'),
        );

        $array = $dto->toArray();

        self::assertSame('ci-1', $array['cart_item_id']);
        self::assertSame('prod-1', $array['product_id']);
        self::assertNull($array['variant_id']);
        self::assertSame(1, $array['quantity']);
        self::assertSame(2500, $array['base_unit_price']['value']);
        self::assertSame('EUR', $array['base_unit_price']['currency']);
        self::assertSame(2000, $array['final_unit_price']['value']);
        self::assertSame([], $array['applied_discounts']);
    }

    #[Test]
    public function cartItemPricingWithDiscounts(): void
    {
        $discount = new AppliedDiscountDTO(
            id: 'd1',
            type: 'promotion',
            name: 'Sale',
            amount: Money::of('5.00', 'USD'),
            discountType: 'fixed_amount',
            discountValue: 500.0,
        );

        $dto = new CartItemPricing(
            cartItemId: 'ci-1',
            productId: 'prod-1',
            variantId: null,
            quantity: 1,
            baseUnitPrice: Money::of('25.00', 'USD'),
            finalUnitPrice: Money::of('20.00', 'USD'),
            rowSubtotal: Money::of('25.00', 'USD'),
            rowTotal: Money::of('20.00', 'USD'),
            totalDiscount: Money::of('5.00', 'USD'),
            appliedDiscounts: [$discount],
        );

        $array = $dto->toArray();
        self::assertCount(1, $array['applied_discounts']);
        self::assertSame('d1', $array['applied_discounts'][0]['id']);
    }

    // --- CartPriceCalculationResult ---

    #[Test]
    public function cartPriceCalculationResultConstruction(): void
    {
        $dto = new CartPriceCalculationResult(
            cartId: 'cart-1',
            items: [],
            subtotal: Money::of('100.00', 'USD'),
            totalDiscounts: Money::of('10.00', 'USD'),
            grandTotal: Money::of('90.00', 'USD'),
            totalItemsCount: 3,
        );

        self::assertSame('cart-1', $dto->cartId);
        self::assertSame([], $dto->items);
        self::assertSame(3, $dto->totalItemsCount);
        self::assertSame([], $dto->cartLevelDiscounts);
    }

    #[Test]
    public function cartPriceCalculationResultToArray(): void
    {
        $dto = new CartPriceCalculationResult(
            cartId: 'cart-1',
            items: [],
            subtotal: Money::of('100.00', 'USD'),
            totalDiscounts: Money::of('0.00', 'USD'),
            grandTotal: Money::of('100.00', 'USD'),
            totalItemsCount: 2,
        );

        $array = $dto->toArray();

        self::assertSame('cart-1', $array['cart_id']);
        self::assertSame([], $array['items']);
        self::assertSame(10000, $array['subtotal']['value']);
        self::assertSame('USD', $array['subtotal']['currency']);
        self::assertSame(10000, $array['grand_total']['value']);
        self::assertSame(2, $array['total_items_count']);
        self::assertSame([], $array['cart_level_discounts']);
    }

    // --- CouponValidationResult ---

    #[Test]
    public function couponValidationResultInvalid(): void
    {
        $result = CouponValidationResult::invalid('Coupon expired');

        self::assertFalse($result->valid);
        self::assertNull($result->promotion);
        self::assertNull($result->discountAmount);
        self::assertSame('Coupon expired', $result->message);
    }

    // --- PriceListDTO ---

    #[Test]
    public function priceListDtoConstruction(): void
    {
        $dto = new PriceListDTO(
            id: 'pl-1',
            tenantId: 't-1',
            name: 'Summer',
            priority: 100,
            rules: [['type' => 'percentage', 'value' => 10]],
            validFrom: '2025-06-01T00:00:00+00:00',
            validTo: '2025-09-01T00:00:00+00:00',
            isActive: true,
            createdAt: '2025-01-01T00:00:00+00:00',
            updatedAt: '2025-01-01T00:00:00+00:00',
        );

        self::assertSame('pl-1', $dto->id);
        self::assertSame('Summer', $dto->name);
        self::assertSame(100, $dto->priority);
        self::assertTrue($dto->isActive);
    }

    #[Test]
    public function priceListDtoToArray(): void
    {
        $dto = new PriceListDTO(
            id: 'pl-1',
            tenantId: 't-1',
            name: 'VIP Prices',
            priority: 50,
            rules: [],
            validFrom: null,
            validTo: null,
            isActive: false,
            createdAt: '2025-01-01T00:00:00+00:00',
            updatedAt: '2025-02-01T00:00:00+00:00',
        );

        $array = $dto->toArray();

        self::assertSame('pl-1', $array['id']);
        self::assertSame('t-1', $array['tenant_id']);
        self::assertSame('VIP Prices', $array['name']);
        self::assertSame(50, $array['priority']);
        self::assertSame([], $array['rules']);
        self::assertNull($array['valid_from']);
        self::assertNull($array['valid_to']);
        self::assertFalse($array['is_active']);
    }
}
