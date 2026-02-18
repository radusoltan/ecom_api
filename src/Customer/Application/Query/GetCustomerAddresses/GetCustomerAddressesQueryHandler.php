<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetCustomerAddresses;

use App\Customer\Application\DTO\CustomerAddressDTO;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\AddressType;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCustomerAddressesQueryHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    /**
     * @return CustomerAddressDTO[]
     */
    public function __invoke(GetCustomerAddressesQuery $query): array
    {
        $customerId = CustomerId::fromString($query->customerId);
        $tenantId = TenantId::fromString($query->tenantId);

        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $query->customerId));
        }

        $addresses = $customer->getAddresses();

        // Filter by type if specified
        if (null !== $query->type) {
            $filterType = AddressType::fromString($query->type);
            $addresses = array_filter(
                $addresses,
                function ($address) use ($filterType) {
                    // If filter is 'shipping', include 'shipping' and 'both'
                    if (AddressType::SHIPPING === $filterType) {
                        return $address->type->isShipping();
                    }
                    // If filter is 'billing', include 'billing' and 'both'
                    if (AddressType::BILLING === $filterType) {
                        return $address->type->isBilling();
                    }

                    // If filter is 'both', include only 'both'
                    return $address->type === $filterType;
                }
            );
        }

        return array_map(
            static fn ($address) => CustomerAddressDTO::fromDomainModel(
                $address,
                $query->customerId,
                $query->tenantId
            ),
            array_values($addresses)
        );
    }
}
