<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerAddress;
use App\Customer\Domain\ValueObject\CustomerAddressId;
use App\Customer\Domain\ValueObject\CustomerId;

final readonly class CustomerAddressUpdated
{
    public function __construct(
        private CustomerId $customerId,
        private CustomerAddressId $addressId,
        private CustomerAddress $newAddress
    ) {
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function addressId(): CustomerAddressId
    {
        return $this->addressId;
    }

    public function newAddress(): CustomerAddress
    {
        return $this->newAddress;
    }
}
