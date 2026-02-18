<?php

declare(strict_types=1);

namespace App\Returns\Application\Query;

/**
 * Query: GetReturnRequestById.
 *
 * Retrieve a return request by its ID.
 */
final readonly class GetReturnRequestById
{
    public function __construct(
        public string $returnRequestId,
    ) {
    }
}
