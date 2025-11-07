<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Repository;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\Tenant;

interface TenantRepositoryInterface
{
    public function save(Tenant $tenant): void;

    public function delete(Tenant $tenant): void;

    public function findById(TenantId $id): ?Tenant;

    public function findByOwnerEmail(Email $email): ?Tenant;

    /**
     * @return Tenant[]
     */
    public function findAll(): array;
}
