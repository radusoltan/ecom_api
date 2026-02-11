<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdateNotificationPreferences;

use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\NotificationPreferences;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for UpdateNotificationPreferencesCommand.
 *
 * Updates a customer's notification preferences with business rule validation.
 */
#[AsMessageHandler]
final readonly class UpdateNotificationPreferencesCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {
    }

    public function __invoke(UpdateNotificationPreferencesCommand $command): void
    {
        // Load customer
        $customer = $this->customerRepository->findById($command->customerId, $command->tenantId);

        if (null === $customer) {
            throw new \DomainException(sprintf('Customer not found: %s', $command->customerId->toString()));
        }

        // Create new notification preferences
        $newPreferences = NotificationPreferences::create(
            orderUpdates: $command->orderUpdates,
            shippingUpdates: $command->shippingUpdates,
            promotionalOffers: $command->promotionalOffers,
            priceDropAlerts: $command->priceDropAlerts,
            backInStockAlerts: $command->backInStockAlerts,
            securityAlerts: $command->securityAlerts,
            newsletterWeekly: $command->newsletterWeekly,
            preferSms: $command->preferSms
        );

        // Update (business rules are validated in the aggregate)
        $customer->updateNotificationPreferences($newPreferences);

        // Persist
        $this->customerRepository->save($customer);
    }
}
