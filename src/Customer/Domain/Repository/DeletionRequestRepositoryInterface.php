<?php

declare(strict_types=1);

namespace App\Customer\Domain\Repository;

use App\Customer\Domain\Model\DeletionRequest;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\DeletionRequestId;
use App\Shared\Domain\ValueObject\TenantId;

interface DeletionRequestRepositoryInterface
{
    public function save(DeletionRequest $request): void;

    public function findById(DeletionRequestId $id, TenantId $tenantId): ?DeletionRequest;

    public function findByCustomerId(CustomerId $customerId, TenantId $tenantId): ?DeletionRequest;

    public function findPendingByCustomerId(CustomerId $customerId, TenantId $tenantId): ?DeletionRequest;

    /**
     * Find all deletion requests ready for execution.
     * (Confirmed, past scheduled date, not on hold).
     *
     * @return DeletionRequest[]
     */
    public function findReadyForExecution(): array;
}
