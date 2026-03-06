<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\DTO;

use App\Order\Application\DTO\OrderDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderDTO::class)]
final class OrderDTOTest extends TestCase
{
    #[Test]
    public function orderDtoConstruction(): void
    {
        $dto = new OrderDTO(
            id: 'ord-1',
            tenantId: 't-1',
            customerEmail: 'john@example.com',
            status: 'pending',
            lines: [
                ['productId' => 'p-1', 'productName' => 'Widget', 'quantity' => 2, 'unitPriceAmount' => 1000, 'unitPriceCurrency' => 'USD', 'totalAmount' => 2000],
            ],
            shippingAddress: ['street' => '123 Main', 'city' => 'Springfield', 'state' => 'IL', 'postalCode' => '62701', 'country' => 'US'],
            billingAddress: ['street' => '123 Main', 'city' => 'Springfield', 'state' => 'IL', 'postalCode' => '62701', 'country' => 'US'],
            totalAmount: 2000,
            totalCurrency: 'USD',
            appliedPromotions: [],
            discountAmount: null,
            discountCurrency: null,
            couponCode: null,
            taxAmount: 200,
            taxCurrency: 'USD',
            taxJurisdiction: 'US-IL',
            taxRuleId: 'tax-1',
            taxRate: 10.0,
            createdAt: '2025-06-15 10:00:00',
            updatedAt: '2025-06-15 10:00:00',
        );

        self::assertSame('ord-1', $dto->id);
        self::assertSame('t-1', $dto->tenantId);
        self::assertSame('john@example.com', $dto->customerEmail);
        self::assertSame('pending', $dto->status);
        self::assertCount(1, $dto->lines);
        self::assertSame(2000, $dto->totalAmount);
        self::assertSame('USD', $dto->totalCurrency);
        self::assertSame(200, $dto->taxAmount);
        self::assertSame(10.0, $dto->taxRate);
    }

    #[Test]
    public function orderDtoWithDiscounts(): void
    {
        $dto = new OrderDTO(
            id: 'ord-2',
            tenantId: 't-1',
            customerEmail: 'jane@example.com',
            status: 'confirmed',
            lines: [],
            shippingAddress: ['street' => '456 Oak', 'city' => 'Portland', 'state' => 'OR', 'postalCode' => '97201', 'country' => 'US'],
            billingAddress: ['street' => '456 Oak', 'city' => 'Portland', 'state' => 'OR', 'postalCode' => '97201', 'country' => 'US'],
            totalAmount: 9000,
            totalCurrency: 'USD',
            appliedPromotions: ['SUMMER20'],
            discountAmount: 1000,
            discountCurrency: 'USD',
            couponCode: 'SUMMER20',
            taxAmount: null,
            taxCurrency: null,
            taxJurisdiction: null,
            taxRuleId: null,
            taxRate: 0.0,
            createdAt: '2025-06-15 10:00:00',
            updatedAt: '2025-06-15 10:00:00',
        );

        self::assertSame(9000, $dto->totalAmount);
        self::assertSame(1000, $dto->discountAmount);
        self::assertSame('SUMMER20', $dto->couponCode);
        self::assertSame(['SUMMER20'], $dto->appliedPromotions);
        self::assertNull($dto->taxAmount);
        self::assertNull($dto->taxJurisdiction);
    }

    #[Test]
    public function orderDtoNullOptionals(): void
    {
        $dto = new OrderDTO(
            id: 'ord-3',
            tenantId: 't-1',
            customerEmail: 'test@test.com',
            status: 'draft',
            lines: [],
            shippingAddress: [],
            billingAddress: [],
            totalAmount: 0,
            totalCurrency: 'EUR',
            appliedPromotions: [],
            discountAmount: null,
            discountCurrency: null,
            couponCode: null,
            taxAmount: null,
            taxCurrency: null,
            taxJurisdiction: null,
            taxRuleId: null,
            taxRate: 0.0,
            createdAt: '2025-01-01 00:00:00',
            updatedAt: '2025-01-01 00:00:00',
        );

        self::assertNull($dto->discountAmount);
        self::assertNull($dto->discountCurrency);
        self::assertNull($dto->couponCode);
        self::assertNull($dto->taxAmount);
        self::assertNull($dto->taxCurrency);
        self::assertNull($dto->taxJurisdiction);
        self::assertNull($dto->taxRuleId);
    }
}
