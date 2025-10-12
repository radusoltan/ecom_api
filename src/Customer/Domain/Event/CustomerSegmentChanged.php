<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;


final readonly class CustomerSegmentChanged
{
    public function __construct(
        private CustomerId $customerId,
        private CustomerSegment $oldSegment,
        private CustomerSegment $newSegment
    ) {
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function oldSegment(): CustomerSegment
    {
        return $this->oldSegment;
    }

    public function newSegment(): CustomerSegment
    {
        return $this->newSegment;
    }
}
