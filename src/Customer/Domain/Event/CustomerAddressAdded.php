<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerId;

final readonly class CustomerAddressAdded
{
    public function __construct(
        private CustomerId $customerId,
        private CustomerAddress $address
    ) {
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function address(): CustomerAddress
    {
        return $this->address;
    }
}
