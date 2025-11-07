<?php

declare(strict_types=1);

namespace App\Privacy\Application\Query;

use App\Privacy\Application\DTO\DataSubjectRequestDTO;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCustomerDataSubjectRequestsQueryHandler
{
    public function __construct(
        private DataSubjectRequestRepositoryInterface $requestRepository
    ) {
    }

    /**
     * @return DataSubjectRequestDTO[]
     */
    public function __invoke(GetCustomerDataSubjectRequestsQuery $query): array
    {
        $requests = $this->requestRepository->findByCustomerId($query->customerId);

        return array_map(
            fn ($request) => DataSubjectRequestDTO::fromDomainModel($request),
            $requests
        );
    }
}
