<?php

declare(strict_types=1);

namespace App\Customer\Application\Command\UpdateAllConsents;

use App\Customer\Domain\Model\ConsentHistory;
use App\Customer\Domain\Repository\ConsentHistoryRepositoryInterface;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\ConsentType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Update All Consents Command Handler.
 *
 * Updates all customer consents at once and creates audit trail entries for each change.
 * Typically used when customer submits consent preferences via cookie/privacy banner.
 */
#[AsMessageHandler]
final readonly class UpdateAllConsentsCommandHandler
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private ConsentHistoryRepositoryInterface $consentHistoryRepository
    ) {
    }

    public function __invoke(UpdateAllConsentsCommand $command): void
    {
        // Load customer aggregate
        $customer = $this->customerRepository->findById($command->customerId, $command->tenantId);

        if (null === $customer) {
            throw new \DomainException(sprintf('Customer with ID "%s" not found', $command->customerId->toString()));
        }

        // Get current consents to detect changes
        $currentConsents = $customer->getConsents();

        // Update each consent if it changed
        /** @var array<array{type: ConsentType, old: bool, new: bool}> $consentChanges */
        $consentChanges = [
            [
                'type' => ConsentType::MARKETING_EMAIL,
                'old' => $currentConsents->marketingEmail(),
                'new' => $command->marketingEmail,
            ],
            [
                'type' => ConsentType::MARKETING_SMS,
                'old' => $currentConsents->marketingSms(),
                'new' => $command->marketingSms,
            ],
            [
                'type' => ConsentType::THIRD_PARTY_SHARING,
                'old' => $currentConsents->thirdPartySharing(),
                'new' => $command->thirdPartySharing,
            ],
            [
                'type' => ConsentType::ANALYTICS_TRACKING,
                'old' => $currentConsents->analyticsTracking(),
                'new' => $command->analyticsTracking,
            ],
        ];

        // Update consents and create history records only for changes
        foreach ($consentChanges as $change) {
            if ($change['old'] !== $change['new']) {
                // Update consent in aggregate
                $customer->updateConsent($change['type'], $change['new']);

                // Create audit trail record
                $history = ConsentHistory::record(
                    customerId: $command->customerId,
                    tenantId: $command->tenantId,
                    consentType: $change['type'],
                    granted: $change['new'],
                    ipAddress: $command->ipAddress,
                    userAgent: $command->userAgent
                );

                $this->consentHistoryRepository->save($history);
            }
        }

        // Persist customer (saves all consent changes)
        $this->customerRepository->save($customer);

        // Domain events are dispatched by repository
    }
}
