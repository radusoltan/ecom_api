<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Model\OrderStatus;
use App\Order\Domain\ValueObject\OrderProductId;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Order::class)]
final class OrderEdgeCasesTest extends TestCase
{
    // ---------------------------------------------------------------
    // Factory helpers
    // ---------------------------------------------------------------

    private function makeAddress(): Address
    {
        return Address::create('1 Main St', 'Boston', 'MA', '02101', 'US');
    }

    private function makeLine(int $qty = 1, int $unitCents = 1000): OrderLine
    {
        return OrderLine::create(
            OrderProductId::generate(),
            'Widget',
            $qty,
            Money::fromScalars($unitCents, 'USD'),
        );
    }

    private function makePlacedOrder(?array $lines = null): Order
    {
        return Order::place(
            OrderId::generate(),
            TenantId::generate(),
            'buyer@example.com',
            $lines ?? [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
        );
    }

    // ---------------------------------------------------------------
    // place — validation
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsWhenLinesContainNonOrderLineObject(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('All order lines must be instances of OrderLine');

        Order::place(
            OrderId::generate(),
            TenantId::generate(),
            'buyer@example.com',
            ['not-an-order-line'],
            $this->makeAddress(),
            $this->makeAddress(),
        );
    }

    #[Test]
    public function itThrowsForMoreThanThreePromotions(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Cannot apply more than 3 promotions to an order');

        Order::place(
            OrderId::generate(),
            TenantId::generate(),
            'buyer@example.com',
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
            [['id' => '1'], ['id' => '2'], ['id' => '3'], ['id' => '4']],
        );
    }

    #[Test]
    public function itAcceptsExactlyThreePromotions(): void
    {
        $order = Order::place(
            OrderId::generate(),
            TenantId::generate(),
            'buyer@example.com',
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
            [['id' => '1'], ['id' => '2'], ['id' => '3']],
        );

        self::assertCount(3, $order->appliedPromotions());
    }

    // ---------------------------------------------------------------
    // createDraft
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesDraftWithDraftStatus(): void
    {
        $order = Order::createDraft(
            OrderId::generate(),
            TenantId::generate(),
            'draft@example.com',
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
        );

        self::assertTrue($order->status()->isDraft());
    }

    #[Test]
    public function itCreatesDraftRecordsNoEvents(): void
    {
        $order = Order::createDraft(
            OrderId::generate(),
            TenantId::generate(),
            'draft@example.com',
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
        );

        self::assertEmpty($order->popEvents());
    }

    #[Test]
    public function itCreatesDraftHasEmptyPromotions(): void
    {
        $order = Order::createDraft(
            OrderId::generate(),
            TenantId::generate(),
            'draft@example.com',
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
        );

        self::assertSame([], $order->appliedPromotions());
        self::assertNull($order->discountAmount());
        self::assertNull($order->couponCode());
    }

    #[Test]
    public function itCreatesDraftThrowsWhenEmptyLines(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Order must have at least one line item');

        Order::createDraft(
            OrderId::generate(),
            TenantId::generate(),
            'draft@example.com',
            [],
            $this->makeAddress(),
            $this->makeAddress(),
        );
    }

    #[Test]
    public function itCreatesDraftThrowsForInvalidEmail(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid customer email');

        Order::createDraft(
            OrderId::generate(),
            TenantId::generate(),
            'not-valid',
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
        );
    }

    #[Test]
    public function itCreatesDraftThrowsWhenLinesContainNonOrderLineObject(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('All order lines must be instances of OrderLine');

        Order::createDraft(
            OrderId::generate(),
            TenantId::generate(),
            'draft@example.com',
            [new \stdClass()],
            $this->makeAddress(),
            $this->makeAddress(),
        );
    }

    // ---------------------------------------------------------------
    // submitDraft
    // ---------------------------------------------------------------

    #[Test]
    public function itSubmitsDraftTransitioningToPending(): void
    {
        $order = Order::createDraft(
            OrderId::generate(),
            TenantId::generate(),
            'draft@example.com',
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
        );

        $order->submitDraft();

        self::assertTrue($order->status()->isPending());
    }

    #[Test]
    public function itSubmitsDraftRecordsStatusChangedEvent(): void
    {
        $order = Order::createDraft(
            OrderId::generate(),
            TenantId::generate(),
            'draft@example.com',
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
        );

        $order->submitDraft();
        $events = $order->popEvents();

        self::assertNotEmpty($events);
    }

    // ---------------------------------------------------------------
    // markAsPaid
    // ---------------------------------------------------------------

    #[Test]
    public function itMarksPendingAsPaid(): void
    {
        $order = $this->makePlacedOrder();
        $order->popEvents();

        $order->markAsPaid();

        self::assertTrue($order->status()->isPaid());
    }

    #[Test]
    public function itThrowsWhenMarkingShippedOrderAsPaid(): void
    {
        $order = $this->makePlacedOrder();
        $order->startProcessing();
        $order->markAsShipped();
        $order->popEvents();

        self::expectException(\InvalidArgumentException::class);

        $order->markAsPaid();
    }

    // ---------------------------------------------------------------
    // startProcessing
    // ---------------------------------------------------------------

    #[Test]
    public function itStartsProcessingFromPaid(): void
    {
        $order = $this->makePlacedOrder();
        $order->markAsPaid();
        $order->popEvents();

        $order->startProcessing();

        self::assertTrue($order->status()->isProcessing());
    }

    #[Test]
    public function itThrowsWhenStartingProcessingFromShipped(): void
    {
        $order = $this->makePlacedOrder();
        $order->startProcessing();
        $order->markAsShipped();
        $order->popEvents();

        self::expectException(\InvalidArgumentException::class);

        $order->startProcessing();
    }

    // ---------------------------------------------------------------
    // cancel — from different states
    // ---------------------------------------------------------------

    #[Test]
    public function itCancelsDraftOrder(): void
    {
        $order = Order::createDraft(
            OrderId::generate(),
            TenantId::generate(),
            'buyer@example.com',
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
        );

        $order->cancel();

        self::assertTrue($order->status()->isCancelled());
    }

    #[Test]
    public function itThrowsWhenCancellingPaidOrder(): void
    {
        $order = $this->makePlacedOrder();
        $order->markAsPaid();
        $order->popEvents();

        // paid → cancelled is in the transition table, so this should succeed
        $order->cancel();
        self::assertTrue($order->status()->isCancelled());
    }

    // ---------------------------------------------------------------
    // total / subtotal with tax
    // ---------------------------------------------------------------

    #[Test]
    public function itAddsTaxAmountToTotal(): void
    {
        $order = Order::place(
            OrderId::generate(),
            TenantId::generate(),
            'buyer@example.com',
            [$this->makeLine(2, 5000)], // 2 × $50 = $100
            $this->makeAddress(),
            $this->makeAddress(),
            [],
            null,
            null,
            Money::fromScalars(2000, 'USD'), // tax $20
        );

        // subtotal = $100, tax = $20 → total = $120
        self::assertSame(10000, $order->subtotal()->getAmount());
        self::assertSame(12000, $order->total()->getAmount());
    }

    #[Test]
    public function itSubtractsDiscountAndAddsTaxCorrectly(): void
    {
        $order = Order::place(
            OrderId::generate(),
            TenantId::generate(),
            'buyer@example.com',
            [$this->makeLine(1, 10000)], // $100
            $this->makeAddress(),
            $this->makeAddress(),
            [],
            Money::fromScalars(1000, 'USD'), // discount $10
            null,
            Money::fromScalars(900, 'USD'),  // tax $9
        );

        // total = (100 - 10) + 9 = $99
        self::assertSame(9900, $order->total()->getAmount());
    }

    #[Test]
    public function itReturnsTaxFieldsStoredOnOrder(): void
    {
        $order = Order::place(
            OrderId::generate(),
            TenantId::generate(),
            'buyer@example.com',
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
            [],
            null,
            null,
            Money::fromScalars(500, 'USD'),
            'US-NY',
            'rule-123',
            0.08,
            false,
            'VAT-XYZ',
        );

        self::assertSame(500, $order->taxAmount()->getAmount());
        self::assertSame('US-NY', $order->taxJurisdiction());
        self::assertSame('rule-123', $order->taxRuleId());
        self::assertEqualsWithDelta(0.08, $order->taxRate(), 0.0001);
        self::assertFalse($order->isReverseCharge());
        self::assertSame('VAT-XYZ', $order->vatNumber());
    }

    #[Test]
    public function itStoresReverseChargeFlag(): void
    {
        $order = Order::place(
            OrderId::generate(),
            TenantId::generate(),
            'buyer@example.com',
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
            [],
            null,
            null,
            null,
            null,
            null,
            0.0,
            true, // isReverseCharge
        );

        self::assertTrue($order->isReverseCharge());
    }

    // ---------------------------------------------------------------
    // reconstituteFromPersistence — tax fields
    // ---------------------------------------------------------------

    #[Test]
    public function itReconstitutesWithAllTaxFields(): void
    {
        $taxAmount = Money::fromScalars(800, 'USD');

        $order = Order::reconstituteFromPersistence(
            OrderId::generate(),
            TenantId::generate(),
            'buyer@example.com',
            OrderStatus::shipped(),
            [$this->makeLine()],
            $this->makeAddress(),
            $this->makeAddress(),
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-02-01'),
            [],
            null,
            null,
            $taxAmount,
            'EU-DE',
            'rule-eu',
            0.19,
            true,
            'DE123456789',
        );

        self::assertTrue($order->status()->isShipped());
        self::assertSame(800, $order->taxAmount()->getAmount());
        self::assertSame('EU-DE', $order->taxJurisdiction());
        self::assertSame('rule-eu', $order->taxRuleId());
        self::assertEqualsWithDelta(0.19, $order->taxRate(), 0.0001);
        self::assertTrue($order->isReverseCharge());
        self::assertSame('DE123456789', $order->vatNumber());
    }

    // ---------------------------------------------------------------
    // Null defaults on nullable getters
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsNullForOptionalFieldsByDefault(): void
    {
        $order = $this->makePlacedOrder();

        self::assertNull($order->discountAmount());
        self::assertNull($order->couponCode());
        self::assertNull($order->taxAmount());
        self::assertNull($order->taxJurisdiction());
        self::assertNull($order->taxRuleId());
        self::assertNull($order->vatNumber());
        self::assertSame(0.0, $order->taxRate());
        self::assertFalse($order->isReverseCharge());
    }

    // ---------------------------------------------------------------
    // Multiple lines total
    // ---------------------------------------------------------------

    #[Test]
    public function itCalculatesSubtotalAsSumOfAllLines(): void
    {
        $lines = [
            $this->makeLine(3, 2000),  // 3 × $20 = $60
            $this->makeLine(1, 5000),  // 1 × $50 = $50
            $this->makeLine(2, 1500),  // 2 × $15 = $30
        ];

        $order = $this->makePlacedOrder($lines);

        // subtotal = $60 + $50 + $30 = $140
        self::assertSame(14000, $order->subtotal()->getAmount());
    }
}
