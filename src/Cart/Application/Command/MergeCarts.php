<?php

declare(strict_types=1);

namespace App\Cart\Application\Command;

/**
 * MergeCarts Command.
 *
 * Merges items from a source cart (typically guest cart) into a target cart (customer cart).
 * When a customer logs in and already has a cart, their guest cart items should be merged.
 *
 * Merge behavior:
 * - Items with matching productId+variantId have quantities added
 * - Unique items are added to the target cart
 * - Source cart is cleared after merge (but not deleted)
 */
final readonly class MergeCarts
{
    public function __construct(
        public string $sourceCartId,  // Guest cart to merge from
        public string $targetCartId,  // Customer cart to merge into
        public bool $deleteSourceAfterMerge = true,
    ) {
    }
}
