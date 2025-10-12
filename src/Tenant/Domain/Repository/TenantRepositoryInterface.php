<?php

declare(strict_types=1);

namespace App\Tenant\Domain\Repository;

use App\Shared\Domain\ValueObject\Email;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\ValueObject\TenantId;

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
