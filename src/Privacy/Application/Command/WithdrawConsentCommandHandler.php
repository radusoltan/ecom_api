<?php

declare(strict_types=1);

namespace App\Privacy\Application\Command;

use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class WithdrawConsentCommandHandler
{
    public function __construct(
        private ConsentRepositoryInterface $consentRepository
    ) {
    }

    public function __invoke(WithdrawConsentCommand $command): void
    {
        $consent = $this->consentRepository->findById($command->consentId);

        if ($consent === null) {
            throw new InvalidArgumentException(
                sprintf('Consent not found: %s', $command->consentId->toString())
            );
        }

        $consent->withdraw();

        $this->consentRepository->save($consent);
    }
}
