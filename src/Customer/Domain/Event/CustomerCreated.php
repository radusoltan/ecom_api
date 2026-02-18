<?php

declare(strict_types=1);

namespace App\Customer\Domain\Event;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class CustomerCreated
{
    public function __construct(
        private CustomerId $customerId,
        private TenantId $tenantId,
        private Email $email,
        private string $firstName,
        private string $lastName,
    ) {
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }
}
