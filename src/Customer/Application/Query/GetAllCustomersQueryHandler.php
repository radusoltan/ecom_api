<?php

declare(strict_types=1);

namespace App\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAllCustomersQueryHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * @return CustomerDTO[]
     */
    public function __invoke(GetAllCustomersQuery $query): array
    {
        $tenantId = TenantId::fromString($query->tenantId);

        if ($query->segment !== null) {
            $customers = $this->customerRepository->findBySegment($query->segment, $tenantId);
        } else {
            $customers = $this->customerRepository->findAll($tenantId);
        }

        return array_map(
            fn($customer) => CustomerDTO::fromDomain($customer),
            $customers
        );
    }
}
