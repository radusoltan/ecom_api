<?php

declare(strict_types=1);

namespace App\Customer\Domain\Repository;

use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;

interface CustomerRepositoryInterface
{
    public function save(Customer $customer): void;

    public function findById(CustomerId $id, TenantId $tenantId): ?Customer;

    public function findByEmail(Email $email, TenantId $tenantId): ?Customer;

    /**
     * Find all customers for tenant.
     *
     * @return Customer[]
     */
    public function findAll(TenantId $tenantId): array;

    /**
     * Find customers by segment.
     *
     * @return Customer[]
     */
    public function findBySegment(string $segment, TenantId $tenantId): array;

    public function delete(Customer $customer): void;
}
