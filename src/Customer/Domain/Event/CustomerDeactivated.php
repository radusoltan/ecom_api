<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;


final readonly class CustomerDeactivated
{
    public function __construct(
        private CustomerId $customerId
    ) {
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }
}
