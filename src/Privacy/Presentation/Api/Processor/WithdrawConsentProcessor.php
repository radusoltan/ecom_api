<?php

declare(strict_types=1);

namespace App\Privacy\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Privacy\Application\Command\WithdrawConsentCommand;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\ConsentEntity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<ConsentEntity, ConsentEntity>
 */
final readonly class WithdrawConsentProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private ConsentRepositoryInterface $consentRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConsentEntity
    {
        $id = $uriVariables['id'] ?? null;
        if (null === $id) {
            throw new NotFoundHttpException('Consent ID is required');
        }

        $consentId = ConsentId::fromString($id);

        $consent = $this->consentRepository->findById($consentId);
        if (null === $consent) {
            throw new NotFoundHttpException(sprintf('Consent "%s" not found', $id));
        }

        $command = new WithdrawConsentCommand(
            consentId: $consentId,
        );

        $this->commandBus->dispatch($command);

        // Reload updated consent
        $updatedConsent = $this->consentRepository->findById($consentId);

        return ConsentEntity::fromDomainModel($updatedConsent ?? $consent);
    }
}
