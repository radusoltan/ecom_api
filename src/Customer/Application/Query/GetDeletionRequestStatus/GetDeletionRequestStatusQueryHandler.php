<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetDeletionRequestStatus;

use App\Customer\Application\DTO\DeletionRequestStatusDTO;
use App\Customer\Domain\Repository\DeletionRequestRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetDeletionRequestStatusQueryHandler
{
    public function __construct(
        private DeletionRequestRepositoryInterface $deletionRequestRepository,
    ) {
    }

    public function __invoke(GetDeletionRequestStatusQuery $query): ?DeletionRequestStatusDTO
    {
        $request = $this->deletionRequestRepository->findByCustomerId(
            $query->customerId,
            $query->tenantId
        );

        if (null === $request) {
            return null;
        }

        return DeletionRequestStatusDTO::fromDomainModel($request);
    }
}
