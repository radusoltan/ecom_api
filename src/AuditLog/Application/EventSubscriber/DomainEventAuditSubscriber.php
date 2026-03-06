<?php

declare(strict_types=1);

namespace App\AuditLog\Application\EventSubscriber;

use App\AuditLog\Application\Command\LogAuditEntry\LogAuditEntry;
use App\Cart\Domain\Event\CartAbandoned;
use App\Cart\Domain\Event\CartCleared;
use App\Cart\Domain\Event\CartCreated;
use App\Cart\Domain\Event\CartQuantityUpdated;
use App\Cart\Domain\Event\ItemAddedToCart;
use App\Cart\Domain\Event\ItemRemovedFromCart;
use App\Catalog\Domain\Event\BundleCreated;
use App\Catalog\Domain\Event\BundleItemAdded;
use App\Catalog\Domain\Event\BundleItemRemoved;
use App\Catalog\Domain\Event\BundleRemoved;
use App\Catalog\Domain\Event\BundleUpdated;
use App\Catalog\Domain\Event\CategoryCreated;
use App\Catalog\Domain\Event\CategoryTranslationsUpdated;
use App\Catalog\Domain\Event\CategoryUpdated;
use App\Catalog\Domain\Event\DownloadableFileAttached;
use App\Catalog\Domain\Event\DownloadableFileRemoved;
use App\Catalog\Domain\Event\DownloadableFileUpdated;
use App\Catalog\Domain\Event\OptionDefined;
use App\Catalog\Domain\Event\OptionRemoved;
use App\Catalog\Domain\Event\ProductCreated;
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
use App\Customer\Domain\Event\AccountDeletionCancelled;
use App\Customer\Domain\Event\AccountDeletionCompleted;
use App\Customer\Domain\Event\AccountDeletionConfirmed;
use App\Customer\Domain\Event\AccountDeletionHeld;
use App\Customer\Domain\Event\AccountDeletionRequested;
use App\Customer\Domain\Event\CustomerActivated;
use App\Customer\Domain\Event\CustomerAddressAdded;
use App\Customer\Domain\Event\CustomerAddressRemoved;
use App\Customer\Domain\Event\CustomerAddressUpdated;
use App\Customer\Domain\Event\CustomerConsentChanged;
use App\Customer\Domain\Event\CustomerCreated;
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
use App\Inventory\Domain\Event\StockAdjusted;
use App\Inventory\Domain\Event\StockAllocated;
use App\Inventory\Domain\Event\StockDepleted;
use App\Inventory\Domain\Event\StockReleased;
use App\Inventory\Domain\Event\StockReserved;
use App\Inventory\Domain\Event\WarehouseActivated;
use App\Inventory\Domain\Event\WarehouseCreated;
use App\Inventory\Domain\Event\WarehouseDeactivated;
use App\Inventory\Domain\Event\WarehouseUpdated;
use App\Invoice\Domain\Event\InvoiceCancelled;
use App\Invoice\Domain\Event\InvoiceCreated;
use App\Invoice\Domain\Event\InvoiceCredited;
use App\Invoice\Domain\Event\InvoiceIssued;
use App\Invoice\Domain\Event\InvoicePaid;
use App\Notifications\Domain\Event\NotificationCreated;
use App\Notifications\Domain\Event\NotificationFailed;
use App\Notifications\Domain\Event\NotificationSent;
use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Event\OrderPaid;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\Event\PaymentCreated;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\Event\PaymentRetryAttempted;
use App\Payment\Domain\Event\PaymentRetryExhausted;
use App\Payment\Domain\Event\PaymentRetryScheduled;
use App\Privacy\Domain\Event\ConsentGranted;
use App\Privacy\Domain\Event\ConsentWithdrawn;
use App\Privacy\Domain\Event\DataErasureRequested;
use App\Privacy\Domain\Event\DataSubjectRequestCompleted;
use App\Privacy\Domain\Event\DataSubjectRequestSubmitted;
use App\Returns\Domain\Event\ReturnRequestApproved;
use App\Returns\Domain\Event\ReturnRequestCompleted;
use App\Returns\Domain\Event\ReturnRequestCreated;
use App\Returns\Domain\Event\ReturnRequestInspected;
use App\Returns\Domain\Event\ReturnRequestReceived;
use App\Returns\Domain\Event\ReturnRequestRejected;
use App\Review\Domain\Event\ReviewApproved;
use App\Review\Domain\Event\ReviewRejected;
use App\Review\Domain\Event\ReviewSubmitted;
use App\Shipping\Domain\Event\ShipmentCancelled;
use App\Shipping\Domain\Event\ShipmentCreated;
use App\Shipping\Domain\Event\ShipmentDelivered;
use App\Shipping\Domain\Event\ShipmentDispatched;
use App\Shipping\Domain\Event\ShipmentInTransit;
use App\Tenant\Domain\Event\FeatureFlagConfigurationUpdated;
use App\Tenant\Domain\Event\FeatureFlagCreated;
use App\Tenant\Domain\Event\FeatureFlagToggled;
use App\Tenant\Domain\Event\TenantActivated;
use App\Tenant\Domain\Event\TenantCreated;
use App\Tenant\Domain\Event\TenantDeactivated;
use App\Tenant\Domain\Event\TenantReactivated;
use App\Tenant\Domain\Event\TenantSuspended;
use App\Tenant\Domain\Event\TenantUpdated;
use App\User\Domain\Event\UserCreated;
use App\Wishlist\Domain\Event\ItemAddedToWishlist;
use App\Wishlist\Domain\Event\ItemRemovedFromWishlist;
use App\Wishlist\Domain\Event\WishlistCleared;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Automatically logs all domain events to the audit log.
 *
 * This subscriber listens to domain events and creates audit log entries
 * to maintain a comprehensive audit trail of all actions in the system.
 *
 * Business Rules:
 * - All domain events should be logged
 * - Log entries include user, action, resource, timestamp, and context
 * - System-initiated events logged with null userId
 * - Failures should be logged but not block event processing
 */
final readonly class DomainEventAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // User events
            UserCreated::class => 'onUserCreated',

            // Order events
            OrderPlaced::class => 'onOrderPlaced',
            OrderPaid::class => 'onOrderPaid',
            OrderStatusChanged::class => 'onOrderStatusChanged',
            OrderCancelled::class => 'onOrderCancelled',

            // Payment events
            PaymentCreated::class => 'onPaymentCreated',
            PaymentAuthorized::class => 'onPaymentAuthorized',
            PaymentCaptured::class => 'onPaymentCaptured',
            PaymentRefunded::class => 'onPaymentRefunded',
            PaymentCancelled::class => 'onPaymentCancelled',
            PaymentFailed::class => 'onPaymentFailed',
            PaymentRetryScheduled::class => 'onPaymentRetryScheduled',
            PaymentRetryAttempted::class => 'onPaymentRetryAttempted',
            PaymentRetryExhausted::class => 'onPaymentRetryExhausted',

            // Review events
            ReviewSubmitted::class => 'onReviewSubmitted',
            ReviewApproved::class => 'onReviewApproved',
            ReviewRejected::class => 'onReviewRejected',

            // Catalog events
            ProductCreated::class => 'onProductCreated',
            ProductUpdated::class => 'onProductUpdated',
            ProductPublished::class => 'onProductPublished',
            ProductDeactivated::class => 'onProductDeactivated',
            ProductReactivated::class => 'onProductReactivated',
            ProductDiscontinued::class => 'onProductDiscontinued',
            ProductTypeChanged::class => 'onProductTypeChanged',
            ProductTranslationsUpdated::class => 'onProductTranslationsUpdated',
            CategoryCreated::class => 'onCategoryCreated',
            CategoryUpdated::class => 'onCategoryUpdated',
            CategoryTranslationsUpdated::class => 'onCategoryTranslationsUpdated',
            BundleCreated::class => 'onBundleCreated',
            BundleUpdated::class => 'onBundleUpdated',
            BundleItemAdded::class => 'onBundleItemAdded',
            BundleItemRemoved::class => 'onBundleItemRemoved',
            BundleRemoved::class => 'onBundleRemoved',
            SubscriptionConfigured::class => 'onSubscriptionConfigured',
            SubscriptionUpdated::class => 'onSubscriptionUpdated',
            SubscriptionRemoved::class => 'onSubscriptionRemoved',
            DownloadableFileAttached::class => 'onDownloadableFileAttached',
            DownloadableFileUpdated::class => 'onDownloadableFileUpdated',
            DownloadableFileRemoved::class => 'onDownloadableFileRemoved',
            OptionDefined::class => 'onOptionDefined',
            OptionRemoved::class => 'onOptionRemoved',
            VariantAdded::class => 'onVariantAdded',
            VariantRemoved::class => 'onVariantRemoved',
            VariantsGenerated::class => 'onVariantsGenerated',

            // Customer events
            CustomerCreated::class => 'onCustomerCreated',
            CustomerUpdated::class => 'onCustomerUpdated',
            CustomerActivated::class => 'onCustomerActivated',
            CustomerDeactivated::class => 'onCustomerDeactivated',
            CustomerConsentChanged::class => 'onCustomerConsentChanged',
            CustomerPreferencesUpdated::class => 'onCustomerPreferencesUpdated',
            CustomerSegmentChanged::class => 'onCustomerSegmentChanged',
            CustomerAddressAdded::class => 'onCustomerAddressAdded',
            CustomerAddressUpdated::class => 'onCustomerAddressUpdated',
            CustomerAddressRemoved::class => 'onCustomerAddressRemoved',
            DefaultAddressChanged::class => 'onDefaultAddressChanged',
            NotificationPreferencesUpdated::class => 'onNotificationPreferencesUpdated',
            LoyaltyPointsAwarded::class => 'onLoyaltyPointsAwarded',
            LoyaltyPointsRedeemed::class => 'onLoyaltyPointsRedeemed',
            LoyaltyProgramCreated::class => 'onLoyaltyProgramCreated',
            LoyaltyProgramUpdated::class => 'onLoyaltyProgramUpdated',
            LoyaltyProgramActivated::class => 'onLoyaltyProgramActivated',
            LoyaltyProgramDeactivated::class => 'onLoyaltyProgramDeactivated',
            LoyaltyTierAdded::class => 'onLoyaltyTierAdded',
            LoyaltyTierUpdated::class => 'onLoyaltyTierUpdated',
            LoyaltyTierRemoved::class => 'onLoyaltyTierRemoved',
            AccountDeletionRequested::class => 'onAccountDeletionRequested',
            AccountDeletionConfirmed::class => 'onAccountDeletionConfirmed',
            AccountDeletionHeld::class => 'onAccountDeletionHeld',
            AccountDeletionCancelled::class => 'onAccountDeletionCancelled',
            AccountDeletionCompleted::class => 'onAccountDeletionCompleted',
            DataExportRequested::class => 'onDataExportRequested',
            DataExportCompleted::class => 'onDataExportCompleted',

            // Inventory events
            StockAdjusted::class => 'onStockAdjusted',
            StockAllocated::class => 'onStockAllocated',
            StockReserved::class => 'onStockReserved',
            StockReleased::class => 'onStockReleased',
            StockDepleted::class => 'onStockDepleted',
            WarehouseCreated::class => 'onWarehouseCreated',
            WarehouseUpdated::class => 'onWarehouseUpdated',
            WarehouseActivated::class => 'onWarehouseActivated',
            WarehouseDeactivated::class => 'onWarehouseDeactivated',

            // Tenant events
            TenantCreated::class => 'onTenantCreated',
            TenantUpdated::class => 'onTenantUpdated',
            TenantActivated::class => 'onTenantActivated',
            TenantDeactivated::class => 'onTenantDeactivated',
            TenantSuspended::class => 'onTenantSuspended',
            TenantReactivated::class => 'onTenantReactivated',
            FeatureFlagCreated::class => 'onFeatureFlagCreated',
            FeatureFlagToggled::class => 'onFeatureFlagToggled',
            FeatureFlagConfigurationUpdated::class => 'onFeatureFlagConfigurationUpdated',

            // Cart events
            CartCreated::class => 'onCartCreated',
            CartCleared::class => 'onCartCleared',
            CartAbandoned::class => 'onCartAbandoned',
            ItemAddedToCart::class => 'onItemAddedToCart',
            ItemRemovedFromCart::class => 'onItemRemovedFromCart',
            CartQuantityUpdated::class => 'onCartQuantityUpdated',

            // Shipping events
            ShipmentCreated::class => 'onShipmentCreated',
            ShipmentDispatched::class => 'onShipmentDispatched',
            ShipmentInTransit::class => 'onShipmentInTransit',
            ShipmentDelivered::class => 'onShipmentDelivered',
            ShipmentCancelled::class => 'onShipmentCancelled',

            // Invoice events
            InvoiceCreated::class => 'onInvoiceCreated',
            InvoiceIssued::class => 'onInvoiceIssued',
            InvoicePaid::class => 'onInvoicePaid',
            InvoiceCancelled::class => 'onInvoiceCancelled',
            InvoiceCredited::class => 'onInvoiceCredited',

            // Returns events
            ReturnRequestCreated::class => 'onReturnRequestCreated',
            ReturnRequestApproved::class => 'onReturnRequestApproved',
            ReturnRequestRejected::class => 'onReturnRequestRejected',
            ReturnRequestReceived::class => 'onReturnRequestReceived',
            ReturnRequestInspected::class => 'onReturnRequestInspected',
            ReturnRequestCompleted::class => 'onReturnRequestCompleted',

            // Privacy events
            ConsentGranted::class => 'onConsentGranted',
            ConsentWithdrawn::class => 'onConsentWithdrawn',
            DataErasureRequested::class => 'onDataErasureRequested',
            DataSubjectRequestSubmitted::class => 'onDataSubjectRequestSubmitted',
            DataSubjectRequestCompleted::class => 'onDataSubjectRequestCompleted',

            // Notification events
            NotificationCreated::class => 'onNotificationCreated',
            NotificationSent::class => 'onNotificationSent',
            NotificationFailed::class => 'onNotificationFailed',

            // Wishlist events
            ItemAddedToWishlist::class => 'onItemAddedToWishlist',
            ItemRemovedFromWishlist::class => 'onItemRemovedFromWishlist',
            WishlistCleared::class => 'onWishlistCleared',
        ];
    }

    // ──────────────────────────────────────────────
    // User Events
    // ──────────────────────────────────────────────

    public function onUserCreated(UserCreated $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'create',
            resourceType: 'user',
            resourceId: $event->userId->toString(),
            metadata: [
                'email' => $event->email,
                'username' => $event->username,
                'event' => 'UserCreated',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Order Events
    // ──────────────────────────────────────────────

    public function onOrderPlaced(OrderPlaced $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'place',
            resourceType: 'order',
            resourceId: $event->orderId->toString(),
            metadata: [
                'customerEmail' => $event->customerEmail,
                'totalAmount' => $event->total->getAmount(),
                'currency' => $event->total->getCurrency()->getCurrencyCode(),
                'event' => 'OrderPlaced',
            ]
        );
    }

    public function onOrderPaid(OrderPaid $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'order',
            resourceId: $event->orderId->toString(),
            metadata: [
                'paymentId' => $event->paymentId,
                'paidAmountInCents' => $event->paidAmountInCents,
                'event' => 'OrderPaid',
            ]
        );
    }

    public function onOrderStatusChanged(OrderStatusChanged $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'order',
            resourceId: $event->orderId->toString(),
            metadata: [
                'oldStatus' => $event->oldStatus->value(),
                'newStatus' => $event->newStatus->value(),
                'event' => 'OrderStatusChanged',
            ]
        );
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'cancel',
            resourceType: 'order',
            resourceId: $event->orderId->toString(),
            metadata: [
                'reason' => $event->reason,
                'event' => 'OrderCancelled',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Payment Events
    // ──────────────────────────────────────────────

    public function onPaymentCreated(PaymentCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'orderId' => $event->orderId,
                'amountInCents' => $event->amount->getAmount(),
                'currency' => $event->amount->getCurrency()->getCurrencyCode(),
                'gateway' => $event->gateway,
                'event' => 'PaymentCreated',
            ]
        );
    }

    public function onPaymentAuthorized(PaymentAuthorized $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'authorize',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'gatewayTransactionId' => $event->gatewayTransactionId,
                'event' => 'PaymentAuthorized',
            ]
        );
    }

    public function onPaymentCaptured(PaymentCaptured $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'capture',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'orderId' => $event->orderId,
                'capturedAmountInCents' => $event->capturedAmount->getAmount(),
                'event' => 'PaymentCaptured',
            ]
        );
    }

    public function onPaymentRefunded(PaymentRefunded $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'refund',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'refundedAmountInCents' => $event->refundedAmount->getAmount(),
                'reason' => $event->reason,
                'event' => 'PaymentRefunded',
            ]
        );
    }

    public function onPaymentCancelled(PaymentCancelled $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'cancel',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'reason' => $event->reason,
                'event' => 'PaymentCancelled',
            ]
        );
    }

    public function onPaymentFailed(PaymentFailed $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'errorMessage' => $event->errorMessage,
                'event' => 'PaymentFailed',
            ]
        );
    }

    public function onPaymentRetryScheduled(PaymentRetryScheduled $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'retry',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'orderId' => $event->orderId,
                'retryAttempt' => $event->retryAttempt,
                'scheduledFor' => $event->scheduledFor->format(\DateTimeInterface::ATOM),
                'errorCode' => $event->errorCode,
                'event' => 'PaymentRetryScheduled',
            ]
        );
    }

    public function onPaymentRetryAttempted(PaymentRetryAttempted $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'retry',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'attemptNumber' => $event->attemptNumber,
                'wasSuccessful' => $event->wasSuccessful,
                'errorCode' => $event->errorCode,
                'errorMessage' => $event->errorMessage,
                'event' => 'PaymentRetryAttempted',
            ]
        );
    }

    public function onPaymentRetryExhausted(PaymentRetryExhausted $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'payment',
            resourceId: $event->paymentId->toString(),
            metadata: [
                'orderId' => $event->orderId,
                'totalAttempts' => $event->totalAttempts,
                'lastErrorCode' => $event->lastErrorCode,
                'lastErrorMessage' => $event->lastErrorMessage,
                'event' => 'PaymentRetryExhausted',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Review Events
    // ──────────────────────────────────────────────

    public function onReviewSubmitted(ReviewSubmitted $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'review',
            resourceId: $event->reviewId->toString(),
            metadata: [
                'productId' => $event->productId->toString(),
                'customerId' => $event->customerId->toString(),
                'rating' => $event->rating,
                'event' => 'ReviewSubmitted',
            ]
        );
    }

    public function onReviewApproved(ReviewApproved $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'review',
            resourceId: $event->reviewId->toString(),
            metadata: [
                'approvedBy' => $event->approvedBy->toString(),
                'event' => 'ReviewApproved',
            ]
        );
    }

    public function onReviewRejected(ReviewRejected $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'review',
            resourceId: $event->reviewId->toString(),
            metadata: [
                'rejectedBy' => $event->rejectedBy->toString(),
                'reason' => $event->reason,
                'event' => 'ReviewRejected',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Catalog Events
    // ──────────────────────────────────────────────

    public function onProductCreated(ProductCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'product',
            resourceId: $event->productId->toString(),
            metadata: [
                'sku' => $event->sku->value(),
                'name' => $event->name,
                'event' => 'ProductCreated',
            ]
        );
    }

    public function onProductUpdated(ProductUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'product',
            resourceId: $event->productId->toString(),
            metadata: ['event' => 'ProductUpdated']
        );
    }

    public function onProductPublished(ProductPublished $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'publish',
            resourceType: 'product',
            resourceId: $event->productId->toString(),
            metadata: ['event' => 'ProductPublished']
        );
    }

    public function onProductDeactivated(ProductDeactivated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'deactivate',
            resourceType: 'product',
            resourceId: $event->productId->toString(),
            metadata: ['event' => 'ProductDeactivated']
        );
    }

    public function onProductReactivated(ProductReactivated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'reactivate',
            resourceType: 'product',
            resourceId: $event->productId->toString(),
            metadata: ['event' => 'ProductReactivated']
        );
    }

    public function onProductDiscontinued(ProductDiscontinued $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'discontinue',
            resourceType: 'product',
            resourceId: $event->productId->toString(),
            metadata: ['event' => 'ProductDiscontinued']
        );
    }

    public function onProductTypeChanged(ProductTypeChanged $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'product',
            resourceId: $event->productId->toString(),
            metadata: [
                'oldType' => $event->oldType->value(),
                'newType' => $event->newType->value(),
                'event' => 'ProductTypeChanged',
            ]
        );
    }

    public function onProductTranslationsUpdated(ProductTranslationsUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'translate',
            resourceType: 'product',
            resourceId: $event->productId()->toString(),
            metadata: [
                'locale' => $event->locale()->toString(),
                'event' => 'ProductTranslationsUpdated',
            ]
        );
    }

    public function onCategoryCreated(CategoryCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'category',
            resourceId: $event->categoryId->toString(),
            metadata: [
                'name' => $event->name,
                'event' => 'CategoryCreated',
            ]
        );
    }

    public function onCategoryUpdated(CategoryUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'category',
            resourceId: $event->categoryId->toString(),
            metadata: ['event' => 'CategoryUpdated']
        );
    }

    public function onCategoryTranslationsUpdated(CategoryTranslationsUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'translate',
            resourceType: 'category',
            resourceId: $event->categoryId()->toString(),
            metadata: [
                'locale' => $event->locale()->toString(),
                'event' => 'CategoryTranslationsUpdated',
            ]
        );
    }

    public function onBundleCreated(BundleCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'create',
            resourceType: 'bundle',
            resourceId: $event->productId()->toString(),
            metadata: [
                'discountPercentage' => $event->discountPercentage(),
                'event' => 'BundleCreated',
            ]
        );
    }

    public function onBundleUpdated(BundleUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'update',
            resourceType: 'bundle',
            resourceId: $event->productId()->toString(),
            metadata: ['event' => 'BundleUpdated']
        );
    }

    public function onBundleItemAdded(BundleItemAdded $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'update',
            resourceType: 'bundle',
            resourceId: $event->productId()->toString(),
            metadata: ['event' => 'BundleItemAdded']
        );
    }

    public function onBundleItemRemoved(BundleItemRemoved $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'update',
            resourceType: 'bundle',
            resourceId: $event->bundleProductId()->toString(),
            metadata: [
                'removedProductId' => $event->removedProductId()->toString(),
                'event' => 'BundleItemRemoved',
            ]
        );
    }

    public function onBundleRemoved(BundleRemoved $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'delete',
            resourceType: 'bundle',
            resourceId: $event->productId()->toString(),
            metadata: ['event' => 'BundleRemoved']
        );
    }

    public function onSubscriptionConfigured(SubscriptionConfigured $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'create',
            resourceType: 'subscription_config',
            resourceId: $event->productId()->toString(),
            metadata: [
                'interval' => $event->interval()->value,
                'billingCycles' => $event->billingCycles(),
                'event' => 'SubscriptionConfigured',
            ]
        );
    }

    public function onSubscriptionUpdated(SubscriptionUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'update',
            resourceType: 'subscription_config',
            resourceId: $event->productId()->toString(),
            metadata: ['event' => 'SubscriptionUpdated']
        );
    }

    public function onSubscriptionRemoved(SubscriptionRemoved $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'delete',
            resourceType: 'subscription_config',
            resourceId: $event->productId()->toString(),
            metadata: ['event' => 'SubscriptionRemoved']
        );
    }

    public function onDownloadableFileAttached(DownloadableFileAttached $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'create',
            resourceType: 'downloadable_file',
            resourceId: $event->productId()->toString(),
            metadata: [
                'filename' => $event->filename(),
                'event' => 'DownloadableFileAttached',
            ]
        );
    }

    public function onDownloadableFileUpdated(DownloadableFileUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'update',
            resourceType: 'downloadable_file',
            resourceId: $event->productId()->toString(),
            metadata: ['event' => 'DownloadableFileUpdated']
        );
    }

    public function onDownloadableFileRemoved(DownloadableFileRemoved $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'delete',
            resourceType: 'downloadable_file',
            resourceId: $event->productId()->toString(),
            metadata: ['event' => 'DownloadableFileRemoved']
        );
    }

    public function onOptionDefined(OptionDefined $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'create',
            resourceType: 'product_option',
            resourceId: $event->getProductId()->toString(),
            metadata: [
                'configurableProductId' => $event->getConfigurableProductId()->toString(),
                'optionCode' => $event->getOptionCode()->toString(),
                'event' => 'OptionDefined',
            ]
        );
    }

    public function onOptionRemoved(OptionRemoved $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'delete',
            resourceType: 'product_option',
            resourceId: $event->getProductId()->toString(),
            metadata: [
                'configurableProductId' => $event->getConfigurableProductId()->toString(),
                'optionCode' => $event->getOptionCode()->toString(),
                'event' => 'OptionRemoved',
            ]
        );
    }

    public function onVariantAdded(VariantAdded $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'create',
            resourceType: 'product_variant',
            resourceId: $event->getVariantId()->toString(),
            metadata: [
                'productId' => $event->getProductId()->toString(),
                'sku' => $event->getSku()->toString(),
                'event' => 'VariantAdded',
            ]
        );
    }

    public function onVariantRemoved(VariantRemoved $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'delete',
            resourceType: 'product_variant',
            resourceId: $event->getVariantId()->toString(),
            metadata: [
                'productId' => $event->getProductId()->toString(),
                'event' => 'VariantRemoved',
            ]
        );
    }

    public function onVariantsGenerated(VariantsGenerated $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'create',
            resourceType: 'product_variant',
            resourceId: $event->getProductId()->toString(),
            metadata: [
                'configurableProductId' => $event->getConfigurableProductId()->toString(),
                'variantsCount' => $event->getVariantsCount(),
                'event' => 'VariantsGenerated',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Customer Events
    // ──────────────────────────────────────────────

    public function onCustomerCreated(CustomerCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'create',
            resourceType: 'customer',
            resourceId: $event->customerId()->toString(),
            metadata: [
                'email' => $event->email()->toString(),
                'firstName' => $event->firstName(),
                'lastName' => $event->lastName(),
                'event' => 'CustomerCreated',
            ]
        );
    }

    public function onCustomerUpdated(CustomerUpdated $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'update',
            resourceType: 'customer',
            resourceId: $event->customerId()->toString(),
            metadata: [
                'firstName' => $event->firstName(),
                'lastName' => $event->lastName(),
                'event' => 'CustomerUpdated',
            ]
        );
    }

    public function onCustomerActivated(CustomerActivated $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'activate',
            resourceType: 'customer',
            resourceId: $event->customerId()->toString(),
            metadata: ['event' => 'CustomerActivated']
        );
    }

    public function onCustomerDeactivated(CustomerDeactivated $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'deactivate',
            resourceType: 'customer',
            resourceId: $event->customerId()->toString(),
            metadata: ['event' => 'CustomerDeactivated']
        );
    }

    public function onCustomerConsentChanged(CustomerConsentChanged $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'update',
            resourceType: 'customer_consent',
            resourceId: $event->customerId()->toString(),
            metadata: [
                'consentType' => $event->consentType()->value,
                'granted' => $event->granted(),
                'event' => 'CustomerConsentChanged',
            ]
        );
    }

    public function onCustomerPreferencesUpdated(CustomerPreferencesUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'update',
            resourceType: 'customer_preferences',
            resourceId: $event->customerId()->toString(),
            metadata: ['event' => 'CustomerPreferencesUpdated']
        );
    }

    public function onCustomerSegmentChanged(CustomerSegmentChanged $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'update',
            resourceType: 'customer_segment',
            resourceId: $event->customerId()->toString(),
            metadata: [
                'oldSegment' => $event->oldSegment()->value(),
                'newSegment' => $event->newSegment()->value(),
                'event' => 'CustomerSegmentChanged',
            ]
        );
    }

    public function onCustomerAddressAdded(CustomerAddressAdded $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'create',
            resourceType: 'customer_address',
            resourceId: $event->customerId()->toString(),
            metadata: ['event' => 'CustomerAddressAdded']
        );
    }

    public function onCustomerAddressUpdated(CustomerAddressUpdated $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'update',
            resourceType: 'customer_address',
            resourceId: $event->customerId()->toString(),
            metadata: [
                'addressId' => $event->addressId()->toString(),
                'event' => 'CustomerAddressUpdated',
            ]
        );
    }

    public function onCustomerAddressRemoved(CustomerAddressRemoved $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'delete',
            resourceType: 'customer_address',
            resourceId: $event->customerId()->toString(),
            metadata: [
                'addressId' => $event->addressId()->toString(),
                'event' => 'CustomerAddressRemoved',
            ]
        );
    }

    public function onDefaultAddressChanged(DefaultAddressChanged $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'update',
            resourceType: 'customer_address',
            resourceId: $event->customerId()->toString(),
            metadata: [
                'addressId' => $event->addressId()->toString(),
                'type' => $event->type(),
                'event' => 'DefaultAddressChanged',
            ]
        );
    }

    public function onNotificationPreferencesUpdated(NotificationPreferencesUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'notification_preferences',
            resourceId: $event->customerId->toString(),
            metadata: ['event' => 'NotificationPreferencesUpdated']
        );
    }

    public function onLoyaltyPointsAwarded(LoyaltyPointsAwarded $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'award',
            resourceType: 'loyalty_points',
            resourceId: $event->customerId()->toString(),
            metadata: [
                'pointsAwarded' => $event->pointsAwarded(),
                'newTotalPoints' => $event->newTotalPoints(),
                'reason' => $event->reason(),
                'event' => 'LoyaltyPointsAwarded',
            ]
        );
    }

    public function onLoyaltyPointsRedeemed(LoyaltyPointsRedeemed $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'redeem',
            resourceType: 'loyalty_points',
            resourceId: $event->customerId->toString(),
            metadata: [
                'pointsRedeemed' => $event->pointsRedeemed,
                'remainingBalance' => $event->remainingBalance,
                'discountAmount' => $event->discountAmount->getAmount(),
                'orderId' => $event->orderId,
                'event' => 'LoyaltyPointsRedeemed',
            ]
        );
    }

    public function onLoyaltyProgramCreated(LoyaltyProgramCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'create',
            resourceType: 'loyalty_program',
            resourceId: $event->programId()->toString(),
            metadata: [
                'name' => $event->name(),
                'event' => 'LoyaltyProgramCreated',
            ]
        );
    }

    public function onLoyaltyProgramUpdated(LoyaltyProgramUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'update',
            resourceType: 'loyalty_program',
            resourceId: $event->programId()->toString(),
            metadata: ['event' => 'LoyaltyProgramUpdated']
        );
    }

    public function onLoyaltyProgramActivated(LoyaltyProgramActivated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'activate',
            resourceType: 'loyalty_program',
            resourceId: $event->programId()->toString(),
            metadata: ['event' => 'LoyaltyProgramActivated']
        );
    }

    public function onLoyaltyProgramDeactivated(LoyaltyProgramDeactivated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'deactivate',
            resourceType: 'loyalty_program',
            resourceId: $event->programId()->toString(),
            metadata: ['event' => 'LoyaltyProgramDeactivated']
        );
    }

    public function onLoyaltyTierAdded(LoyaltyTierAdded $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'create',
            resourceType: 'loyalty_tier',
            resourceId: $event->tierId()->toString(),
            metadata: [
                'programId' => $event->programId()->toString(),
                'tierName' => $event->tierName(),
                'threshold' => $event->threshold(),
                'event' => 'LoyaltyTierAdded',
            ]
        );
    }

    public function onLoyaltyTierUpdated(LoyaltyTierUpdated $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'update',
            resourceType: 'loyalty_tier',
            resourceId: $event->tierId()->toString(),
            metadata: [
                'programId' => $event->programId()->toString(),
                'event' => 'LoyaltyTierUpdated',
            ]
        );
    }

    public function onLoyaltyTierRemoved(LoyaltyTierRemoved $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'delete',
            resourceType: 'loyalty_tier',
            resourceId: $event->tierId()->toString(),
            metadata: [
                'programId' => $event->programId()->toString(),
                'event' => 'LoyaltyTierRemoved',
            ]
        );
    }

    public function onAccountDeletionRequested(AccountDeletionRequested $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'request_deletion',
            resourceType: 'account_deletion',
            resourceId: $event->requestId()->toString(),
            metadata: [
                'customerId' => $event->customerId()->toString(),
                'reason' => $event->reason(),
                'event' => 'AccountDeletionRequested',
            ]
        );
    }

    public function onAccountDeletionConfirmed(AccountDeletionConfirmed $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'confirm',
            resourceType: 'account_deletion',
            resourceId: $event->requestId()->toString(),
            metadata: [
                'customerId' => $event->customerId()->toString(),
                'scheduledFor' => $event->scheduledFor()->format(\DateTimeInterface::ATOM),
                'event' => 'AccountDeletionConfirmed',
            ]
        );
    }

    public function onAccountDeletionHeld(AccountDeletionHeld $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'hold',
            resourceType: 'account_deletion',
            resourceId: $event->requestId()->toString(),
            metadata: [
                'customerId' => $event->customerId()->toString(),
                'holdReason' => $event->holdReason(),
                'event' => 'AccountDeletionHeld',
            ]
        );
    }

    public function onAccountDeletionCancelled(AccountDeletionCancelled $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'cancel',
            resourceType: 'account_deletion',
            resourceId: $event->requestId()->toString(),
            metadata: [
                'customerId' => $event->customerId()->toString(),
                'event' => 'AccountDeletionCancelled',
            ]
        );
    }

    public function onAccountDeletionCompleted(AccountDeletionCompleted $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'complete',
            resourceType: 'account_deletion',
            resourceId: $event->requestId()->toString(),
            metadata: [
                'customerId' => $event->customerId()->toString(),
                'event' => 'AccountDeletionCompleted',
            ]
        );
    }

    public function onDataExportRequested(DataExportRequested $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'request_export',
            resourceType: 'data_export',
            resourceId: $event->requestId()->toString(),
            metadata: [
                'customerId' => $event->customerId()->toString(),
                'event' => 'DataExportRequested',
            ]
        );
    }

    public function onDataExportCompleted(DataExportCompleted $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId()->toString(),
            actionType: 'complete',
            resourceType: 'data_export',
            resourceId: $event->requestId()->toString(),
            metadata: [
                'customerId' => $event->customerId()->toString(),
                'event' => 'DataExportCompleted',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Inventory Events
    // ──────────────────────────────────────────────

    public function onStockAdjusted(StockAdjusted $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'adjust',
            resourceType: 'stock',
            resourceId: $event->stockItemId->toString(),
            metadata: [
                'previousQuantity' => $event->previousQuantity->value(),
                'newQuantity' => $event->newQuantity->value(),
                'reason' => $event->reason,
                'event' => 'StockAdjusted',
            ]
        );
    }

    public function onStockAllocated(StockAllocated $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'allocate',
            resourceType: 'stock',
            resourceId: $event->stockItemId->toString(),
            metadata: [
                'quantity' => $event->quantity->value(),
                'orderId' => $event->orderId,
                'event' => 'StockAllocated',
            ]
        );
    }

    public function onStockReserved(StockReserved $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'reserve',
            resourceType: 'stock',
            resourceId: $event->stockItemId->toString(),
            metadata: [
                'quantity' => $event->quantity->value(),
                'reservationId' => $event->reservationId,
                'event' => 'StockReserved',
            ]
        );
    }

    public function onStockReleased(StockReleased $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'release',
            resourceType: 'stock',
            resourceId: $event->stockItemId->toString(),
            metadata: [
                'quantity' => $event->quantity->value(),
                'referenceId' => $event->referenceId,
                'reason' => $event->reason,
                'event' => 'StockReleased',
            ]
        );
    }

    public function onStockDepleted(StockDepleted $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'deplete',
            resourceType: 'stock',
            resourceId: $event->stockItemId->toString(),
            metadata: [
                'productId' => $event->productId->toString(),
                'warehouseId' => $event->warehouseId->toString(),
                'availableQuantity' => $event->availableQuantity->value(),
                'threshold' => $event->threshold->value(),
                'event' => 'StockDepleted',
            ]
        );
    }

    public function onWarehouseCreated(WarehouseCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'warehouse',
            resourceId: $event->warehouseId->toString(),
            metadata: [
                'code' => $event->code->toString(),
                'name' => $event->name->value(),
                'event' => 'WarehouseCreated',
            ]
        );
    }

    public function onWarehouseUpdated(WarehouseUpdated $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'update',
            resourceType: 'warehouse',
            resourceId: $event->warehouseId->toString(),
            metadata: [
                'name' => $event->name->value(),
                'event' => 'WarehouseUpdated',
            ]
        );
    }

    public function onWarehouseActivated(WarehouseActivated $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'activate',
            resourceType: 'warehouse',
            resourceId: $event->warehouseId->toString(),
            metadata: ['event' => 'WarehouseActivated']
        );
    }

    public function onWarehouseDeactivated(WarehouseDeactivated $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'deactivate',
            resourceType: 'warehouse',
            resourceId: $event->warehouseId->toString(),
            metadata: ['event' => 'WarehouseDeactivated']
        );
    }

    // ──────────────────────────────────────────────
    // Tenant Events
    // ──────────────────────────────────────────────

    public function onTenantCreated(TenantCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'tenant',
            resourceId: $event->tenantId->toString(),
            metadata: [
                'name' => $event->name->value(),
                'ownerEmail' => $event->ownerEmail->value(),
                'event' => 'TenantCreated',
            ]
        );
    }

    public function onTenantUpdated(TenantUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'tenant',
            resourceId: $event->tenantId->toString(),
            metadata: [
                'name' => $event->name->value(),
                'ownerEmail' => $event->ownerEmail->value(),
                'event' => 'TenantUpdated',
            ]
        );
    }

    public function onTenantActivated(TenantActivated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'activate',
            resourceType: 'tenant',
            resourceId: $event->tenantId->toString(),
            metadata: ['event' => 'TenantActivated']
        );
    }

    public function onTenantDeactivated(TenantDeactivated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'deactivate',
            resourceType: 'tenant',
            resourceId: $event->tenantId->toString(),
            metadata: ['event' => 'TenantDeactivated']
        );
    }

    public function onTenantSuspended(TenantSuspended $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'suspend',
            resourceType: 'tenant',
            resourceId: $event->tenantId->toString(),
            metadata: ['event' => 'TenantSuspended']
        );
    }

    public function onTenantReactivated(TenantReactivated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'reactivate',
            resourceType: 'tenant',
            resourceId: $event->tenantId->toString(),
            metadata: ['event' => 'TenantReactivated']
        );
    }

    public function onFeatureFlagCreated(FeatureFlagCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'feature_flag',
            resourceId: $event->flagId->toString(),
            metadata: [
                'featureName' => $event->featureName,
                'enabled' => $event->enabled,
                'event' => 'FeatureFlagCreated',
            ]
        );
    }

    public function onFeatureFlagToggled(FeatureFlagToggled $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'toggle',
            resourceType: 'feature_flag',
            resourceId: $event->flagId->toString(),
            metadata: [
                'featureName' => $event->featureName,
                'enabled' => $event->enabled,
                'event' => 'FeatureFlagToggled',
            ]
        );
    }

    public function onFeatureFlagConfigurationUpdated(FeatureFlagConfigurationUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'feature_flag',
            resourceId: $event->flagId->toString(),
            metadata: [
                'featureName' => $event->featureName,
                'event' => 'FeatureFlagConfigurationUpdated',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Cart Events
    // ──────────────────────────────────────────────

    public function onCartCreated(CartCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'cart',
            resourceId: $event->cartId->toString(),
            metadata: ['event' => 'CartCreated']
        );
    }

    public function onCartCleared(CartCleared $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'clear',
            resourceType: 'cart',
            resourceId: $event->cartId->toString(),
            metadata: ['event' => 'CartCleared']
        );
    }

    public function onCartAbandoned(CartAbandoned $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'abandon',
            resourceType: 'cart',
            resourceId: $event->cartId->toString(),
            metadata: [
                'customerEmail' => $event->customerEmail,
                'itemCount' => $event->itemCount,
                'cartTotal' => $event->cartTotal->getAmount(),
                'event' => 'CartAbandoned',
            ]
        );
    }

    public function onItemAddedToCart(ItemAddedToCart $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'add_item',
            resourceType: 'cart',
            resourceId: $event->cartId->toString(),
            metadata: [
                'productId' => $event->productId->toString(),
                'quantity' => $event->quantity->toInt(),
                'event' => 'ItemAddedToCart',
            ]
        );
    }

    public function onItemRemovedFromCart(ItemRemovedFromCart $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'remove_item',
            resourceType: 'cart',
            resourceId: $event->cartId->toString(),
            metadata: [
                'productId' => $event->productId->toString(),
                'event' => 'ItemRemovedFromCart',
            ]
        );
    }

    public function onCartQuantityUpdated(CartQuantityUpdated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'cart',
            resourceId: $event->cartId->toString(),
            metadata: [
                'productId' => $event->productId->toString(),
                'newQuantity' => $event->newQuantity->toInt(),
                'event' => 'CartQuantityUpdated',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Shipping Events
    // ──────────────────────────────────────────────

    public function onShipmentCreated(ShipmentCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'shipment',
            resourceId: $event->shipmentId->toString(),
            metadata: [
                'orderId' => $event->orderId,
                'recipientName' => $event->recipientName,
                'event' => 'ShipmentCreated',
            ]
        );
    }

    public function onShipmentDispatched(ShipmentDispatched $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'dispatch',
            resourceType: 'shipment',
            resourceId: $event->shipmentId->toString(),
            metadata: [
                'carrierCode' => $event->carrierCode->toString(),
                'trackingNumber' => $event->trackingNumber->toString(),
                'event' => 'ShipmentDispatched',
            ]
        );
    }

    public function onShipmentInTransit(ShipmentInTransit $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'update',
            resourceType: 'shipment',
            resourceId: $event->shipmentId->toString(),
            metadata: [
                'location' => $event->location,
                'statusNote' => $event->statusNote,
                'event' => 'ShipmentInTransit',
            ]
        );
    }

    public function onShipmentDelivered(ShipmentDelivered $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'deliver',
            resourceType: 'shipment',
            resourceId: $event->shipmentId->toString(),
            metadata: [
                'deliveredAt' => $event->deliveredAt->format(\DateTimeInterface::ATOM),
                'event' => 'ShipmentDelivered',
            ]
        );
    }

    public function onShipmentCancelled(ShipmentCancelled $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'cancel',
            resourceType: 'shipment',
            resourceId: $event->shipmentId->toString(),
            metadata: ['event' => 'ShipmentCancelled']
        );
    }

    // ──────────────────────────────────────────────
    // Invoice Events
    // ──────────────────────────────────────────────

    public function onInvoiceCreated(InvoiceCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'invoice',
            resourceId: $event->invoiceId->toString(),
            metadata: [
                'orderId' => $event->orderId->toString(),
                'customerId' => $event->customerId->toString(),
                'event' => 'InvoiceCreated',
            ]
        );
    }

    public function onInvoiceIssued(InvoiceIssued $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'issue',
            resourceType: 'invoice',
            resourceId: $event->invoiceId->toString(),
            metadata: [
                'invoiceNumber' => $event->invoiceNumber->toString(),
                'totalAmount' => $event->total->getAmount(),
                'currency' => $event->total->getCurrency()->getCurrencyCode(),
                'event' => 'InvoiceIssued',
            ]
        );
    }

    public function onInvoicePaid(InvoicePaid $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'pay',
            resourceType: 'invoice',
            resourceId: $event->invoiceId->toString(),
            metadata: [
                'totalAmount' => $event->total->getAmount(),
                'currency' => $event->total->getCurrency()->getCurrencyCode(),
                'event' => 'InvoicePaid',
            ]
        );
    }

    public function onInvoiceCancelled(InvoiceCancelled $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'cancel',
            resourceType: 'invoice',
            resourceId: $event->invoiceId->toString(),
            metadata: ['event' => 'InvoiceCancelled']
        );
    }

    public function onInvoiceCredited(InvoiceCredited $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'credit',
            resourceType: 'invoice',
            resourceId: $event->creditNoteId->toString(),
            metadata: [
                'originalInvoiceId' => $event->originalInvoiceId->toString(),
                'creditNoteNumber' => $event->creditNoteNumber->toString(),
                'creditAmount' => $event->creditAmount->getAmount(),
                'currency' => $event->creditAmount->getCurrency()->getCurrencyCode(),
                'event' => 'InvoiceCredited',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Returns Events
    // ──────────────────────────────────────────────

    public function onReturnRequestCreated(ReturnRequestCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'return_request',
            resourceId: $event->returnRequestId->toString(),
            metadata: [
                'orderId' => $event->orderId,
                'reason' => $event->reason,
                'event' => 'ReturnRequestCreated',
            ]
        );
    }

    public function onReturnRequestApproved(ReturnRequestApproved $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'approve',
            resourceType: 'return_request',
            resourceId: $event->returnRequestId->toString(),
            metadata: ['event' => 'ReturnRequestApproved']
        );
    }

    public function onReturnRequestRejected(ReturnRequestRejected $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'reject',
            resourceType: 'return_request',
            resourceId: $event->returnRequestId->toString(),
            metadata: [
                'rejectionReason' => $event->rejectionReason,
                'event' => 'ReturnRequestRejected',
            ]
        );
    }

    public function onReturnRequestReceived(ReturnRequestReceived $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'receive',
            resourceType: 'return_request',
            resourceId: $event->returnRequestId->toString(),
            metadata: [
                'warehouseId' => $event->warehouseId,
                'event' => 'ReturnRequestReceived',
            ]
        );
    }

    public function onReturnRequestInspected(ReturnRequestInspected $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'inspect',
            resourceType: 'return_request',
            resourceId: $event->returnRequestId->toString(),
            metadata: [
                'isResellable' => $event->isResellable,
                'inspectionNotes' => $event->inspectionNotes,
                'event' => 'ReturnRequestInspected',
            ]
        );
    }

    public function onReturnRequestCompleted(ReturnRequestCompleted $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'complete',
            resourceType: 'return_request',
            resourceId: $event->returnRequestId->toString(),
            metadata: [
                'refundAmount' => $event->refundAmount,
                'refundCurrency' => $event->refundCurrency,
                'event' => 'ReturnRequestCompleted',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Privacy Events
    // ──────────────────────────────────────────────

    public function onConsentGranted(ConsentGranted $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'grant',
            resourceType: 'consent',
            resourceId: $event->consentId->toString(),
            metadata: [
                'customerId' => $event->customerId->toString(),
                'purpose' => $event->purpose->value(),
                'event' => 'ConsentGranted',
            ]
        );
    }

    public function onConsentWithdrawn(ConsentWithdrawn $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'withdraw',
            resourceType: 'consent',
            resourceId: $event->consentId->toString(),
            metadata: [
                'customerId' => $event->customerId->toString(),
                'purpose' => $event->purpose->value(),
                'event' => 'ConsentWithdrawn',
            ]
        );
    }

    public function onDataErasureRequested(DataErasureRequested $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'request_erasure',
            resourceType: 'data_subject_request',
            resourceId: $event->requestId->toString(),
            metadata: [
                'customerId' => $event->customerId->toString(),
                'event' => 'DataErasureRequested',
            ]
        );
    }

    public function onDataSubjectRequestSubmitted(DataSubjectRequestSubmitted $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'submit',
            resourceType: 'data_subject_request',
            resourceId: $event->requestId->toString(),
            metadata: [
                'customerId' => $event->customerId->toString(),
                'requestType' => $event->requestType->value(),
                'event' => 'DataSubjectRequestSubmitted',
            ]
        );
    }

    public function onDataSubjectRequestCompleted(DataSubjectRequestCompleted $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'complete',
            resourceType: 'data_subject_request',
            resourceId: $event->requestId->toString(),
            metadata: [
                'customerId' => $event->customerId->toString(),
                'requestType' => $event->requestType->value(),
                'event' => 'DataSubjectRequestCompleted',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Notification Events
    // ──────────────────────────────────────────────

    public function onNotificationCreated(NotificationCreated $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'create',
            resourceType: 'notification',
            resourceId: $event->notificationId->toString(),
            metadata: [
                'type' => $event->type->value,
                'subject' => $event->subject,
                'event' => 'NotificationCreated',
            ]
        );
    }

    public function onNotificationSent(NotificationSent $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'send',
            resourceType: 'notification',
            resourceId: $event->notificationId->toString(),
            metadata: [
                'type' => $event->type->value,
                'event' => 'NotificationSent',
            ]
        );
    }

    public function onNotificationFailed(NotificationFailed $event): void
    {
        $this->logEvent(
            tenantId: $event->tenantId->toString(),
            actionType: 'fail',
            resourceType: 'notification',
            resourceId: $event->notificationId->toString(),
            metadata: [
                'type' => $event->type->value,
                'reason' => $event->reason,
                'attemptCount' => $event->attemptCount,
                'event' => 'NotificationFailed',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Wishlist Events
    // ──────────────────────────────────────────────

    public function onItemAddedToWishlist(ItemAddedToWishlist $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'add_item',
            resourceType: 'wishlist',
            resourceId: $event->wishlistId->toString(),
            metadata: [
                'productId' => $event->productId->toString(),
                'customerId' => $event->customerId,
                'event' => 'ItemAddedToWishlist',
            ]
        );
    }

    public function onItemRemovedFromWishlist(ItemRemovedFromWishlist $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'remove_item',
            resourceType: 'wishlist',
            resourceId: $event->wishlistId->toString(),
            metadata: [
                'productId' => $event->productId->toString(),
                'customerId' => $event->customerId,
                'event' => 'ItemRemovedFromWishlist',
            ]
        );
    }

    public function onWishlistCleared(WishlistCleared $event): void
    {
        $this->logEvent(
            tenantId: null,
            actionType: 'clear',
            resourceType: 'wishlist',
            resourceId: $event->wishlistId->toString(),
            metadata: [
                'customerId' => $event->customerId,
                'itemCount' => $event->itemCount,
                'event' => 'WishlistCleared',
            ]
        );
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /**
     * Log an audit entry.
     *
     * @param array<string, mixed> $metadata
     */
    private function logEvent(
        ?string $tenantId,
        string $actionType,
        string $resourceType,
        string $resourceId,
        array $metadata = [],
    ): void {
        try {
            // Get current user from security context
            $userId = null;
            $token = $this->tokenStorage->getToken();
            if ($token && $token->getUser() instanceof UserInterface) {
                $user = $token->getUser();
                // Assuming the user identifier is the user ID
                $userId = $user->getUserIdentifier();
            }

            // Get IP address and user agent from request
            $ipAddress = null;
            $userAgent = null;
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $ipAddress = $request->getClientIp();
                $userAgent = $request->headers->get('User-Agent');
            }

            // Use a default tenant ID for cross-tenant events (like UserCreated)
            if (null === $tenantId) {
                $tenantId = '00000000-0000-0000-0000-000000000000'; // System tenant
            }

            $command = new LogAuditEntry(
                tenantId: $tenantId,
                userId: $userId,
                actionType: $actionType,
                resourceType: $resourceType,
                resourceId: $resourceId,
                metadata: $metadata,
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            $this->commandBus->dispatch($command);

            $this->logger->debug('Audit log entry created', [
                'actionType' => $actionType,
                'resourceType' => $resourceType,
                'resourceId' => $resourceId,
            ]);
        } catch (\Throwable $exception) {
            // Log error but don't throw - audit logging shouldn't block domain events
            $this->logger->error('Failed to create audit log entry', [
                'actionType' => $actionType,
                'resourceType' => $resourceType,
                'resourceId' => $resourceId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}
