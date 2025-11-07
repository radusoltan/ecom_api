<?php

declare(strict_types=1);

namespace App\Privacy\Application\Query;

use App\Privacy\Application\DTO\DataSubjectRequestDTO;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetDataSubjectRequestQueryHandler
{
    public function __construct(
        private DataSubjectRequestRepositoryInterface $requestRepository
    ) {
    }

    public function __invoke(GetDataSubjectRequestQuery $query): DataSubjectRequestDTO
    {
        $request = $this->requestRepository->findById($query->requestId);

        if (null === $request) {
            throw new \InvalidArgumentException(sprintf('Data subject request not found: %s', $query->requestId->toString()));
        }

        return DataSubjectRequestDTO::fromDomainModel($request);
    }
}
