<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\GetNotificationPreferences;

use App\Customer\Application\DTO\NotificationPreferencesDTO;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for GetNotificationPreferencesQuery.
 *
 * Retrieves customer notification preferences.
 */
#[AsMessageHandler]
final readonly class GetNotificationPreferencesQueryHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function __invoke(GetNotificationPreferencesQuery $query): NotificationPreferencesDTO
    {
        // Load customer
        $customer = $this->customerRepository->findById($query->customerId, $query->tenantId);

        if (null === $customer) {
            throw new \DomainException(sprintf('Customer not found: %s', $query->customerId->toString()));
        }

        // Return DTO
        return NotificationPreferencesDTO::fromDomainModel(
            $customer->getNotificationPreferences(),
            $customer->updatedAt()
        );
    }
}
