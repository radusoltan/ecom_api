<?php

declare(strict_types=1);

namespace App\Cart\Application\EventSubscriber;

use App\Cart\Application\Command\ClearCart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\Event\OrderPlaced;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * OrderPlacedCartClearingSubscriber
 *
 * Listens to OrderPlaced events and clears the associated cart.
 *
 * Event Flow:
 * 1. Order is placed via PlaceOrderCommand
 * 2. OrderPlaced event is dispatched
 * 3. This subscriber receives the event
 * 4. Cart is cleared via ClearCart command
 * 5. Cart status is marked as CONVERTED
 *
 * Business Rules:
 * - Cart is cleared only after successful order placement
 * - Cart status changes from ACTIVE to CONVERTED
 * - If cart clearing fails, order is still created (cart clearing is not critical)
 * - Failed cart clearing is logged but does not throw exceptions
 *
 * Integration Points:
 * - Order Context: OrderPlaced event
 * - Cart Context: ClearCart command, CartRepository
 */
final readonly class OrderPlacedCartClearingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderPlaced::class => 'onOrderPlaced',
        ];
    }

    /**
     * Handle OrderPlaced event
     *
     * Strategy:
     * - Find cart by session ID or customer ID (from order context)
     * - Mark cart as CONVERTED
     * - Clear cart items
     * - Log any failures (non-critical)
     *
     * Note: We don't have direct cart ID from OrderPlaced event, so we need to:
     * 1. Find cart by customer email + tenant ID
     * 2. Or find cart by session ID (if stored in order metadata)
     *
     * For now, we'll implement a simple approach:
     * - Find active carts for this tenant + customer email
     * - Clear the most recent one
     */
    public function onOrderPlaced(OrderPlaced $event): void
    {
        try {
            $this->logger->info('OrderPlaced event received, attempting to clear cart', [
                'order_id' => $event->orderId->toString(),
                'tenant_id' => $event->tenantId->toString(),
                'customer_email' => $event->customerEmail,
            ]);

            // Find active cart by tenant and customer email
            // Note: This assumes cart repository has a method to find by email
            // For session-based carts, we would need session ID from order metadata
            $carts = $this->cartRepository->findActiveByTenantAndEmail(
                $event->tenantId,
                $event->customerEmail
            );

            if (empty($carts)) {
                $this->logger->info('No active cart found for customer, skipping cart clearing', [
                    'order_id' => $event->orderId->toString(),
                    'customer_email' => $event->customerEmail,
                ]);
                return;
            }

            // Get the most recently updated cart
            $cart = $carts[0]; // Repository should return sorted by updatedAt DESC

            // Mark cart as converted
            $cart->markAsConverted();

            // Clear cart items
            $cart->clear();

            // Save cart
            $this->cartRepository->save($cart);

            $this->logger->info('Cart cleared successfully after order placement', [
                'order_id' => $event->orderId->toString(),
                'cart_id' => $cart->id()->toString(),
            ]);
        } catch (\Throwable $e) {
            // Log error but don't throw - cart clearing failure should not affect order
            $this->logger->error('Failed to clear cart after order placement', [
                'order_id' => $event->orderId->toString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
