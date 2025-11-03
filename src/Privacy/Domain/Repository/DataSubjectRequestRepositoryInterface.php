<?php

declare(strict_types=1);

namespace App\Privacy\Domain\Repository;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Privacy\Domain\Model\DataSubjectRequest;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Domain\ValueObject\RequestStatus;
use App\Privacy\Domain\ValueObject\RequestType;
use App\Shared\Domain\ValueObject\TenantId;

interface DataSubjectRequestRepositoryInterface
{
    public function save(DataSubjectRequest $request): void;

    public function findById(DataSubjectRequestId $id): ?DataSubjectRequest;

    /**
     * Find all requests for a customer
     *
     * @return DataSubjectRequest[]
     */
    public function findByCustomerId(CustomerId $customerId): array;

    /**
     * Find all requests for a tenant
     *
     * @return DataSubjectRequest[]
     */
    public function findByTenantId(TenantId $tenantId): array;

    /**
     * Find requests by status
     *
     * @return DataSubjectRequest[]
     */
    public function findByStatus(RequestStatus $status, ?TenantId $tenantId = null): array;

    /**
     * Find requests by type
     *
     * @return DataSubjectRequest[]
     */
    public function findByType(RequestType $type, ?TenantId $tenantId = null): array;

    /**
     * Find overdue requests (past deadline and not completed/rejected)
     *
     * @return DataSubjectRequest[]
     */
    public function findOverdueRequests(?TenantId $tenantId = null): array;

    /**
     * Check if customer has pending erasure request
     */
    public function hasPendingErasureRequest(CustomerId $customerId): bool;
}
