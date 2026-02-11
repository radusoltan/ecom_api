<?php

declare(strict_types=1);

namespace App\Cart\Domain\Repository;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\SessionId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Cart Repository Interface (Port).
 *
 * Defines the contract for cart persistence operations
 */
interface CartRepositoryInterface
{
    /**
     * Save or update a cart.
     */
    public function save(Cart $cart): void;

    /**
     * Find cart by ID.
     */
    public function findById(CartId $id): ?Cart;

    /**
     * Find cart by customer ID and tenant (for authenticated users).
     */
    public function findByCustomerId(CustomerId $customerId, TenantId $tenantId): ?Cart;

    /**
     * Find cart by session ID and tenant (for guest users).
     */
    public function findBySessionId(SessionId $sessionId, TenantId $tenantId): ?Cart;

    /**
     * Remove a cart.
     */
    public function remove(Cart $cart): void;

    /**
     * Find expired carts (not updated before the given date).
     *
     * @return Cart[]
     */
    public function findExpired(\DateTimeImmutable $before): array;

    /**
     * Find active carts by tenant and customer email.
     *
     * Used for clearing carts after order placement when we don't have direct cart ID
     * Returns carts sorted by updatedAt DESC (most recent first)
     *
     * @return Cart[]
     */
    public function findActiveByTenantAndEmail(TenantId $tenantId, string $customerEmail): array;

    /**
     * Find abandoned carts ready for email notification.
     *
     * Returns active carts that:
     * - Have not been updated in the specified time window
     * - Have not already received abandonment email
     * - Have at least one item
     *
     * @param \DateTimeImmutable $abandonedBefore Carts not updated since this time
     * @param int $limit Maximum number of carts to return
     * @return CartId[] Array of cart IDs
     */
    public function findAbandonedCartsForEmail(\DateTimeImmutable $abandonedBefore, int $limit = 100): array;

    /**
     * Mark cart as having received abandonment email.
     */
    public function markAbandonmentEmailSent(CartId $cartId): void;
}
