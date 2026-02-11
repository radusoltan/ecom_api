<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetCustomerConsents;

use App\Customer\Application\DTO\CustomerConsentsDTO;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Customer Consents Query Handler.
 *
 * Retrieves customer's current consent status.
 */
#[AsMessageHandler]
final readonly class GetCustomerConsentsQueryHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    public function __invoke(GetCustomerConsentsQuery $query): CustomerConsentsDTO
    {
        $customer = $this->customerRepository->findById($query->customerId, $query->tenantId);

        if (null === $customer) {
            throw new \DomainException(sprintf('Customer with ID "%s" not found', $query->customerId->toString()));
        }

        $consents = $customer->getConsents();

        return new CustomerConsentsDTO(
            customerId: $customer->id()->toString(),
            marketingEmail: $consents->marketingEmail(),
            marketingSms: $consents->marketingSms(),
            thirdPartySharing: $consents->thirdPartySharing(),
            analyticsTracking: $consents->analyticsTracking(),
            lastUpdatedAt: $customer->updatedAt()
        );
    }
}
