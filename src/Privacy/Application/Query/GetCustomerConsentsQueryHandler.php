<?php

declare(strict_types=1);

namespace App\Privacy\Application\Query;

use App\Privacy\Application\DTO\ConsentDTO;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCustomerConsentsQueryHandler
{
    public function __construct(
        private ConsentRepositoryInterface $consentRepository,
    ) {
    }

    /**
     * @return ConsentDTO[]
     */
    public function __invoke(GetCustomerConsentsQuery $query): array
    {
        $consents = $query->activeOnly
            ? $this->consentRepository->findActiveByCustomerId($query->customerId)
            : $this->consentRepository->findByCustomerId($query->customerId);

        return array_map(
            fn ($consent) => ConsentDTO::fromDomainModel($consent),
            $consents
        );
    }
}
