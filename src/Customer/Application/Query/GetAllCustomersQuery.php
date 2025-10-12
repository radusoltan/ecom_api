<?php

declare(strict_types=1);

namespace App\Customer\Application\Query;

final readonly class GetAllCustomersQuery
{
    public function __construct(
        public string $tenantId,
        public ?string $segment = null
    ) {
    }
}
