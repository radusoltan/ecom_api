<?php

declare(strict_types=1);

namespace App\Returns\Application\Query;

/**
 * Query: GetReturnRequestsByOrderId.
 *
 * Retrieve all return requests for a specific order.
 */
final readonly class GetReturnRequestsByOrderId
{
    public function __construct(
        public string $orderId,
    ) {
    }
}
