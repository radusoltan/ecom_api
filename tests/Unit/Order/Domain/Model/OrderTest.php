<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\ValueObject\OrderProductId;
use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Event\OrderDelivered;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Model\OrderStatus;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    // =============================================
    // Test Order Placement via place()
    // =============================================

    public function testItPlacesOrderWithPendingStatus(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'customer@example.com';
        $lines = [$this->createSampleOrderLine()];
        $shippingAddress = $this->createSampleAddress();
        $billingAddress = $this->createSampleAddress();

        // Act
        $order = Order::place(
            $orderId,
            $tenantId,
            $customerEmail,
            $lines,
            $shippingAddress,
            $billingAddress
        );

        // Assert
        $this->assertTrue($order->status()->isPending());
    }

    public function testItRecordsOrderPlacedEventOnPlacement(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'customer@example.com';
        $lines = [$this->createSampleOrderLine()];
        $shippingAddress = $this->createSampleAddress();
        $billingAddress = $this->createSampleAddress();

        // Act
        $order = Order::place($orderId, $tenantId, $customerEmail, $lines, $shippingAddress, $billingAddress);
        $events = $order->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderPlaced::class, $events[0]);
    }

    public function testItSetsAllPropertiesCorrectlyOnPlacement(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'customer@example.com';
        $lines = [$this->createSampleOrderLine()];
        $shippingAddress = $this->createSampleAddress();
        $billingAddress = $this->createSampleAddress();
        $beforePlacement = new \DateTimeImmutable();

        // Act
        $order = Order::place($orderId, $tenantId, $customerEmail, $lines, $shippingAddress, $billingAddress);
        $afterPlacement = new \DateTimeImmutable();

        // Assert
        $this->assertTrue($order->id()->equals($orderId));
        $this->assertTrue($order->tenantId()->equals($tenantId));
        $this->assertSame($customerEmail, $order->customerEmail());
        $this->assertCount(1, $order->lines());
        $this->assertSame($shippingAddress, $order->shippingAddress());
        $this->assertSame($billingAddress, $order->billingAddress());
        $this->assertGreaterThanOrEqual($beforePlacement, $order->createdAt());
        $this->assertLessThanOrEqual($afterPlacement, $order->createdAt());
    }

    public function testPlacedEventContainsCorrectData(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'customer@example.com';
        $lines = [$this->createSampleOrderLine()];
        $shippingAddress = $this->createSampleAddress();
        $billingAddress = $this->createSampleAddress();

        // Act
        $order = Order::place($orderId, $tenantId, $customerEmail, $lines, $shippingAddress, $billingAddress);
        $events = $order->popEvents();
        $event = $events[0];

        // Assert
        $this->assertInstanceOf(OrderPlaced::class, $event);
        $this->assertTrue($event->orderId->equals($orderId));
        $this->assertTrue($event->tenantId->equals($tenantId));
        $this->assertSame($customerEmail, $event->customerEmail);
    }

    public function testItThrowsExceptionWhenPlacingOrderWithEmptyLines(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'customer@example.com';
        $lines = [];
        $shippingAddress = $this->createSampleAddress();
        $billingAddress = $this->createSampleAddress();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must have at least one line item');

        // Act
        Order::place($orderId, $tenantId, $customerEmail, $lines, $shippingAddress, $billingAddress);
    }

    public function testItThrowsExceptionWhenPlacingOrderWithInvalidEmail(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'invalid-email';
        $lines = [$this->createSampleOrderLine()];
        $shippingAddress = $this->createSampleAddress();
        $billingAddress = $this->createSampleAddress();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid customer email');

        // Act
        Order::place($orderId, $tenantId, $customerEmail, $lines, $shippingAddress, $billingAddress);
    }

    // =============================================
    // Test Order Status Updates via updateStatus()
    // =============================================

    public function testItUpdatesOrderStatusFromPendingToProcessing(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->popEvents(); // Clear placement event

        // Act
        $order->startProcessing();

        // Assert
        $this->assertTrue($order->status()->isProcessing());
    }

    public function testItRecordsOrderStatusChangedEvent(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->popEvents(); // Clear placement event

        // Act
        $order->startProcessing();
        $events = $order->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderStatusChanged::class, $events[0]);
    }

    public function testStatusChangedEventContainsCorrectData(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $oldStatus = $order->status();
        $order->popEvents(); // Clear placement event

        // Act
        $order->startProcessing();
        $events = $order->popEvents();
        $event = $events[0];

        // Assert
        $this->assertInstanceOf(OrderStatusChanged::class, $event);
        $this->assertTrue($event->orderId->equals($order->id()));
        $this->assertTrue($event->oldStatus->equals($oldStatus));
        $this->assertTrue($event->newStatus->equals(OrderStatus::processing()));
    }

    public function testItThrowsExceptionWhenUpdatingToInvalidStatus(): void
    {
        // Arrange
        $order = $this->createSampleOrder(); // Starts with pending status
        $order->popEvents();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status transition from "pending" to "shipped"');

        // Act
        $order->markAsShipped(); // Invalid: pending -> shipped
    }

    public function testItThrowsExceptionWhenUpdatingFinalOrderStatus(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->popEvents();
        $order->startProcessing();
        $order->markAsShipped();
        $order->markAsDelivered(); // Order is now delivered
        $order->popEvents();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot change status of finalized order (current status: delivered)');

        // Act
        $order->startProcessing();
    }

    // =============================================
    // Test Order Cancellation via cancel()
    // =============================================

    public function testItCancelsOrderWithPendingStatus(): void
    {
        // Arrange
        $order = $this->createSampleOrder(); // pending status
        $order->popEvents();

        // Act
        $order->cancel();

        // Assert
        $this->assertTrue($order->status()->isCancelled());
    }

    public function testItCancelsOrderWithProcessingStatus(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->startProcessing();
        $order->popEvents();

        // Act
        $order->cancel();

        // Assert
        $this->assertTrue($order->status()->isCancelled());
    }

    public function testItRecordsOrderCancelledEvent(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->popEvents();

        // Act
        $order->cancel();
        $events = $order->popEvents();

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderCancelled::class, $events[0]);
    }

    public function testCancelledEventContainsCorrectData(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $oldStatus = $order->status();
        $order->popEvents();

        // Act
        $order->cancel();
        $events = $order->popEvents();
        $event = $events[0];

        // Assert
        $this->assertInstanceOf(OrderCancelled::class, $event);
        $this->assertTrue($event->orderId->equals($order->id()));
        $this->assertTrue($event->previousStatus->equals($oldStatus));
    }

    public function testItThrowsExceptionWhenCancellingDeliveredOrder(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->startProcessing();
        $order->markAsShipped();
        $order->markAsDelivered();
        $order->popEvents();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel order with status: delivered');

        // Act
        $order->cancel();
    }

    public function testItThrowsExceptionWhenCancellingAlreadyCancelledOrder(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->cancel();
        $order->popEvents();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel order with status: cancelled');

        // Act
        $order->cancel();
    }

    public function testItThrowsExceptionWhenCancellingShippedOrder(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->startProcessing();
        $order->markAsShipped();
        $order->popEvents();

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel order with status: shipped. Can only cancel pending or processing orders');

        // Act
        $order->cancel();
    }

    // =============================================
    // Test Order Total Calculation
    // =============================================

    public function testItCalculatesTotalFromSingleLine(): void
    {
        // Arrange
        $line = OrderLine::create(
            OrderProductId::generate(),
            'Test Product',
            2,
            Money::fromScalars(1000, 'USD') // $10.00
        );
        $order = $this->createOrderWithLines([$line]);

        // Act
        $total = $order->total();

        // Assert
        $this->assertEquals(2000, $total->getAmount()); // 2 * $10.00 = $20.00
        $this->assertEquals('USD', $total->getCurrency()->getCurrencyCode());
    }

    public function testItCalculatesTotalFromMultipleLines(): void
    {
        // Arrange
        $line1 = OrderLine::create(
            OrderProductId::generate(),
            'Product 1',
            2,
            Money::fromScalars(1000, 'USD') // $10.00
        );
        $line2 = OrderLine::create(
            OrderProductId::generate(),
            'Product 2',
            1,
            Money::fromScalars(1500, 'USD') // $15.00
        );
        $order = $this->createOrderWithLines([$line1, $line2]);

        // Act
        $total = $order->total();

        // Assert
        $this->assertEquals(3500, $total->getAmount()); // (2*$10) + (1*$15) = $35.00
        $this->assertEquals('USD', $total->getCurrency()->getCurrencyCode());
    }

    // =============================================
    // Test Order Reconstitution via reconstituteFromPersistence()
    // =============================================

    public function testItReconstitutesOrderFromPersistence(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'customer@example.com';
        $status = OrderStatus::processing();
        $lines = [$this->createSampleOrderLine()];
        $shippingAddress = $this->createSampleAddress();
        $billingAddress = $this->createSampleAddress();
        $createdAt = new \DateTimeImmutable('2024-01-01 12:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-02 12:00:00');

        // Act
        $order = Order::reconstituteFromPersistence(
            $orderId,
            $tenantId,
            $customerEmail,
            $status,
            $lines,
            $shippingAddress,
            $billingAddress,
            $createdAt,
            $updatedAt
        );

        // Assert
        $this->assertTrue($order->id()->equals($orderId));
        $this->assertTrue($order->tenantId()->equals($tenantId));
        $this->assertSame($customerEmail, $order->customerEmail());
        $this->assertTrue($order->status()->equals($status));
        $this->assertSame($createdAt, $order->createdAt());
        $this->assertSame($updatedAt, $order->updatedAt());
    }

    public function testItDoesNotRecordEventsOnReconstitution(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'customer@example.com';
        $status = OrderStatus::pending();
        $lines = [$this->createSampleOrderLine()];
        $shippingAddress = $this->createSampleAddress();
        $billingAddress = $this->createSampleAddress();
        $createdAt = new \DateTimeImmutable();
        $updatedAt = new \DateTimeImmutable();

        // Act
        $order = Order::reconstituteFromPersistence(
            $orderId,
            $tenantId,
            $customerEmail,
            $status,
            $lines,
            $shippingAddress,
            $billingAddress,
            $createdAt,
            $updatedAt
        );

        // Assert
        $this->assertEmpty($order->popEvents());
    }

    // =============================================
    // Test OrderDelivered Event
    // =============================================

    public function testMarkAsDeliveredRecordsOrderDeliveredEvent(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->popEvents(); // Clear placement event
        $order->startProcessing();
        $order->markAsShipped();
        $order->popEvents(); // Clear status change events

        // Act
        $order->markAsDelivered();
        $events = $order->popEvents();

        // Assert - Should have 2 events: OrderStatusChanged + OrderDelivered
        $this->assertCount(2, $events);
        $this->assertInstanceOf(OrderStatusChanged::class, $events[0]);
        $this->assertInstanceOf(OrderDelivered::class, $events[1]);
    }

    public function testOrderDeliveredEventContainsCorrectData(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->popEvents();
        $order->startProcessing();
        $order->markAsShipped();
        $order->popEvents();
        $deliveryMethod = 'express';

        // Act
        $order->markAsDelivered($deliveryMethod);
        $events = $order->popEvents();

        // Assert
        $deliveredEvent = $events[1]; // Second event is OrderDelivered
        $this->assertInstanceOf(OrderDelivered::class, $deliveredEvent);
        $this->assertTrue($deliveredEvent->orderId->equals($order->id()));
        $this->assertTrue($deliveredEvent->tenantId->equals($order->tenantId()));
        $this->assertSame($deliveryMethod, $deliveredEvent->deliveryMethod);
        $this->assertSame($order->customerEmail(), $deliveredEvent->customerEmail);
        $this->assertInstanceOf(\DateTimeImmutable::class, $deliveredEvent->deliveryDate);
        $this->assertInstanceOf(\DateTimeImmutable::class, $deliveredEvent->occurredOn);
    }

    public function testMarkAsDeliveredUsesDefaultDeliveryMethod(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->popEvents();
        $order->startProcessing();
        $order->markAsShipped();
        $order->popEvents();

        // Act
        $order->markAsDelivered(); // No delivery method specified
        $events = $order->popEvents();

        // Assert
        $deliveredEvent = $events[1];
        $this->assertInstanceOf(OrderDelivered::class, $deliveredEvent);
        $this->assertSame('standard', $deliveredEvent->deliveryMethod);
    }

    public function testMarkAsDeliveredChangesStatusToDelivered(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->startProcessing();
        $order->markAsShipped();

        // Act
        $order->markAsDelivered();

        // Assert
        $this->assertTrue($order->status()->isDelivered());
    }

    public function testMarkAsDeliveredThrowsExceptionWhenOrderNotShipped(): void
    {
        // Arrange
        $order = $this->createSampleOrder();
        $order->startProcessing(); // Only processing, not shipped

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status transition');

        // Act
        $order->markAsDelivered();
    }

    // =============================================
    // Test Getters
    // =============================================

    public function testGettersReturnCorrectValues(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'customer@example.com';
        $lines = [$this->createSampleOrderLine()];
        $shippingAddress = $this->createSampleAddress();
        $billingAddress = $this->createSampleAddress();

        // Act
        $order = Order::place($orderId, $tenantId, $customerEmail, $lines, $shippingAddress, $billingAddress);

        // Assert
        $this->assertInstanceOf(OrderId::class, $order->id());
        $this->assertInstanceOf(TenantId::class, $order->tenantId());
        $this->assertIsString($order->customerEmail());
        $this->assertInstanceOf(OrderStatus::class, $order->status());
        $this->assertIsArray($order->lines());
        $this->assertInstanceOf(Address::class, $order->shippingAddress());
        $this->assertInstanceOf(Address::class, $order->billingAddress());
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->createdAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->updatedAt());
    }

    // =============================================
    // Helper Methods
    // =============================================

    private function createSampleOrder(): Order
    {
        return Order::place(
            OrderId::generate(),
            TenantId::generate(),
            'customer@example.com',
            [$this->createSampleOrderLine()],
            $this->createSampleAddress(),
            $this->createSampleAddress()
        );
    }

    private function createOrderWithLines(array $lines): Order
    {
        return Order::place(
            OrderId::generate(),
            TenantId::generate(),
            'customer@example.com',
            $lines,
            $this->createSampleAddress(),
            $this->createSampleAddress()
        );
    }

    private function createSampleOrderLine(): OrderLine
    {
        return OrderLine::create(
            OrderProductId::generate(),
            'Sample Product',
            2,
            Money::fromScalars(1000, 'USD') // $10.00
        );
    }

    private function createSampleAddress(): Address
    {
        return Address::create(
            '123 Main St',
            'New York',
            'NY',
            '10001',
            'USA'
        );
    }
}
