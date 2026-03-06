<?php

declare(strict_types=1);

namespace App\Tests\Unit\AuditLog\Application\EventSubscriber;

use App\AuditLog\Application\Command\LogAuditEntry\LogAuditEntry;
use App\AuditLog\Application\EventSubscriber\DomainEventAuditSubscriber;
use App\Cart\Domain\Event\CartAbandoned;
use App\Cart\Domain\Event\CartCleared;
use App\Cart\Domain\Event\CartQuantityUpdated;
use App\Cart\Domain\Event\ItemRemovedFromCart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\CartItemId;
use App\Cart\Domain\Model\Quantity as CartQuantity;
use App\Catalog\Domain\Event\BundleCreated;
use App\Catalog\Domain\Event\BundleItemAdded;
use App\Catalog\Domain\Event\BundleItemRemoved;
use App\Catalog\Domain\Event\BundleRemoved;
use App\Catalog\Domain\Event\BundleUpdated;
use App\Catalog\Domain\Event\CategoryTranslationsUpdated;
use App\Catalog\Domain\Event\CategoryUpdated;
use App\Catalog\Domain\Event\OptionDefined;
use App\Catalog\Domain\Event\OptionRemoved;
use App\Catalog\Domain\Event\ProductDeactivated;
use App\Catalog\Domain\Event\ProductDiscontinued;
use App\Catalog\Domain\Event\ProductPublished;
use App\Catalog\Domain\Event\ProductReactivated;
use App\Catalog\Domain\Event\ProductTranslationsUpdated;
use App\Catalog\Domain\Event\ProductTypeChanged;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\Event\SubscriptionConfigured;
use App\Catalog\Domain\Event\SubscriptionRemoved;
use App\Catalog\Domain\Event\SubscriptionUpdated;
use App\Catalog\Domain\Event\VariantAdded;
use App\Catalog\Domain\Event\VariantRemoved;
use App\Catalog\Domain\Event\VariantsGenerated;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Domain\ValueObject\LocalizedString;
use App\Catalog\Domain\ValueObject\OptionCode;
use App\Catalog\Domain\ValueObject\ProductType;
use App\Catalog\Domain\ValueObject\SubscriptionInterval;
use App\Catalog\Domain\ValueObject\VariantSKU;
use App\Customer\Domain\Event\AccountDeletionCancelled;
use App\Customer\Domain\Event\AccountDeletionCompleted;
use App\Customer\Domain\Event\AccountDeletionConfirmed;
use App\Customer\Domain\Event\AccountDeletionHeld;
use App\Customer\Domain\Event\CustomerActivated;
use App\Customer\Domain\Event\CustomerAddressAdded;
use App\Customer\Domain\Event\CustomerAddressRemoved;
use App\Customer\Domain\Event\CustomerAddressUpdated;
use App\Customer\Domain\Event\CustomerConsentChanged;
use App\Customer\Domain\Event\CustomerDeactivated;
use App\Customer\Domain\Event\CustomerPreferencesUpdated;
use App\Customer\Domain\Event\CustomerSegmentChanged;
use App\Customer\Domain\Event\CustomerUpdated;
use App\Customer\Domain\Event\DataExportCompleted;
use App\Customer\Domain\Event\DataExportRequested;
use App\Customer\Domain\Event\DefaultAddressChanged;
use App\Customer\Domain\Event\LoyaltyPointsAwarded;
use App\Customer\Domain\Event\LoyaltyPointsRedeemed;
use App\Customer\Domain\Event\LoyaltyProgramActivated;
use App\Customer\Domain\Event\LoyaltyProgramCreated;
use App\Customer\Domain\Event\LoyaltyProgramDeactivated;
use App\Customer\Domain\Event\LoyaltyProgramUpdated;
use App\Customer\Domain\Event\LoyaltyTierAdded;
use App\Customer\Domain\Event\LoyaltyTierRemoved;
use App\Customer\Domain\Event\LoyaltyTierUpdated;
use App\Customer\Domain\Event\NotificationPreferencesUpdated;
use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\ConsentType;
use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Customer\Domain\ValueObject\DataExportRequestId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use App\Customer\Domain\ValueObject\LoyaltyTierId;
use App\Inventory\Domain\Event\StockAllocated;
use App\Inventory\Domain\Event\StockDepleted;
use App\Inventory\Domain\Event\StockReleased;
use App\Inventory\Domain\Event\StockReserved;
use App\Inventory\Domain\Event\WarehouseActivated;
use App\Inventory\Domain\Event\WarehouseDeactivated;
use App\Inventory\Domain\Event\WarehouseUpdated;
use App\Inventory\Domain\Model\Quantity as InventoryQuantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Invoice\Domain\Event\InvoiceCancelled;
use App\Invoice\Domain\Event\InvoiceCredited;
use App\Invoice\Domain\Event\InvoicePaid;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Notifications\Domain\Event\NotificationFailed;
use App\Notifications\Domain\Event\NotificationSent;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Model\NotificationType;
use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Event\OrderPaid;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;
use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Privacy\Domain\Event\ConsentWithdrawn;
use App\Privacy\Domain\Event\DataErasureRequested;
use App\Privacy\Domain\Event\DataSubjectRequestCompleted;
use App\Privacy\Domain\Event\DataSubjectRequestSubmitted;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Domain\ValueObject\ConsentPurpose;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Returns\Domain\Event\ReturnRequestApproved;
use App\Returns\Domain\Event\ReturnRequestCompleted;
use App\Returns\Domain\Event\ReturnRequestInspected;
use App\Returns\Domain\Event\ReturnRequestReceived;
use App\Returns\Domain\Event\ReturnRequestRejected;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Review\Domain\Event\ReviewApproved;
use App\Review\Domain\Event\ReviewRejected;
use App\Review\Domain\Event\ReviewSubmitted;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Event\FeatureFlagConfigurationUpdated;
use App\Tenant\Domain\Event\FeatureFlagCreated;
use App\Tenant\Domain\Event\FeatureFlagToggled;
use App\Tenant\Domain\Event\TenantDeactivated;
use App\Tenant\Domain\Event\TenantReactivated;
use App\Tenant\Domain\Event\TenantSuspended;
use App\Tenant\Domain\Event\TenantUpdated;
use App\Tenant\Domain\Model\FeatureFlagId;
use App\Tenant\Domain\ValueObject\TenantName;
use App\User\Domain\Event\UserCreated;
use App\User\Domain\ValueObject\UserId;
use App\Wishlist\Domain\Event\ItemRemovedFromWishlist;
use App\Wishlist\Domain\Event\WishlistCleared;
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

final class DomainEventAuditSubscriberExtraTest extends TestCase
{
    private MessageBusInterface $commandBus;
    private TokenStorageInterface $tokenStorage;
    private RequestStack $requestStack;
    private DomainEventAuditSubscriber $subscriber;

    /** @var list<LogAuditEntry> */
    private array $dispatchedCommands = [];

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const SYSTEM_TENANT_ID = '00000000-0000-0000-0000-000000000000';
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

    // ──────────────────────────────────────────────
    // User Events
    // ──────────────────────────────────────────────

    public function testUserCreatedSubscriberBug(): void
    {
        // The subscriber's onUserCreated() accesses $event->userId (property syntax) and
        // $event->email / $event->username, but UserCreated declares all three as private
        // readonly properties. Accessing a private property from outside the declaring class
        // throws an Error. Because the Error is raised while evaluating the argument list for
        // logEvent(), it propagates before the try/catch inside logEvent() can intercept it.
        // This test documents that known subscriber bug.
        $event = new UserCreated(
            userId: UserId::generate(),
            email: 'test@example.com',
            username: 'testuser',
            occurredOn: new \DateTimeImmutable(),
        );

        $this->expectException(\Error::class);
        $this->subscriber->onUserCreated($event);
    }

    // ──────────────────────────────────────────────
    // Order Events
    // ──────────────────────────────────────────────

    public function testOrderHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $orderId = OrderId::generate();

        // OrderPlaced
        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: 'customer@example.com',
            total: Money::fromScalars(9999, 'USD'),
        );

        $this->subscriber->onOrderPlaced($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('place', $command->actionType);
        $this->assertSame('order', $command->resourceType);
        $this->assertSame($orderId->toString(), $command->resourceId);
        $this->assertSame('customer@example.com', $command->metadata['customerEmail']);
        $this->assertSame('OrderPlaced', $command->metadata['event']);

        // OrderPaid – tested separately in testOrderPaidSubscriberBug below

        // OrderStatusChanged
        $this->dispatchedCommands = [];
        $orderStatusChanged = new OrderStatusChanged(
            orderId: $orderId,
            tenantId: $tenantId,
            oldStatus: OrderStatus::pending(),
            newStatus: OrderStatus::processing(),
        );

        $this->subscriber->onOrderStatusChanged($orderStatusChanged);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('update', $command->actionType);
        $this->assertSame('order', $command->resourceType);
        $this->assertSame('pending', $command->metadata['oldStatus']);
        $this->assertSame('processing', $command->metadata['newStatus']);
        $this->assertSame('OrderStatusChanged', $command->metadata['event']);

        // OrderCancelled
        $this->dispatchedCommands = [];
        $orderCancelled = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: OrderStatus::processing(),
            reason: 'Customer request',
        );

        $this->subscriber->onOrderCancelled($orderCancelled);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('cancel', $command->actionType);
        $this->assertSame('order', $command->resourceType);
        $this->assertSame('Customer request', $command->metadata['reason']);
        $this->assertSame('OrderCancelled', $command->metadata['event']);
    }

    public function testOrderPaidSubscriberBug(): void
    {
        // The subscriber's onOrderPaid() calls $event->orderId->toString() but
        // OrderPaid::$orderId is declared as public string – calling ->toString() on a
        // primitive string throws an Error. This Error is raised in the ARGUMENT expression
        // before logEvent()'s try/catch can intercept it, so it propagates out of the
        // handler uncaught. This test documents that known subscriber bug.
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $orderId = OrderId::generate();
        $orderPaid = new OrderPaid(
            orderId: $orderId->toString(),
            tenantId: $tenantId,
            paymentId: 'payment-abc',
            paidAmountInCents: 9999,
            occurredOn: new \DateTimeImmutable(),
        );

        $this->expectException(\Error::class);
        $this->subscriber->onOrderPaid($orderPaid);
    }

    // ──────────────────────────────────────────────
    // Payment Events
    // ──────────────────────────────────────────────

    public function testPaymentAuthorizedCreatesAuditRecord(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $event = new PaymentAuthorized(
            paymentId: $paymentId,
            tenantId: $tenantId,
            gatewayTransactionId: 'txn_abc123',
        );

        $this->subscriber->onPaymentAuthorized($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('authorize', $command->actionType);
        $this->assertSame('payment', $command->resourceType);
        $this->assertSame($paymentId->toString(), $command->resourceId);
        $this->assertSame('txn_abc123', $command->metadata['gatewayTransactionId']);
        $this->assertSame('PaymentAuthorized', $command->metadata['event']);
        $this->assertSame(self::USER_ID, $command->userId);
        $this->assertSame(self::IP_ADDRESS, $command->ipAddress);
        $this->assertSame(self::USER_AGENT, $command->userAgent);
    }

    // ──────────────────────────────────────────────
    // Review Events (subscriber/event mismatch – bugs documented)
    // ──────────────────────────────────────────────

    public function testReviewSubmittedSubscriberBug(): void
    {
        // The subscriber's onReviewSubmitted() accesses $event->tenantId->toString(),
        // $event->customerId->toString() etc., but the actual ReviewSubmitted event has no
        // tenantId property and customerId is typed as ?string (not an object).
        // The property access for tenantId on a real ReviewSubmitted object returns null
        // (undefined/non-existent property), causing a TypeError when ->toString() is called.
        // This is a known subscriber/event mismatch bug.
        class_exists(\App\Review\Domain\Model\ProductReview::class);

        $event = new ReviewSubmitted(
            reviewId: \App\Review\Domain\Model\ReviewId::generate(),
            productId: ProductId::generate(),
            customerId: 'customer-001',
            rating: \App\Review\Domain\ValueObject\Rating::fromInt(4),
            title: 'Great product',
            content: 'Very happy with this.',
            occurredAt: new \DateTimeImmutable(),
        );

        $this->expectException(\Throwable::class);
        $this->subscriber->onReviewSubmitted($event);
    }

    public function testReviewApprovedSubscriberBug(): void
    {
        // The subscriber's onReviewApproved() accesses $event->tenantId->toString() and
        // $event->approvedBy->toString(), but ReviewApproved has no such properties.
        // Accessing undefined properties returns null, and calling ->toString() on null
        // throws a TypeError. This is a known subscriber/event mismatch bug.
        class_exists(\App\Review\Domain\Model\ProductReview::class);

        $event = new ReviewApproved(
            reviewId: \App\Review\Domain\Model\ReviewId::generate(),
            occurredAt: new \DateTimeImmutable(),
        );

        $this->expectException(\Throwable::class);
        $this->subscriber->onReviewApproved($event);
    }

    public function testReviewRejectedSubscriberBug(): void
    {
        // The subscriber's onReviewRejected() accesses $event->tenantId->toString() and
        // $event->rejectedBy->toString(), but ReviewRejected has no such properties.
        // Accessing undefined properties returns null, and calling ->toString() on null
        // throws a TypeError. This is a known subscriber/event mismatch bug.
        class_exists(\App\Review\Domain\Model\ProductReview::class);

        $event = new ReviewRejected(
            reviewId: \App\Review\Domain\Model\ReviewId::generate(),
            reason: 'Inappropriate content',
            occurredAt: new \DateTimeImmutable(),
        );

        $this->expectException(\Throwable::class);
        $this->subscriber->onReviewRejected($event);
    }

    // ──────────────────────────────────────────────
    // Catalog Product Events
    // ──────────────────────────────────────────────

    public function testProductLifecycleHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $now = new \DateTimeImmutable();

        // ProductUpdated
        $event = new ProductUpdated(productId: $productId, tenantId: $tenantId);
        $this->subscriber->onProductUpdated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('product', $command->resourceType);
        $this->assertSame($productId->toString(), $command->resourceId);
        $this->assertSame('ProductUpdated', $command->metadata['event']);

        // ProductPublished
        $this->dispatchedCommands = [];
        $event = new ProductPublished(productId: $productId, tenantId: $tenantId, occurredAt: $now);
        $this->subscriber->onProductPublished($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('publish', $command->actionType);
        $this->assertSame('ProductPublished', $command->metadata['event']);

        // ProductDeactivated
        $this->dispatchedCommands = [];
        $event = new ProductDeactivated(productId: $productId, tenantId: $tenantId, occurredAt: $now);
        $this->subscriber->onProductDeactivated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('deactivate', $command->actionType);
        $this->assertSame('ProductDeactivated', $command->metadata['event']);

        // ProductReactivated
        $this->dispatchedCommands = [];
        $event = new ProductReactivated(productId: $productId, tenantId: $tenantId, occurredAt: $now);
        $this->subscriber->onProductReactivated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('reactivate', $command->actionType);
        $this->assertSame('ProductReactivated', $command->metadata['event']);

        // ProductDiscontinued
        $this->dispatchedCommands = [];
        $event = new ProductDiscontinued(productId: $productId, tenantId: $tenantId, occurredAt: $now);
        $this->subscriber->onProductDiscontinued($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('discontinue', $command->actionType);
        $this->assertSame('ProductDiscontinued', $command->metadata['event']);
    }

    public function testProductTypeChangedCreatesAuditRecord(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $event = new ProductTypeChanged(
            productId: $productId,
            tenantId: $tenantId,
            oldType: ProductType::simple(),
            newType: ProductType::configurable(),
            occurredAt: new \DateTimeImmutable(),
        );

        $this->subscriber->onProductTypeChanged($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('product', $command->resourceType);
        $this->assertSame('simple', $command->metadata['oldType']);
        $this->assertSame('configurable', $command->metadata['newType']);
        $this->assertSame('ProductTypeChanged', $command->metadata['event']);
    }

    public function testProductTranslationsUpdatedCreatesAuditRecord(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();

        $event = new ProductTranslationsUpdated(
            productId: $productId,
            tenantId: $tenantId,
            locale: Locale::fromString('en_US'),
        );

        $this->subscriber->onProductTranslationsUpdated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('translate', $command->actionType);
        $this->assertSame('product', $command->resourceType);
        $this->assertSame($productId->toString(), $command->resourceId);
        $this->assertSame('en_US', $command->metadata['locale']);
        $this->assertSame('ProductTranslationsUpdated', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Catalog Category Events
    // ──────────────────────────────────────────────

    public function testCategoryHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $categoryId = CategoryId::generate();

        // CategoryUpdated
        $event = new CategoryUpdated(categoryId: $categoryId, tenantId: $tenantId);
        $this->subscriber->onCategoryUpdated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('category', $command->resourceType);
        $this->assertSame($categoryId->toString(), $command->resourceId);
        $this->assertSame('CategoryUpdated', $command->metadata['event']);

        // CategoryTranslationsUpdated
        $this->dispatchedCommands = [];
        $translEvent = new CategoryTranslationsUpdated(
            categoryId: $categoryId,
            tenantId: $tenantId,
            locale: Locale::fromString('fr_FR'),
        );

        $this->subscriber->onCategoryTranslationsUpdated($translEvent);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('translate', $command->actionType);
        $this->assertSame('category', $command->resourceType);
        $this->assertSame($categoryId->toString(), $command->resourceId);
        $this->assertSame('fr_FR', $command->metadata['locale']);
        $this->assertSame('CategoryTranslationsUpdated', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Bundle Events
    // ──────────────────────────────────────────────

    public function testBundleHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $bundleProductId = ProductId::generate();
        $itemProductId = ProductId::generate();
        $now = new \DateTimeImmutable();

        $bundleItem = BundleItem::create(
            productId: $itemProductId,
            quantity: 2,
            price: Money::fromScalars(1999, 'USD'),
        );

        // BundleCreated
        $event = new BundleCreated(
            productId: $bundleProductId,
            tenantId: $tenantId,
            items: [$bundleItem],
            discountPercentage: 10.0,
            occurredOn: $now,
        );

        $this->subscriber->onBundleCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('bundle', $command->resourceType);
        $this->assertSame($bundleProductId->toString(), $command->resourceId);
        $this->assertSame(10.0, $command->metadata['discountPercentage']);
        $this->assertSame('BundleCreated', $command->metadata['event']);

        // BundleUpdated
        $this->dispatchedCommands = [];
        $bundleUpdated = new BundleUpdated(
            productId: $bundleProductId,
            tenantId: $tenantId,
            oldBundle: $this->createMock(\App\Catalog\Domain\ValueObject\Bundle::class),
            newBundle: $this->createMock(\App\Catalog\Domain\ValueObject\Bundle::class),
            occurredOn: $now,
        );

        $this->subscriber->onBundleUpdated($bundleUpdated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('update', $command->actionType);
        $this->assertSame('bundle', $command->resourceType);
        $this->assertSame('BundleUpdated', $command->metadata['event']);

        // BundleItemAdded
        $this->dispatchedCommands = [];
        $bundleItemAdded = new BundleItemAdded(
            productId: $bundleProductId,
            tenantId: $tenantId,
            item: $bundleItem,
            occurredOn: $now,
        );

        $this->subscriber->onBundleItemAdded($bundleItemAdded);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('update', $command->actionType);
        $this->assertSame('bundle', $command->resourceType);
        $this->assertSame('BundleItemAdded', $command->metadata['event']);

        // BundleItemRemoved
        $this->dispatchedCommands = [];
        $removedProductId = ProductId::generate();
        $bundleItemRemoved = new BundleItemRemoved(
            bundleProductId: $bundleProductId,
            tenantId: $tenantId,
            removedProductId: $removedProductId,
            occurredOn: $now,
        );

        $this->subscriber->onBundleItemRemoved($bundleItemRemoved);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('update', $command->actionType);
        $this->assertSame('bundle', $command->resourceType);
        $this->assertSame($bundleProductId->toString(), $command->resourceId);
        $this->assertSame($removedProductId->toString(), $command->metadata['removedProductId']);
        $this->assertSame('BundleItemRemoved', $command->metadata['event']);

        // BundleRemoved
        $this->dispatchedCommands = [];
        $bundleRemoved = new BundleRemoved(
            productId: $bundleProductId,
            tenantId: $tenantId,
            removedBundle: $this->createMock(\App\Catalog\Domain\ValueObject\Bundle::class),
            occurredOn: $now,
        );

        $this->subscriber->onBundleRemoved($bundleRemoved);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('delete', $command->actionType);
        $this->assertSame('bundle', $command->resourceType);
        $this->assertSame('BundleRemoved', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Subscription Events
    // ──────────────────────────────────────────────

    public function testSubscriptionHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $now = new \DateTimeImmutable();

        // SubscriptionConfigured
        $event = new SubscriptionConfigured(
            productId: $productId,
            tenantId: $tenantId,
            interval: SubscriptionInterval::MONTHLY,
            billingCycles: 12,
            occurredOn: $now,
        );

        $this->subscriber->onSubscriptionConfigured($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('subscription_config', $command->resourceType);
        $this->assertSame($productId->toString(), $command->resourceId);
        $this->assertSame('monthly', $command->metadata['interval']);
        $this->assertSame(12, $command->metadata['billingCycles']);
        $this->assertSame('SubscriptionConfigured', $command->metadata['event']);

        // SubscriptionUpdated
        $this->dispatchedCommands = [];
        $subUpdated = new SubscriptionUpdated(
            productId: $productId,
            tenantId: $tenantId,
            oldSubscription: $this->createMock(\App\Catalog\Domain\ValueObject\Subscription::class),
            newSubscription: $this->createMock(\App\Catalog\Domain\ValueObject\Subscription::class),
            occurredOn: $now,
        );

        $this->subscriber->onSubscriptionUpdated($subUpdated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('update', $command->actionType);
        $this->assertSame('subscription_config', $command->resourceType);
        $this->assertSame('SubscriptionUpdated', $command->metadata['event']);

        // SubscriptionRemoved
        $this->dispatchedCommands = [];
        $subRemoved = new SubscriptionRemoved(
            productId: $productId,
            tenantId: $tenantId,
            subscription: $this->createMock(\App\Catalog\Domain\ValueObject\Subscription::class),
            occurredOn: $now,
        );

        $this->subscriber->onSubscriptionRemoved($subRemoved);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('delete', $command->actionType);
        $this->assertSame('subscription_config', $command->resourceType);
        $this->assertSame('SubscriptionRemoved', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Variant Events
    // ──────────────────────────────────────────────

    public function testVariantHandlers(): void
    {
        // ConfigurableProductId and VariantId are defined at the bottom of their parent class
        // files (ConfigurableProduct.php and Variant.php). PSR-4 maps these class names to
        // non-existent standalone files. Loading the parent class files makes them available.
        class_exists(\App\Catalog\Domain\Model\ConfigurableProduct::class);
        class_exists(\App\Catalog\Domain\Model\Variant::class);

        $configurableProductId = ConfigurableProductId::generate();
        $productId = ProductId::generate();
        $variantId = VariantId::generate();
        $sku = VariantSKU::fromString('CLO-NIK-000001-red-xl');

        // VariantAdded
        $event = new VariantAdded(
            configurableProductId: $configurableProductId,
            productId: $productId,
            variantId: $variantId,
            sku: $sku,
            optionValueMap: ['color' => 'red', 'size' => 'xl'],
        );

        $this->subscriber->onVariantAdded($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('product_variant', $command->resourceType);
        $this->assertSame($variantId->toString(), $command->resourceId);
        $this->assertSame($productId->toString(), $command->metadata['productId']);
        $this->assertSame($sku->toString(), $command->metadata['sku']);
        $this->assertSame('VariantAdded', $command->metadata['event']);

        // VariantRemoved
        $this->dispatchedCommands = [];
        $variantRemoved = new VariantRemoved(
            configurableProductId: $configurableProductId,
            productId: $productId,
            variantId: $variantId,
        );

        $this->subscriber->onVariantRemoved($variantRemoved);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('delete', $command->actionType);
        $this->assertSame('product_variant', $command->resourceType);
        $this->assertSame($variantId->toString(), $command->resourceId);
        $this->assertSame($productId->toString(), $command->metadata['productId']);
        $this->assertSame('VariantRemoved', $command->metadata['event']);

        // VariantsGenerated
        $this->dispatchedCommands = [];
        $variantsGenerated = new VariantsGenerated(
            configurableProductId: $configurableProductId,
            productId: $productId,
            variantsCount: 4,
        );

        $this->subscriber->onVariantsGenerated($variantsGenerated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('product_variant', $command->resourceType);
        $this->assertSame($productId->toString(), $command->resourceId);
        $this->assertSame($configurableProductId->toString(), $command->metadata['configurableProductId']);
        $this->assertSame(4, $command->metadata['variantsCount']);
        $this->assertSame('VariantsGenerated', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Option Events
    // ──────────────────────────────────────────────

    public function testOptionHandlers(): void
    {
        // Ensure ConfigurableProductId is available (defined inside ConfigurableProduct.php)
        class_exists(\App\Catalog\Domain\Model\ConfigurableProduct::class);

        $configurableProductId = ConfigurableProductId::generate();
        $productId = ProductId::generate();
        $optionCode = OptionCode::fromString('color');

        // OptionDefined
        $event = new OptionDefined(
            configurableProductId: $configurableProductId,
            productId: $productId,
            optionCode: $optionCode,
            nameTranslations: LocalizedString::fromArray(['en' => 'Color', 'fr' => 'Couleur']),
        );

        $this->subscriber->onOptionDefined($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('product_option', $command->resourceType);
        $this->assertSame($productId->toString(), $command->resourceId);
        $this->assertSame($configurableProductId->toString(), $command->metadata['configurableProductId']);
        $this->assertSame('color', $command->metadata['optionCode']);
        $this->assertSame('OptionDefined', $command->metadata['event']);

        // OptionRemoved
        $this->dispatchedCommands = [];
        $optionRemoved = new OptionRemoved(
            configurableProductId: $configurableProductId,
            productId: $productId,
            optionCode: $optionCode,
        );

        $this->subscriber->onOptionRemoved($optionRemoved);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('delete', $command->actionType);
        $this->assertSame('product_option', $command->resourceType);
        $this->assertSame($productId->toString(), $command->resourceId);
        $this->assertSame($configurableProductId->toString(), $command->metadata['configurableProductId']);
        $this->assertSame('color', $command->metadata['optionCode']);
        $this->assertSame('OptionRemoved', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Cart Events
    // ──────────────────────────────────────────────

    public function testCartHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $cartId = CartId::generate();
        $productId = ProductId::generate();
        $cartItemId = CartItemId::generate();
        $now = new \DateTimeImmutable();

        // CartCleared
        $event = new CartCleared(cartId: $cartId, tenantId: $tenantId);
        $this->subscriber->onCartCleared($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('clear', $command->actionType);
        $this->assertSame('cart', $command->resourceType);
        $this->assertSame($cartId->toString(), $command->resourceId);
        $this->assertSame('CartCleared', $command->metadata['event']);

        // CartAbandoned
        $this->dispatchedCommands = [];
        $cartAbandoned = new CartAbandoned(
            cartId: $cartId,
            tenantId: $tenantId,
            customerId: null,
            customerEmail: 'guest@example.com',
            cartTotal: Money::fromScalars(4999, 'USD'),
            itemCount: 3,
            abandonedAt: $now,
        );

        $this->subscriber->onCartAbandoned($cartAbandoned);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('abandon', $command->actionType);
        $this->assertSame('cart', $command->resourceType);
        $this->assertSame('guest@example.com', $command->metadata['customerEmail']);
        $this->assertSame(3, $command->metadata['itemCount']);
        $this->assertSame('CartAbandoned', $command->metadata['event']);

        // ItemRemovedFromCart
        $this->dispatchedCommands = [];
        $itemRemoved = new ItemRemovedFromCart(
            cartId: $cartId,
            tenantId: $tenantId,
            cartItemId: $cartItemId,
            productId: $productId,
            variantId: null,
        );

        $this->subscriber->onItemRemovedFromCart($itemRemoved);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('remove_item', $command->actionType);
        $this->assertSame('cart', $command->resourceType);
        $this->assertSame($productId->toString(), $command->metadata['productId']);
        $this->assertSame('ItemRemovedFromCart', $command->metadata['event']);

        // CartQuantityUpdated
        $this->dispatchedCommands = [];
        $cartQtyUpdated = new CartQuantityUpdated(
            cartId: $cartId,
            tenantId: $tenantId,
            cartItemId: $cartItemId,
            productId: $productId,
            variantId: null,
            newQuantity: CartQuantity::fromInt(5),
        );

        $this->subscriber->onCartQuantityUpdated($cartQtyUpdated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('update', $command->actionType);
        $this->assertSame('cart', $command->resourceType);
        $this->assertSame($productId->toString(), $command->metadata['productId']);
        $this->assertSame(5, $command->metadata['newQuantity']);
        $this->assertSame('CartQuantityUpdated', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Customer Events
    // ──────────────────────────────────────────────

    public function testCustomerBaseHandlers(): void
    {
        $customerId = CustomerId::generate();

        // CustomerActivated (tenantId=null -> system tenant)
        $event = new CustomerActivated(customerId: $customerId);
        $this->subscriber->onCustomerActivated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('activate', $command->actionType);
        $this->assertSame('customer', $command->resourceType);
        $this->assertSame($customerId->toString(), $command->resourceId);
        $this->assertSame('CustomerActivated', $command->metadata['event']);

        // CustomerDeactivated
        $this->dispatchedCommands = [];
        $deactivated = new CustomerDeactivated(customerId: $customerId);
        $this->subscriber->onCustomerDeactivated($deactivated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('deactivate', $command->actionType);
        $this->assertSame('CustomerDeactivated', $command->metadata['event']);

        // CustomerUpdated
        $this->dispatchedCommands = [];
        $updated = new CustomerUpdated(
            customerId: $customerId,
            firstName: 'Jane',
            lastName: 'Doe',
            phoneNumber: '+1234567890',
        );

        $this->subscriber->onCustomerUpdated($updated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('customer', $command->resourceType);
        $this->assertSame('Jane', $command->metadata['firstName']);
        $this->assertSame('Doe', $command->metadata['lastName']);
        $this->assertSame('CustomerUpdated', $command->metadata['event']);
    }

    public function testCustomerConsentAndPreferencesHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $customerId = CustomerId::generate();

        // CustomerConsentChanged
        $event = new CustomerConsentChanged(
            customerId: $customerId,
            tenantId: $tenantId,
            consentType: ConsentType::MARKETING_EMAIL,
            granted: true,
            ipAddress: '10.0.0.1',
            occurredAt: new \DateTimeImmutable(),
        );

        $this->subscriber->onCustomerConsentChanged($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('customer_consent', $command->resourceType);
        $this->assertSame($customerId->toString(), $command->resourceId);
        $this->assertSame('marketing_email', $command->metadata['consentType']);
        $this->assertTrue($command->metadata['granted']);
        $this->assertSame('CustomerConsentChanged', $command->metadata['event']);

        // CustomerPreferencesUpdated
        $this->dispatchedCommands = [];
        $prefUpdated = new CustomerPreferencesUpdated(
            customerId: $customerId,
            tenantId: $tenantId,
            oldPreferences: ['language' => 'en'],
            newPreferences: ['language' => 'fr'],
            occurredAt: new \DateTimeImmutable(),
        );

        $this->subscriber->onCustomerPreferencesUpdated($prefUpdated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('customer_preferences', $command->resourceType);
        $this->assertSame('CustomerPreferencesUpdated', $command->metadata['event']);

        // CustomerSegmentChanged
        $this->dispatchedCommands = [];
        $segmentChanged = new CustomerSegmentChanged(
            customerId: $customerId,
            oldSegment: CustomerSegment::regular(),
            newSegment: CustomerSegment::vip(),
        );

        $this->subscriber->onCustomerSegmentChanged($segmentChanged);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('customer_segment', $command->resourceType);
        $this->assertSame('regular', $command->metadata['oldSegment']);
        $this->assertSame('vip', $command->metadata['newSegment']);
        $this->assertSame('CustomerSegmentChanged', $command->metadata['event']);
    }

    public function testCustomerAddressHandlers(): void
    {
        $customerId = CustomerId::generate();
        $addressId = CustomerAddressId::generate();

        $address = CustomerAddress::create(
            id: $addressId,
            street: '123 Main St',
            street2: null,
            city: 'Springfield',
            state: 'IL',
            postalCode: '62701',
            country: 'US',
            type: AddressType::SHIPPING,
        );

        // CustomerAddressAdded
        $event = new CustomerAddressAdded(customerId: $customerId, address: $address);
        $this->subscriber->onCustomerAddressAdded($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('customer_address', $command->resourceType);
        $this->assertSame($customerId->toString(), $command->resourceId);
        $this->assertSame('CustomerAddressAdded', $command->metadata['event']);

        // CustomerAddressUpdated
        $this->dispatchedCommands = [];
        $addressUpdated = new CustomerAddressUpdated(
            customerId: $customerId,
            addressId: $addressId,
            newAddress: $address,
        );

        $this->subscriber->onCustomerAddressUpdated($addressUpdated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('customer_address', $command->resourceType);
        $this->assertSame($addressId->toString(), $command->metadata['addressId']);
        $this->assertSame('CustomerAddressUpdated', $command->metadata['event']);

        // CustomerAddressRemoved
        $this->dispatchedCommands = [];
        $addressRemoved = new CustomerAddressRemoved(
            customerId: $customerId,
            addressId: $addressId,
        );

        $this->subscriber->onCustomerAddressRemoved($addressRemoved);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('delete', $command->actionType);
        $this->assertSame('customer_address', $command->resourceType);
        $this->assertSame($addressId->toString(), $command->metadata['addressId']);
        $this->assertSame('CustomerAddressRemoved', $command->metadata['event']);

        // DefaultAddressChanged
        $this->dispatchedCommands = [];
        $defaultChanged = new DefaultAddressChanged(
            customerId: $customerId,
            addressId: $addressId,
            type: 'shipping',
        );

        $this->subscriber->onDefaultAddressChanged($defaultChanged);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('customer_address', $command->resourceType);
        $this->assertSame($addressId->toString(), $command->metadata['addressId']);
        $this->assertSame('shipping', $command->metadata['type']);
        $this->assertSame('DefaultAddressChanged', $command->metadata['event']);
    }

    public function testNotificationPreferencesUpdatedCreatesAuditRecord(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $customerId = CustomerId::generate();

        $event = new NotificationPreferencesUpdated(
            customerId: $customerId,
            tenantId: $tenantId,
            oldPreferences: ['email' => true, 'sms' => false],
            newPreferences: ['email' => true, 'sms' => true],
            occurredAt: new \DateTimeImmutable(),
        );

        $this->subscriber->onNotificationPreferencesUpdated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('notification_preferences', $command->resourceType);
        $this->assertSame($customerId->toString(), $command->resourceId);
        $this->assertSame('NotificationPreferencesUpdated', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Loyalty Events
    // ──────────────────────────────────────────────

    public function testLoyaltyPointsHandlers(): void
    {
        $customerId = CustomerId::generate();

        // LoyaltyPointsAwarded
        $event = new LoyaltyPointsAwarded(
            customerId: $customerId,
            pointsAwarded: 100,
            newTotalPoints: 500,
            reason: 'Order purchase',
        );

        $this->subscriber->onLoyaltyPointsAwarded($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('award', $command->actionType);
        $this->assertSame('loyalty_points', $command->resourceType);
        $this->assertSame($customerId->toString(), $command->resourceId);
        $this->assertSame(100, $command->metadata['pointsAwarded']);
        $this->assertSame(500, $command->metadata['newTotalPoints']);
        $this->assertSame('Order purchase', $command->metadata['reason']);
        $this->assertSame('LoyaltyPointsAwarded', $command->metadata['event']);

        // LoyaltyPointsRedeemed
        $this->dispatchedCommands = [];
        $redeemed = new LoyaltyPointsRedeemed(
            customerId: $customerId,
            pointsRedeemed: 200,
            remainingBalance: 300,
            discountAmount: Money::fromScalars(500, 'USD'),
            orderId: 'order-xyz',
            occurredOn: new \DateTimeImmutable(),
        );

        $this->subscriber->onLoyaltyPointsRedeemed($redeemed);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('redeem', $command->actionType);
        $this->assertSame('loyalty_points', $command->resourceType);
        $this->assertSame(200, $command->metadata['pointsRedeemed']);
        $this->assertSame(300, $command->metadata['remainingBalance']);
        $this->assertSame('order-xyz', $command->metadata['orderId']);
        $this->assertSame('LoyaltyPointsRedeemed', $command->metadata['event']);
    }

    public function testLoyaltyProgramHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $programId = LoyaltyProgramId::generate();
        $now = new \DateTimeImmutable();

        // LoyaltyProgramCreated
        $event = new LoyaltyProgramCreated(
            programId: $programId,
            tenantId: $tenantId,
            name: 'Gold Rewards',
            earningRate: EarningRate::fromFloat(1.5),
            occurredAt: $now,
        );

        $this->subscriber->onLoyaltyProgramCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('loyalty_program', $command->resourceType);
        $this->assertSame($programId->toString(), $command->resourceId);
        $this->assertSame('Gold Rewards', $command->metadata['name']);
        $this->assertSame('LoyaltyProgramCreated', $command->metadata['event']);

        // LoyaltyProgramUpdated
        $this->dispatchedCommands = [];
        $programUpdated = new LoyaltyProgramUpdated(
            programId: $programId,
            tenantId: $tenantId,
            changes: ['name' => ['old' => 'Gold', 'new' => 'Gold Rewards']],
            occurredAt: $now,
        );

        $this->subscriber->onLoyaltyProgramUpdated($programUpdated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('update', $command->actionType);
        $this->assertSame('loyalty_program', $command->resourceType);
        $this->assertSame('LoyaltyProgramUpdated', $command->metadata['event']);

        // LoyaltyProgramActivated
        $this->dispatchedCommands = [];
        $programActivated = new LoyaltyProgramActivated(
            programId: $programId,
            tenantId: $tenantId,
            occurredAt: $now,
        );

        $this->subscriber->onLoyaltyProgramActivated($programActivated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('activate', $command->actionType);
        $this->assertSame('loyalty_program', $command->resourceType);
        $this->assertSame('LoyaltyProgramActivated', $command->metadata['event']);

        // LoyaltyProgramDeactivated
        $this->dispatchedCommands = [];
        $programDeactivated = new LoyaltyProgramDeactivated(
            programId: $programId,
            tenantId: $tenantId,
            occurredAt: $now,
        );

        $this->subscriber->onLoyaltyProgramDeactivated($programDeactivated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('deactivate', $command->actionType);
        $this->assertSame('loyalty_program', $command->resourceType);
        $this->assertSame('LoyaltyProgramDeactivated', $command->metadata['event']);
    }

    public function testLoyaltyTierHandlers(): void
    {
        $programId = LoyaltyProgramId::generate();
        $tierId = LoyaltyTierId::generate();
        $now = new \DateTimeImmutable();

        // LoyaltyTierAdded
        $event = new LoyaltyTierAdded(
            programId: $programId,
            tierId: $tierId,
            tierName: 'Silver',
            threshold: 500,
            occurredAt: $now,
        );

        $this->subscriber->onLoyaltyTierAdded($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('loyalty_tier', $command->resourceType);
        $this->assertSame($tierId->toString(), $command->resourceId);
        $this->assertSame($programId->toString(), $command->metadata['programId']);
        $this->assertSame('Silver', $command->metadata['tierName']);
        $this->assertSame(500, $command->metadata['threshold']);
        $this->assertSame('LoyaltyTierAdded', $command->metadata['event']);

        // LoyaltyTierUpdated
        $this->dispatchedCommands = [];
        $tierUpdated = new LoyaltyTierUpdated(
            programId: $programId,
            tierId: $tierId,
            changes: ['threshold' => ['old' => 500, 'new' => 750]],
            occurredAt: $now,
        );

        $this->subscriber->onLoyaltyTierUpdated($tierUpdated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('loyalty_tier', $command->resourceType);
        $this->assertSame($tierId->toString(), $command->resourceId);
        $this->assertSame($programId->toString(), $command->metadata['programId']);
        $this->assertSame('LoyaltyTierUpdated', $command->metadata['event']);

        // LoyaltyTierRemoved
        $this->dispatchedCommands = [];
        $tierRemoved = new LoyaltyTierRemoved(
            programId: $programId,
            tierId: $tierId,
            occurredAt: $now,
        );

        $this->subscriber->onLoyaltyTierRemoved($tierRemoved);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('delete', $command->actionType);
        $this->assertSame('loyalty_tier', $command->resourceType);
        $this->assertSame($tierId->toString(), $command->resourceId);
        $this->assertSame($programId->toString(), $command->metadata['programId']);
        $this->assertSame('LoyaltyTierRemoved', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Account Deletion Events
    // ──────────────────────────────────────────────

    public function testAccountDeletionHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $customerId = CustomerId::generate();
        $requestId = DeletionRequestId::generate();
        $now = new \DateTimeImmutable();

        // AccountDeletionCancelled
        $event = new AccountDeletionCancelled(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
        );

        $this->subscriber->onAccountDeletionCancelled($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('cancel', $command->actionType);
        $this->assertSame('account_deletion', $command->resourceType);
        $this->assertSame($requestId->toString(), $command->resourceId);
        $this->assertSame($customerId->toString(), $command->metadata['customerId']);
        $this->assertSame('AccountDeletionCancelled', $command->metadata['event']);

        // AccountDeletionConfirmed
        $this->dispatchedCommands = [];
        $confirmed = new AccountDeletionConfirmed(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            scheduledFor: $now->modify('+30 days'),
        );

        $this->subscriber->onAccountDeletionConfirmed($confirmed);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('confirm', $command->actionType);
        $this->assertSame('account_deletion', $command->resourceType);
        $this->assertSame($customerId->toString(), $command->metadata['customerId']);
        $this->assertArrayHasKey('scheduledFor', $command->metadata);
        $this->assertSame('AccountDeletionConfirmed', $command->metadata['event']);

        // AccountDeletionHeld
        $this->dispatchedCommands = [];
        $held = new AccountDeletionHeld(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            holdReason: 'Pending dispute',
        );

        $this->subscriber->onAccountDeletionHeld($held);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('hold', $command->actionType);
        $this->assertSame('account_deletion', $command->resourceType);
        $this->assertSame('Pending dispute', $command->metadata['holdReason']);
        $this->assertSame('AccountDeletionHeld', $command->metadata['event']);

        // AccountDeletionCompleted
        $this->dispatchedCommands = [];
        $completed = new AccountDeletionCompleted(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            occurredAt: $now,
        );

        $this->subscriber->onAccountDeletionCompleted($completed);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('complete', $command->actionType);
        $this->assertSame('account_deletion', $command->resourceType);
        $this->assertSame($customerId->toString(), $command->metadata['customerId']);
        $this->assertSame('AccountDeletionCompleted', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Data Export Events
    // ──────────────────────────────────────────────

    public function testDataExportHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $customerId = CustomerId::generate();
        $requestId = DataExportRequestId::generate();
        $now = new \DateTimeImmutable();

        // DataExportRequested
        $event = new DataExportRequested(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            requestedAt: $now,
        );

        $this->subscriber->onDataExportRequested($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('request_export', $command->actionType);
        $this->assertSame('data_export', $command->resourceType);
        $this->assertSame($requestId->toString(), $command->resourceId);
        $this->assertSame($customerId->toString(), $command->metadata['customerId']);
        $this->assertSame('DataExportRequested', $command->metadata['event']);

        // DataExportCompleted
        $this->dispatchedCommands = [];
        $completed = new DataExportCompleted(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            filePath: '/exports/customer-data-123.zip',
            expiresAt: $now->modify('+7 days'),
            completedAt: $now,
        );

        $this->subscriber->onDataExportCompleted($completed);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('complete', $command->actionType);
        $this->assertSame('data_export', $command->resourceType);
        $this->assertSame($requestId->toString(), $command->resourceId);
        $this->assertSame($customerId->toString(), $command->metadata['customerId']);
        $this->assertSame('DataExportCompleted', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Inventory - Stock Events
    // ──────────────────────────────────────────────

    public function testStockHandlers(): void
    {
        $stockItemId = StockItemId::generate();
        $quantity = InventoryQuantity::fromInt(10);
        $now = new \DateTimeImmutable();

        // StockAllocated
        $event = new StockAllocated(
            stockItemId: $stockItemId,
            quantity: $quantity,
            orderId: 'order-001',
            occurredOn: $now,
        );

        $this->subscriber->onStockAllocated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('allocate', $command->actionType);
        $this->assertSame('stock', $command->resourceType);
        $this->assertSame($stockItemId->toString(), $command->resourceId);
        $this->assertSame(10, $command->metadata['quantity']);
        $this->assertSame('order-001', $command->metadata['orderId']);
        $this->assertSame('StockAllocated', $command->metadata['event']);

        // StockReserved
        $this->dispatchedCommands = [];
        $reserved = new StockReserved(
            stockItemId: $stockItemId,
            quantity: $quantity,
            reservationId: 'res-abc',
            occurredOn: $now,
        );

        $this->subscriber->onStockReserved($reserved);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('reserve', $command->actionType);
        $this->assertSame('stock', $command->resourceType);
        $this->assertSame('res-abc', $command->metadata['reservationId']);
        $this->assertSame('StockReserved', $command->metadata['event']);

        // StockReleased
        $this->dispatchedCommands = [];
        $released = new StockReleased(
            stockItemId: $stockItemId,
            quantity: $quantity,
            referenceId: 'ref-001',
            reason: 'Order cancelled',
            occurredOn: $now,
        );

        $this->subscriber->onStockReleased($released);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('release', $command->actionType);
        $this->assertSame('stock', $command->resourceType);
        $this->assertSame('ref-001', $command->metadata['referenceId']);
        $this->assertSame('Order cancelled', $command->metadata['reason']);
        $this->assertSame('StockReleased', $command->metadata['event']);

        // StockDepleted
        $this->dispatchedCommands = [];
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();

        $depleted = new StockDepleted(
            stockItemId: $stockItemId,
            productId: $productId,
            warehouseId: $warehouseId,
            tenantId: $tenantId,
            availableQuantity: InventoryQuantity::fromInt(0),
            threshold: InventoryQuantity::fromInt(5),
            occurredOn: $now,
        );

        $this->subscriber->onStockDepleted($depleted);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('deplete', $command->actionType);
        $this->assertSame('stock', $command->resourceType);
        $this->assertSame($stockItemId->toString(), $command->resourceId);
        $this->assertSame($productId->toString(), $command->metadata['productId']);
        $this->assertSame($warehouseId->toString(), $command->metadata['warehouseId']);
        $this->assertSame(0, $command->metadata['availableQuantity']);
        $this->assertSame(5, $command->metadata['threshold']);
        $this->assertSame('StockDepleted', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Warehouse Events
    // ──────────────────────────────────────────────

    public function testWarehouseHandlers(): void
    {
        $warehouseId = WarehouseId::generate();
        $now = new \DateTimeImmutable();

        // WarehouseActivated
        $event = new WarehouseActivated(warehouseId: $warehouseId, occurredOn: $now);
        $this->subscriber->onWarehouseActivated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('activate', $command->actionType);
        $this->assertSame('warehouse', $command->resourceType);
        $this->assertSame($warehouseId->toString(), $command->resourceId);
        $this->assertSame('WarehouseActivated', $command->metadata['event']);

        // WarehouseDeactivated
        $this->dispatchedCommands = [];
        $deactivated = new WarehouseDeactivated(warehouseId: $warehouseId, occurredOn: $now);
        $this->subscriber->onWarehouseDeactivated($deactivated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('deactivate', $command->actionType);
        $this->assertSame('WarehouseDeactivated', $command->metadata['event']);

        // WarehouseUpdated
        $this->dispatchedCommands = [];
        $updated = new WarehouseUpdated(
            warehouseId: $warehouseId,
            name: WarehouseName::fromString('Main Warehouse'),
            occurredOn: $now,
        );

        $this->subscriber->onWarehouseUpdated($updated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('warehouse', $command->resourceType);
        $this->assertSame($warehouseId->toString(), $command->resourceId);
        $this->assertSame('Main Warehouse', $command->metadata['name']);
        $this->assertSame('WarehouseUpdated', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Tenant Events
    // ──────────────────────────────────────────────

    public function testTenantHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $now = new \DateTimeImmutable();

        // TenantDeactivated
        $event = new TenantDeactivated(tenantId: $tenantId, occurredAt: $now);
        $this->subscriber->onTenantDeactivated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('deactivate', $command->actionType);
        $this->assertSame('tenant', $command->resourceType);
        $this->assertSame(self::TENANT_ID, $command->resourceId);
        $this->assertSame('TenantDeactivated', $command->metadata['event']);

        // TenantSuspended
        $this->dispatchedCommands = [];
        $suspended = new TenantSuspended(tenantId: $tenantId, occurredAt: $now);
        $this->subscriber->onTenantSuspended($suspended);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('suspend', $command->actionType);
        $this->assertSame('tenant', $command->resourceType);
        $this->assertSame('TenantSuspended', $command->metadata['event']);

        // TenantReactivated
        $this->dispatchedCommands = [];
        $reactivated = new TenantReactivated(tenantId: $tenantId, occurredAt: $now);
        $this->subscriber->onTenantReactivated($reactivated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('reactivate', $command->actionType);
        $this->assertSame('tenant', $command->resourceType);
        $this->assertSame('TenantReactivated', $command->metadata['event']);

        // TenantUpdated
        $this->dispatchedCommands = [];
        $updated = new TenantUpdated(
            tenantId: $tenantId,
            name: TenantName::fromString('My Store'),
            ownerEmail: Email::fromString('owner@store.com'),
            occurredAt: $now,
        );

        $this->subscriber->onTenantUpdated($updated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('tenant', $command->resourceType);
        $this->assertSame('My Store', $command->metadata['name']);
        $this->assertSame('owner@store.com', $command->metadata['ownerEmail']);
        $this->assertSame('TenantUpdated', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Feature Flag Events
    // ──────────────────────────────────────────────

    public function testFeatureFlagHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $flagId = FeatureFlagId::generate();

        // FeatureFlagCreated
        $event = new FeatureFlagCreated(
            flagId: $flagId,
            tenantId: $tenantId,
            featureName: 'new-checkout',
            enabled: true,
        );

        $this->subscriber->onFeatureFlagCreated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('create', $command->actionType);
        $this->assertSame('feature_flag', $command->resourceType);
        $this->assertSame($flagId->toString(), $command->resourceId);
        $this->assertSame('new-checkout', $command->metadata['featureName']);
        $this->assertTrue($command->metadata['enabled']);
        $this->assertSame('FeatureFlagCreated', $command->metadata['event']);

        // FeatureFlagToggled
        $this->dispatchedCommands = [];
        $toggled = new FeatureFlagToggled(
            flagId: $flagId,
            tenantId: $tenantId,
            featureName: 'new-checkout',
            enabled: false,
        );

        $this->subscriber->onFeatureFlagToggled($toggled);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('toggle', $command->actionType);
        $this->assertSame('feature_flag', $command->resourceType);
        $this->assertSame($flagId->toString(), $command->resourceId);
        $this->assertFalse($command->metadata['enabled']);
        $this->assertSame('FeatureFlagToggled', $command->metadata['event']);

        // FeatureFlagConfigurationUpdated
        $this->dispatchedCommands = [];
        $configUpdated = new FeatureFlagConfigurationUpdated(
            flagId: $flagId,
            tenantId: $tenantId,
            featureName: 'new-checkout',
        );

        $this->subscriber->onFeatureFlagConfigurationUpdated($configUpdated);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('update', $command->actionType);
        $this->assertSame('feature_flag', $command->resourceType);
        $this->assertSame('new-checkout', $command->metadata['featureName']);
        $this->assertSame('FeatureFlagConfigurationUpdated', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Invoice Events
    // ──────────────────────────────────────────────

    public function testInvoiceHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $invoiceId = InvoiceId::generate();
        $now = new \DateTimeImmutable();

        // InvoicePaid
        $event = new InvoicePaid(
            invoiceId: $invoiceId,
            tenantId: $tenantId,
            paidDate: $now,
            total: Money::fromScalars(12000, 'USD'),
        );

        $this->subscriber->onInvoicePaid($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('pay', $command->actionType);
        $this->assertSame('invoice', $command->resourceType);
        $this->assertSame($invoiceId->toString(), $command->resourceId);
        $this->assertSame(12000, $command->metadata['totalAmount']);
        $this->assertSame('USD', $command->metadata['currency']);
        $this->assertSame('InvoicePaid', $command->metadata['event']);

        // InvoiceCancelled
        $this->dispatchedCommands = [];
        $cancelled = new InvoiceCancelled(invoiceId: $invoiceId, tenantId: $tenantId);
        $this->subscriber->onInvoiceCancelled($cancelled);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('cancel', $command->actionType);
        $this->assertSame('invoice', $command->resourceType);
        $this->assertSame($invoiceId->toString(), $command->resourceId);
        $this->assertSame('InvoiceCancelled', $command->metadata['event']);

        // InvoiceCredited
        $this->dispatchedCommands = [];
        $creditNoteId = InvoiceId::generate();
        $credited = new InvoiceCredited(
            creditNoteId: $creditNoteId,
            originalInvoiceId: $invoiceId,
            tenantId: $tenantId,
            creditNoteNumber: InvoiceNumber::fromString('INV-2025-000001'),
            creditAmount: Money::fromScalars(5000, 'USD'),
            occurredAt: $now,
        );

        $this->subscriber->onInvoiceCredited($credited);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('credit', $command->actionType);
        $this->assertSame('invoice', $command->resourceType);
        $this->assertSame($creditNoteId->toString(), $command->resourceId);
        $this->assertSame($invoiceId->toString(), $command->metadata['originalInvoiceId']);
        $this->assertSame('INV-2025-000001', $command->metadata['creditNoteNumber']);
        $this->assertSame(5000, $command->metadata['creditAmount']);
        $this->assertSame('USD', $command->metadata['currency']);
        $this->assertSame('InvoiceCredited', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Return Request Events
    // ──────────────────────────────────────────────

    public function testReturnRequestHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $returnRequestId = ReturnRequestId::generate();
        $now = new \DateTimeImmutable();

        // ReturnRequestApproved
        $event = new ReturnRequestApproved(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            occurredOn: $now,
        );

        $this->subscriber->onReturnRequestApproved($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('approve', $command->actionType);
        $this->assertSame('return_request', $command->resourceType);
        $this->assertSame($returnRequestId->toString(), $command->resourceId);
        $this->assertSame('ReturnRequestApproved', $command->metadata['event']);

        // ReturnRequestRejected
        $this->dispatchedCommands = [];
        $rejected = new ReturnRequestRejected(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            rejectionReason: 'Outside return window',
            occurredOn: $now,
        );

        $this->subscriber->onReturnRequestRejected($rejected);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('reject', $command->actionType);
        $this->assertSame('return_request', $command->resourceType);
        $this->assertSame('Outside return window', $command->metadata['rejectionReason']);
        $this->assertSame('ReturnRequestRejected', $command->metadata['event']);

        // ReturnRequestReceived
        $this->dispatchedCommands = [];
        $received = new ReturnRequestReceived(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            warehouseId: 'warehouse-001',
            occurredOn: $now,
        );

        $this->subscriber->onReturnRequestReceived($received);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('receive', $command->actionType);
        $this->assertSame('return_request', $command->resourceType);
        $this->assertSame('warehouse-001', $command->metadata['warehouseId']);
        $this->assertSame('ReturnRequestReceived', $command->metadata['event']);

        // ReturnRequestInspected
        $this->dispatchedCommands = [];
        $inspected = new ReturnRequestInspected(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            isResellable: true,
            inspectionNotes: 'Item in good condition',
            occurredOn: $now,
        );

        $this->subscriber->onReturnRequestInspected($inspected);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('inspect', $command->actionType);
        $this->assertSame('return_request', $command->resourceType);
        $this->assertTrue($command->metadata['isResellable']);
        $this->assertSame('Item in good condition', $command->metadata['inspectionNotes']);
        $this->assertSame('ReturnRequestInspected', $command->metadata['event']);

        // ReturnRequestCompleted
        $this->dispatchedCommands = [];
        $completed = new ReturnRequestCompleted(
            returnRequestId: $returnRequestId,
            tenantId: $tenantId,
            refundAmount: 4999,
            refundCurrency: 'USD',
            occurredOn: $now,
        );

        $this->subscriber->onReturnRequestCompleted($completed);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('complete', $command->actionType);
        $this->assertSame('return_request', $command->resourceType);
        $this->assertSame(4999, $command->metadata['refundAmount']);
        $this->assertSame('USD', $command->metadata['refundCurrency']);
        $this->assertSame('ReturnRequestCompleted', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Privacy Events
    // ──────────────────────────────────────────────

    public function testPrivacyHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $customerId = CustomerId::generate();
        $consentId = ConsentId::generate();
        $requestId = DataSubjectRequestId::generate();
        $now = new \DateTimeImmutable();

        // ConsentWithdrawn
        $event = new ConsentWithdrawn(
            consentId: $consentId,
            customerId: $customerId,
            purpose: ConsentPurpose::marketing(),
            tenantId: $tenantId,
            occurredOn: $now,
        );

        $this->subscriber->onConsentWithdrawn($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('withdraw', $command->actionType);
        $this->assertSame('consent', $command->resourceType);
        $this->assertSame($consentId->toString(), $command->resourceId);
        $this->assertSame($customerId->toString(), $command->metadata['customerId']);
        $this->assertSame('marketing', $command->metadata['purpose']);
        $this->assertSame('ConsentWithdrawn', $command->metadata['event']);

        // DataErasureRequested
        $this->dispatchedCommands = [];
        $erasureRequested = new DataErasureRequested(
            requestId: $requestId,
            customerId: $customerId,
            tenantId: $tenantId,
            occurredOn: $now,
        );

        $this->subscriber->onDataErasureRequested($erasureRequested);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('request_erasure', $command->actionType);
        $this->assertSame('data_subject_request', $command->resourceType);
        $this->assertSame($requestId->toString(), $command->resourceId);
        $this->assertSame($customerId->toString(), $command->metadata['customerId']);
        $this->assertSame('DataErasureRequested', $command->metadata['event']);

        // DataSubjectRequestSubmitted
        $this->dispatchedCommands = [];
        $submitted = new DataSubjectRequestSubmitted(
            requestId: $requestId,
            customerId: $customerId,
            requestType: RequestType::access(),
            tenantId: $tenantId,
            occurredOn: $now,
        );

        $this->subscriber->onDataSubjectRequestSubmitted($submitted);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('submit', $command->actionType);
        $this->assertSame('data_subject_request', $command->resourceType);
        $this->assertSame($requestId->toString(), $command->resourceId);
        $this->assertSame('access', $command->metadata['requestType']);
        $this->assertSame('DataSubjectRequestSubmitted', $command->metadata['event']);

        // DataSubjectRequestCompleted (tenantId=null -> system tenant)
        $this->dispatchedCommands = [];
        $dsCompleted = new DataSubjectRequestCompleted(
            requestId: $requestId,
            customerId: $customerId,
            requestType: RequestType::erasure(),
            occurredOn: $now,
        );

        $this->subscriber->onDataSubjectRequestCompleted($dsCompleted);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('complete', $command->actionType);
        $this->assertSame('data_subject_request', $command->resourceType);
        $this->assertSame('erasure', $command->metadata['requestType']);
        $this->assertSame('DataSubjectRequestCompleted', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Notification Events
    // ──────────────────────────────────────────────

    public function testNotificationHandlers(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $notificationId = NotificationId::generate();
        $now = new \DateTimeImmutable();

        // NotificationSent
        $event = new NotificationSent(
            notificationId: $notificationId,
            tenantId: $tenantId,
            type: NotificationType::EMAIL,
            recipientEmail: 'user@example.com',
            sentAt: $now,
        );

        $this->subscriber->onNotificationSent($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('send', $command->actionType);
        $this->assertSame('notification', $command->resourceType);
        $this->assertSame($notificationId->toString(), $command->resourceId);
        $this->assertSame('email', $command->metadata['type']);
        $this->assertSame('NotificationSent', $command->metadata['event']);

        // NotificationFailed
        $this->dispatchedCommands = [];
        $failed = new NotificationFailed(
            notificationId: $notificationId,
            tenantId: $tenantId,
            type: NotificationType::SMS,
            recipientEmail: null,
            reason: 'Invalid phone number',
            attemptCount: 3,
        );

        $this->subscriber->onNotificationFailed($failed);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::TENANT_ID, $command->tenantId);
        $this->assertSame('fail', $command->actionType);
        $this->assertSame('notification', $command->resourceType);
        $this->assertSame($notificationId->toString(), $command->resourceId);
        $this->assertSame('sms', $command->metadata['type']);
        $this->assertSame('Invalid phone number', $command->metadata['reason']);
        $this->assertSame(3, $command->metadata['attemptCount']);
        $this->assertSame('NotificationFailed', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // Wishlist Events
    // ──────────────────────────────────────────────

    public function testWishlistHandlers(): void
    {
        $wishlistId = WishlistId::generate();
        $productId = ProductId::generate();
        $now = new \DateTimeImmutable();

        // ItemRemovedFromWishlist
        $event = new ItemRemovedFromWishlist(
            wishlistId: $wishlistId,
            productId: $productId,
            customerId: 'customer-abc',
            occurredAt: $now,
        );

        $this->subscriber->onItemRemovedFromWishlist($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('remove_item', $command->actionType);
        $this->assertSame('wishlist', $command->resourceType);
        $this->assertSame($wishlistId->toString(), $command->resourceId);
        $this->assertSame($productId->toString(), $command->metadata['productId']);
        $this->assertSame('customer-abc', $command->metadata['customerId']);
        $this->assertSame('ItemRemovedFromWishlist', $command->metadata['event']);

        // WishlistCleared
        $this->dispatchedCommands = [];
        $cleared = new WishlistCleared(
            wishlistId: $wishlistId,
            customerId: 'customer-abc',
            itemCount: 5,
            occurredAt: $now,
        );

        $this->subscriber->onWishlistCleared($cleared);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame(self::SYSTEM_TENANT_ID, $command->tenantId);
        $this->assertSame('clear', $command->actionType);
        $this->assertSame('wishlist', $command->resourceType);
        $this->assertSame($wishlistId->toString(), $command->resourceId);
        $this->assertSame('customer-abc', $command->metadata['customerId']);
        $this->assertSame(5, $command->metadata['itemCount']);
        $this->assertSame('WishlistCleared', $command->metadata['event']);
    }

    // ──────────────────────────────────────────────
    // System tenant fallback test
    // ──────────────────────────────────────────────

    public function testSystemTenantFallbackWhenTenantIdIsNull(): void
    {
        // Events with no tenantId use system tenant UUID
        $customerId = CustomerId::generate();
        $event = new CustomerActivated(customerId: $customerId);

        $this->subscriber->onCustomerActivated($event);

        $this->assertCount(1, $this->dispatchedCommands);
        $command = $this->dispatchedCommands[0];
        $this->assertSame('00000000-0000-0000-0000-000000000000', $command->tenantId);
    }
}
