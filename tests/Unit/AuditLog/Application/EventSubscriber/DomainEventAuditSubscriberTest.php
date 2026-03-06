<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Application\EventSubscriber;

use App\AuditLog\Application\Command\LogAuditEntry\LogAuditEntry;
use App\AuditLog\Application\EventSubscriber\DomainEventAuditSubscriber;
use App\Cart\Domain\Event\CartCreated;
use App\Cart\Domain\Event\ItemAddedToCart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\CartItemId;
use App\Cart\Domain\Model\Quantity as CartQuantity;
use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\SKU;
use App\Customer\Domain\Event\AccountDeletionRequested;
use App\Customer\Domain\Event\CustomerCreated;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Inventory\Domain\Event\StockAdjusted;
use App\Inventory\Domain\Event\WarehouseCreated;
use App\Inventory\Domain\Model\Quantity as InventoryQuantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Invoice\Domain\Event\InvoiceCreated;
use App\Invoice\Domain\Model\InvoiceId;
use App\Notifications\Domain\Event\NotificationCreated;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Order\Domain\Model\OrderId;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentCreated;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\Event\PaymentRetryAttempted;
use App\Payment\Domain\Event\PaymentRetryExhausted;
use App\Payment\Domain\Event\PaymentRetryScheduled;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Privacy\Domain\Event\ConsentGranted;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Returns\Domain\Event\ReturnRequestCreated;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Event\ShipmentCreated;
use App\Shipping\Domain\Model\ShipmentId;
use App\Tenant\Domain\Event\TenantCreated;
use App\Tenant\Domain\ValueObject\TenantName;
use App\Wishlist\Domain\Event\ItemAddedToWishlist;
use App\Wishlist\Domain\ValueObject\WishlistId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class DomainEventAuditSubscriberTest extends TestCase
{
    private MessageBusInterface $commandBus;
    private TokenStorageInterface $tokenStorage;
    private RequestStack $requestStack;
    private DomainEventAuditSubscriber $subscriber;

    /** @var list<LogAuditEntry> */
    private array $dispatchedCommands = [];

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const USER_ID = 'user-123';
    private const IP_ADDRESS = '192.168.1.1';
    private const USER_AGENT = 'TestAgent/1.0';

    protected function setUp(): void
    {
        $this->dispatchedCommands = [];

        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->commandBus->method('dispatch')
            ->willReturnCallback(function (object $message): Envelope {
                if ($message instanceof LogAuditEntry) {
                    $this->dispatchedCommands[] = $message;
                }

                return new Envelope($message);
            });

        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn(self::USER_ID);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->tokenStorage->method('getToken')->willReturn($token);

        $request = new Request(server: [
            'REMOTE_ADDR' => self::IP_ADDRESS,
            'HTTP_USER_AGENT' => self::USER_AGENT,
        ]);

        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);

        $this->subscriber = new DomainEventAuditSubscriber(
            $this->commandBus,
            $this->tokenStorage,
            $this->requestStack,
            new NullLogger(),
        );
    }

    public function testSubscribesToAllPaymentEvents(): void
    {
        $events = DomainEventAuditSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(PaymentCreated::class, $events);
        $this->assertArrayHasKey(PaymentCaptured::class, $events);
        $this->assertArrayHasKey(PaymentRefunded::class, $events);
        $this->assertArrayHasKey(PaymentCancelled::class, $events);
        $this->assertArrayHasKey(PaymentFailed::class, $events);
        $this->assertArrayHasKey(PaymentRetryScheduled::class, $events);
        $this->assertArrayHasKey(PaymentRetryAttempted::class, $events);
        $this->assertArrayHasKey(PaymentRetryExhausted::class, $events);
    }

    public function testPaymentCapturedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmount: Money::fromScalars(5000, 'USD'),
            orderId: 'order-123',
        );

        $this->subscriber->onPaymentCaptured($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame(self::USER_ID, $command->userId);
        $this->assertSame('capture', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame('order-123', $command->metadata['orderId']);
        $this->assertSame(5000, $command->metadata['capturedAmountInCents']);
        $this->assertSame('PaymentCaptured', $command->metadata['event']);
        $this->assertSame(self::IP_ADDRESS, $command->ipAddress);
        $this->assertSame(self::USER_AGENT, $command->userAgent);
    }

    public function testPaymentRefundedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(2500, 'USD'),
            reason: 'Customer request',
        );

        $this->subscriber->onPaymentRefunded($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('refund', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame(2500, $command->metadata['refundedAmountInCents']);
        $this->assertSame('Customer request', $command->metadata['reason']);
        $this->assertSame('PaymentRefunded', $command->metadata['event']);
    }

    public function testPaymentCancelledCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentCancelled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            reason: 'Order cancelled by customer',
        );

        $this->subscriber->onPaymentCancelled($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('cancel', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame('Order cancelled by customer', $command->metadata['reason']);
        $this->assertSame('PaymentCancelled', $command->metadata['event']);
    }

    public function testPaymentRetryScheduledCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $scheduledFor = new \DateTimeImmutable('2026-02-19T15:30:00+00:00');

        $event = new PaymentRetryScheduled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            retryAttempt: 2,
            scheduledFor: $scheduledFor,
            errorCode: 'gateway_timeout',
            orderId: 'order-456',
        );

        $this->subscriber->onPaymentRetryScheduled($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('retry', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame('order-456', $command->metadata['orderId']);
        $this->assertSame(2, $command->metadata['retryAttempt']);
        $this->assertSame('2026-02-19T15:30:00+00:00', $command->metadata['scheduledFor']);
        $this->assertSame('gateway_timeout', $command->metadata['errorCode']);
        $this->assertSame('PaymentRetryScheduled', $command->metadata['event']);
    }

    public function testPaymentRetryAttemptedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentRetryAttempted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            attemptNumber: 3,
            wasSuccessful: false,
            errorCode: 'insufficient_funds',
            errorMessage: 'Card declined',
        );

        $this->subscriber->onPaymentRetryAttempted($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('retry', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame(3, $command->metadata['attemptNumber']);
        $this->assertFalse($command->metadata['wasSuccessful']);
        $this->assertSame('insufficient_funds', $command->metadata['errorCode']);
        $this->assertSame('Card declined', $command->metadata['errorMessage']);
        $this->assertSame('PaymentRetryAttempted', $command->metadata['event']);
    }

    public function testPaymentRetryExhaustedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentRetryExhausted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            totalAttempts: 5,
            lastErrorCode: 'card_declined',
            lastErrorMessage: 'Insufficient funds',
            orderId: 'order-789',
        );

        $this->subscriber->onPaymentRetryExhausted($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame('order-789', $command->metadata['orderId']);
        $this->assertSame(5, $command->metadata['totalAttempts']);
        $this->assertSame('card_declined', $command->metadata['lastErrorCode']);
        $this->assertSame('Insufficient funds', $command->metadata['lastErrorMessage']);
        $this->assertSame('PaymentRetryExhausted', $command->metadata['event']);
    }

    public function testPaymentCreatedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentCreated(
            paymentId: $paymentId,
            tenantId: $tenantId,
            orderId: 'order-100',
            amount: Money::fromScalars(9999, 'EUR'),
            gateway: 'stripe',
        );

        $this->subscriber->onPaymentCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('create', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame('order-100', $command->metadata['orderId']);
        $this->assertSame(9999, $command->metadata['amountInCents']);
        $this->assertSame('EUR', $command->metadata['currency']);
        $this->assertSame('stripe', $command->metadata['gateway']);
    }

    public function testPaymentFailedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentFailed(
            paymentId: $paymentId,
            tenantId: $tenantId,
            errorMessage: 'Gateway timeout',
        );

        $this->subscriber->onPaymentFailed($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('update', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame('Gateway timeout', $command->metadata['errorMessage']);
        $this->assertSame('PaymentFailed', $command->metadata['event']);
    }

    public function testAuditRecordIncludesUserContext(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmount: Money::fromScalars(1000, 'USD'),
        );

        $this->subscriber->onPaymentCaptured($event);

        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::USER_ID, $command->userId);
        $this->assertSame(self::IP_ADDRESS, $command->ipAddress);
        $this->assertSame(self::USER_AGENT, $command->userAgent);
    }

    public function testAuditRecordWithSystemContext(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $subscriber = new DomainEventAuditSubscriber(
            $this->commandBus,
            $tokenStorage,
            new RequestStack(),
            new NullLogger(),
        );

        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentRetryAttempted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            attemptNumber: 1,
            wasSuccessful: true,
        );

        $subscriber->onPaymentRetryAttempted($event);

        $command = $this->dispatchedCommands[0];
        $this->assertNull($command->userId);
        $this->assertNull($command->ipAddress);
        $this->assertNull($command->userAgent);
    }

    public function testSubscribesToAllBoundedContextEvents(): void
    {
        $events = DomainEventAuditSubscriber::getSubscribedEvents();

        // Verify we subscribe to events from all 14 bounded contexts
        $contexts = [
            'User' => 'onUserCreated',
            'Order' => 'onOrderPlaced',
            'Payment' => 'onPaymentCreated',
            'Review' => 'onReviewSubmitted',
            'Catalog' => 'onProductCreated',
            'Customer' => 'onCustomerCreated',
            'Inventory' => 'onStockAdjusted',
            'Tenant' => 'onTenantCreated',
            'Cart' => 'onCartCreated',
            'Shipping' => 'onShipmentCreated',
            'Invoice' => 'onInvoiceCreated',
            'Returns' => 'onReturnRequestCreated',
            'Privacy' => 'onConsentGranted',
            'Notifications' => 'onNotificationCreated',
            'Wishlist' => 'onItemAddedToWishlist',
        ];

        foreach ($contexts as $context => $handler) {
            $this->assertContains($handler, $events, "Missing handler for {$context} context");
        }

        // Total event count: 88 events across 15 bounded contexts
        $this->assertGreaterThanOrEqual(88, \count($events));
    }

    public function testProductCreatedCreatesAuditRecord(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new ProductCreated(
            productId: $productId,
            tenantId: $tenantId,
            sku: SKU::fromString('PRD-000001'),
            name: 'Test Product',
        );

        $this->subscriber->onProductCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('product', $command->resourceType);
        $this->assertSame($productId->toString(), $command->resourceId);
        $this->assertSame('PRD-000001', $command->metadata['sku']);
        $this->assertSame('Test Product', $command->metadata['name']);
        $this->assertSame('ProductCreated', $command->metadata['event']);
    }

    public function testCategoryCreatedCreatesAuditRecord(): void
    {
        $categoryId = CategoryId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new CategoryCreated(
            categoryId: $categoryId,
            tenantId: $tenantId,
            name: 'Electronics',
        );

        $this->subscriber->onCategoryCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('create', $command->actionType);
        $this->assertSame('category', $command->resourceType);
        $this->assertSame('Electronics', $command->metadata['name']);
    }

    public function testCustomerCreatedCreatesAuditRecord(): void
    {
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new CustomerCreated(
            customerId: $customerId,
            tenantId: $tenantId,
            email: Email::fromString('customer@example.com'),
            firstName: 'John',
            lastName: 'Doe',
        );

        $this->subscriber->onCustomerCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('customer', $command->resourceType);
        $this->assertSame($customerId->toString(), $command->resourceId);
        $this->assertSame('customer@example.com', $command->metadata['email']);
        $this->assertSame('John', $command->metadata['firstName']);
        $this->assertSame('Doe', $command->metadata['lastName']);
    }

    public function testAccountDeletionRequestedCreatesAuditRecord(): void
    {
        $requestId = DeletionRequestId::generate();
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new AccountDeletionRequested(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            reason: 'No longer needed',
        );

        $this->subscriber->onAccountDeletionRequested($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('request_deletion', $command->actionType);
        $this->assertSame('account_deletion', $command->resourceType);
        $this->assertSame($requestId->toString(), $command->resourceId);
        $this->assertSame($customerId->toString(), $command->metadata['customerId']);
        $this->assertSame('No longer needed', $command->metadata['reason']);
    }

    public function testStockAdjustedUsesSystemTenant(): void
    {
        $stockItemId = StockItemId::generate();

        $event = new StockAdjusted(
            stockItemId: $stockItemId,
            previousQuantity: InventoryQuantity::fromInt(100),
            newQuantity: InventoryQuantity::fromInt(80),
            reason: 'Manual adjustment',
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onStockAdjusted($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        // Events without tenantId fall back to system tenant
        $this->assertSame('00000000-0000-0000-0000-000000000000', $command->tenantId);
        $this->assertSame('adjust', $command->actionType);
        $this->assertSame('stock', $command->resourceType);
        $this->assertSame(100, $command->metadata['previousQuantity']);
        $this->assertSame(80, $command->metadata['newQuantity']);
        $this->assertSame('Manual adjustment', $command->metadata['reason']);
    }

    public function testWarehouseCreatedCreatesAuditRecord(): void
    {
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new WarehouseCreated(
            warehouseId: $warehouseId,
            tenantId: $tenantId,
            code: WarehouseCode::fromString('WH-001'),
            name: WarehouseName::fromString('Main Warehouse'),
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onWarehouseCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('warehouse', $command->resourceType);
        $this->assertSame('WH-001', $command->metadata['code']);
        $this->assertSame('Main Warehouse', $command->metadata['name']);
    }

    public function testTenantCreatedCreatesAuditRecord(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new TenantCreated(
            tenantId: $tenantId,
            name: TenantName::fromString('Test Store'),
            ownerEmail: Email::fromString('owner@store.com'),
            occurredAt: new \DateTimeImmutable(),
        );

        $this->subscriber->onTenantCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('tenant', $command->resourceType);
        $this->assertSame(self::TENANT_ID, $command->resourceId);
        $this->assertSame('Test Store', $command->metadata['name']);
        $this->assertSame('owner@store.com', $command->metadata['ownerEmail']);
    }

    public function testCartCreatedCreatesAuditRecord(): void
    {
        $cartId = CartId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new CartCreated(
            cartId: $cartId,
            tenantId: $tenantId,
            customerId: null,
            sessionId: null,
        );

        $this->subscriber->onCartCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('create', $command->actionType);
        $this->assertSame('cart', $command->resourceType);
        $this->assertSame($cartId->toString(), $command->resourceId);
    }

    public function testItemAddedToCartCreatesAuditRecord(): void
    {
        $cartId = CartId::generate();
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new ItemAddedToCart(
            cartId: $cartId,
            tenantId: $tenantId,
            cartItemId: CartItemId::generate(),
            productId: $productId,
            variantId: null,
            quantity: CartQuantity::fromInt(2),
            unitPrice: Money::fromScalars(1999, 'USD'),
        );

        $this->subscriber->onItemAddedToCart($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('add_item', $command->actionType);
        $this->assertSame('cart', $command->resourceType);
        $this->assertSame($productId->toString(), $command->metadata['productId']);
        $this->assertSame(2, $command->metadata['quantity']);
    }

    public function testShipmentCreatedCreatesAuditRecord(): void
    {
        $shipmentId = ShipmentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new ShipmentCreated(
            shipmentId: $shipmentId,
            tenantId: $tenantId,
            orderId: 'order-500',
            recipientName: 'Jane Doe',
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onShipmentCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('shipment', $command->resourceType);
        $this->assertSame('order-500', $command->metadata['orderId']);
        $this->assertSame('Jane Doe', $command->metadata['recipientName']);
    }

    public function testInvoiceCreatedCreatesAuditRecord(): void
    {
        $invoiceId = InvoiceId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $orderId = OrderId::generate();
        $customerId = CustomerId::generate();

        $event = new InvoiceCreated(
            invoiceId: $invoiceId,
            tenantId: $tenantId,
            orderId: $orderId,
            customerId: $customerId,
        );

        $this->subscriber->onInvoiceCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('create', $command->actionType);
        $this->assertSame('invoice', $command->resourceType);
        $this->assertSame($invoiceId->toString(), $command->resourceId);
        $this->assertSame($orderId->toString(), $command->metadata['orderId']);
        $this->assertSame($customerId->toString(), $command->metadata['customerId']);
    }

    public function testReturnRequestCreatedCreatesAuditRecord(): void
    {
        $returnRequestId = ReturnRequestId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new ReturnRequestCreated(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            orderId: 'order-600',
            reason: 'Defective product',
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onReturnRequestCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('create', $command->actionType);
        $this->assertSame('return_request', $command->resourceType);
        $this->assertSame('order-600', $command->metadata['orderId']);
        $this->assertSame('Defective product', $command->metadata['reason']);
    }

    public function testConsentGrantedCreatesAuditRecord(): void
    {
        $consentId = ConsentId::generate();
        $customerId = CustomerId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new ConsentGranted(
            consentId: $consentId,
            customerId: $customerId,
            purpose: ConsentPurpose::fromString('marketing'),
            tenantId: $tenantId,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onConsentGranted($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('grant', $command->actionType);
        $this->assertSame('consent', $command->resourceType);
        $this->assertSame($consentId->toString(), $command->resourceId);
        $this->assertSame('marketing', $command->metadata['purpose']);
    }

    public function testNotificationCreatedCreatesAuditRecord(): void
    {
        $notificationId = NotificationId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new NotificationCreated(
            notificationId: $notificationId,
            tenantId: $tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'user@example.com',
            subject: 'Order Confirmation',
        );

        $this->subscriber->onNotificationCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('create', $command->actionType);
        $this->assertSame('notification', $command->resourceType);
        $this->assertSame('email', $command->metadata['type']);
        $this->assertSame('Order Confirmation', $command->metadata['subject']);
    }

    public function testWishlistItemAddedUsesSystemTenant(): void
    {
        $wishlistId = WishlistId::generate();
        $productId = ProductId::generate();

        $event = new ItemAddedToWishlist(
            wishlistId: $wishlistId,
            productId: $productId,
            customerId: 'customer-123',
            occurredAt: new \DateTimeImmutable(),
        );

        $this->subscriber->onItemAddedToWishlist($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        // Wishlist events lack tenantId, falls back to system tenant
        $this->assertSame('00000000-0000-0000-0000-000000000000', $command->tenantId);
        $this->assertSame('add_item', $command->actionType);
        $this->assertSame('wishlist', $command->resourceType);
        $this->assertSame($productId->toString(), $command->metadata['productId']);
        $this->assertSame('customer-123', $command->metadata['customerId']);
    }
}
