<?php

declare(strict_types=1);

namespace App\Privacy\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Privacy\Domain\Repository\ConsentRepositoryInterface;
use App\Privacy\Domain\ValueObject\ConsentId;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\ConsentEntity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ConsentEntity>
 */
final readonly class ConsentItemProvider implements ProviderInterface
{
    public function __construct(
        private ConsentRepositoryInterface $consentRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ConsentEntity
    {
        $id = $uriVariables['id'] ?? null;
        if (null === $id) {
            throw new NotFoundHttpException('Consent ID is required');
        }

        $consent = $this->consentRepository->findById(ConsentId::fromString($id));
        if (null === $consent) {
            throw new NotFoundHttpException(sprintf('Consent "%s" not found', $id));
        }

        return ConsentEntity::fromDomainModel($consent);
    }
}
