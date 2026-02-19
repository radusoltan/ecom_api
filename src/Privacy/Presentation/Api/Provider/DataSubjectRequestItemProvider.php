<?php

declare(strict_types=1);

namespace App\Privacy\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Privacy\Domain\Repository\DataSubjectRequestRepositoryInterface;
use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use App\Privacy\Infrastructure\Persistence\Doctrine\Entity\DataSubjectRequestEntity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<DataSubjectRequestEntity>
 */
final readonly class DataSubjectRequestItemProvider implements ProviderInterface
{
    public function __construct(
        private DataSubjectRequestRepositoryInterface $requestRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DataSubjectRequestEntity
    {
        $id = $uriVariables['id'] ?? null;
        if (null === $id) {
            throw new NotFoundHttpException('Data subject request ID is required');
        }

        $dsRequest = $this->requestRepository->findById(
            DataSubjectRequestId::fromString($id),
        );

        if (null === $dsRequest) {
            throw new NotFoundHttpException(sprintf('Data subject request "%s" not found', $id));
        }

        return DataSubjectRequestEntity::fromDomainModel($dsRequest);
    }
}
