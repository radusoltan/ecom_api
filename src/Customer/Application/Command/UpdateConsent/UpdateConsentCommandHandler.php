<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdateConsent;

use App\Customer\Domain\Model\ConsentHistory;
use App\Customer\Domain\Repository\ConsentHistoryRepositoryInterface;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Update Consent Command Handler.
 *
 * Updates a customer's consent and creates an audit trail entry.
 */
#[AsMessageHandler]
final readonly class UpdateConsentCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private ConsentHistoryRepositoryInterface $consentHistoryRepository
    ) {
    }

    public function __invoke(UpdateConsentCommand $command): void
    {
        // Load customer aggregate
        $customer = $this->customerRepository->findById($command->customerId, $command->tenantId);

        if (null === $customer) {
            throw new \DomainException(sprintf('Customer with ID "%s" not found', $command->customerId->toString()));
        }

        // Update consent (domain logic + event recording)
        $customer->updateConsent($command->consentType, $command->granted);

        // Create audit trail record
        $history = ConsentHistory::record(
            customerId: $command->customerId,
            tenantId: $command->tenantId,
            consentType: $command->consentType,
            granted: $command->granted,
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent
        );

        // Persist both
        $this->customerRepository->save($customer);
        $this->consentHistoryRepository->save($history);

        // Domain events are dispatched by repository
    }
}
