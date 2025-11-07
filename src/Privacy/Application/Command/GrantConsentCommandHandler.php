<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command;

use App\Privacy\Domain\Model\Consent;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\ValueObject\ConsentId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GrantConsentCommandHandler
{
    public function __construct(
        private ConsentRepositoryInterface $consentRepository
    ) {
    }

    public function __invoke(GrantConsentCommand $command): ConsentId
    {
        // Check if consent already exists for this purpose
        $existingConsent = $this->consentRepository->findActiveByCustomerAndPurpose(
            $command->customerId,
            $command->purpose
        );

        if (null !== $existingConsent) {
            // Consent already granted, return existing ID
            return $existingConsent->id();
        }

        $consent = Consent::grant(
            ConsentId::generate(),
            $command->tenantId,
            $command->customerId,
            $command->purpose,
            $command->ipAddress,
            $command->userAgent,
            $command->consentText,
            $command->consentVersion
        );

        $this->consentRepository->save($consent);

        return $consent->id();
    }
}
