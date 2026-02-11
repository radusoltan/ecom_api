<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetCustomerPreferences;

use App\Customer\Application\DTO\CustomerPreferencesDTO;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Customer Preferences Query Handler.
 *
 * Handles retrieving customer preferences.
 */
#[AsMessageHandler]
final readonly class GetCustomerPreferencesQueryHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    public function __invoke(GetCustomerPreferencesQuery $query): CustomerPreferencesDTO
    {
        $customerId = CustomerId::fromString($query->customerId);
        $tenantId = TenantId::fromString($query->tenantId);

        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $query->customerId));
        }

        return CustomerPreferencesDTO::fromPreferences($customer->preferences());
    }
}
