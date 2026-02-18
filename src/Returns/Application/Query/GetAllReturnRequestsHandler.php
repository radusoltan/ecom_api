<?php

declare(strict_types=1);

namespace App\Returns\Application\Query;

use App\Returns\Application\DTO\ReturnRequestDTO;
use App\Returns\Domain\Repository\ReturnRequestRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GetAllReturnRequests query.
 */
#[AsMessageHandler]
final readonly class GetAllReturnRequestsHandler
{
    public function __construct(
        private ReturnRequestRepositoryInterface $returnRequestRepository,
    ) {
    }

    /**
     * @return array<ReturnRequestDTO>
     */
    public function __invoke(GetAllReturnRequests $query): array
    {
        $tenantId = TenantId::fromString($query->tenantId);

        $returnRequests = null !== $query->status
            ? $this->returnRequestRepository->findByStatus($query->status, $tenantId)
            : $this->returnRequestRepository->findByTenantId($tenantId);

        return array_map(
            fn ($returnRequest) => ReturnRequestDTO::fromDomain($returnRequest),
            $returnRequests
        );
    }
}
