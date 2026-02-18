<?php

declare(strict_types=1);

namespace App\Customer\Application\Query;

use App\Customer\Application\DTO\CustomerDTO;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCustomerByIdQueryHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function __invoke(GetCustomerByIdQuery $query): ?CustomerDTO
    {
        $customerId = CustomerId::fromString($query->customerId);
        $tenantId = TenantId::fromString($query->tenantId);

        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            return null;
        }

        return CustomerDTO::fromDomain($customer);
    }
}
