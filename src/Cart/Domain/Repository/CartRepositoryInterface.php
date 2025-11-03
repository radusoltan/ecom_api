<?php

declare(strict_types=1);

namespace App\Cart\Domain\Repository;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\SessionId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use DateTimeImmutable;

/**
 * Cart Repository Interface (Port)
 *
 * Defines the contract for cart persistence operations
 */
interface CartRepositoryInterface
{
    /**
     * Save or update a cart
     */
    public function save(Cart $cart): void;

    /**
     * Find cart by ID
     */
    public function findById(CartId $id): ?Cart;

    /**
     * Find cart by customer ID and tenant (for authenticated users)
     */
    public function findByCustomerId(CustomerId $customerId, TenantId $tenantId): ?Cart;

    /**
     * Find cart by session ID and tenant (for guest users)
     */
    public function findBySessionId(SessionId $sessionId, TenantId $tenantId): ?Cart;

    /**
     * Remove a cart
     */
    public function remove(Cart $cart): void;

    /**
     * Find expired carts (not updated before the given date)
     *
     * @return Cart[]
     */
    public function findExpired(DateTimeImmutable $before): array;

    /**
     * Find active carts by tenant and customer email
     *
     * Used for clearing carts after order placement when we don't have direct cart ID
     * Returns carts sorted by updatedAt DESC (most recent first)
     *
     * @return Cart[]
     */
    public function findActiveByTenantAndEmail(TenantId $tenantId, string $customerEmail): array;
}