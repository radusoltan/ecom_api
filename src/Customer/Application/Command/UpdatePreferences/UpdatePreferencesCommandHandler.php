<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdatePreferences;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerPreferences;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Update Customer Preferences Command Handler.
 *
 * Handles updating customer preferences with validation.
 */
#[AsMessageHandler]
final readonly class UpdatePreferencesCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function __invoke(UpdatePreferencesCommand $command): void
    {
        $customerId = CustomerId::fromString($command->customerId);
        $tenantId = TenantId::fromString($command->tenantId);

        // Find customer
        $customer = $this->customerRepository->findById($customerId, $tenantId);

        if (null === $customer) {
            throw new \InvalidArgumentException(sprintf('Customer with ID "%s" not found', $command->customerId));
        }

        // Create preferences from array (validation happens here)
        $preferences = CustomerPreferences::fromArray($command->preferences);

        // Update preferences (records event)
        $customer->updatePreferences($preferences);

        // Save customer
        $this->customerRepository->save($customer);
    }
}
