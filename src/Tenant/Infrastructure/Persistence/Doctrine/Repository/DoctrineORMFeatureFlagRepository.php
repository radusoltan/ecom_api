<?php

declare(strict_types=1);

namespace App\Tenant\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tenant\Domain\Model\FeatureFlag;
use App\Tenant\Domain\Model\FeatureFlagId;
use App\Tenant\Domain\Repository\FeatureFlagRepositoryInterface;
use App\Tenant\Infrastructure\Persistence\Doctrine\Entity\FeatureFlagEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class DoctrineORMFeatureFlagRepository implements FeatureFlagRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function save(FeatureFlag $flag): void
    {
        $entity = $this->entityManager->find(FeatureFlagEntity::class, $flag->id()->toString());
        if (null === $entity) {
            $entity = FeatureFlagEntity::fromDomainModel($flag);
            $this->entityManager->persist($entity);
        } else {
            $entity->updateFromDomainModel($flag);
        }
        $this->entityManager->flush();

        foreach ($flag->popEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function findById(FeatureFlagId $id): ?FeatureFlag
    {
        $entity = $this->entityManager->find(FeatureFlagEntity::class, $id->toString());

        return $entity?->toDomainModel();
    }

    public function findByTenantAndFeature(TenantId $tenantId, string $featureName): ?FeatureFlag
    {
        $entity = $this->entityManager->getRepository(FeatureFlagEntity::class)->findOneBy([
            'tenantId' => $tenantId->toString(),
            'featureName' => $featureName,
        ]);

        return $entity?->toDomainModel();
    }

    /** @return FeatureFlag[] */
    public function findAllByTenant(TenantId $tenantId): array
    {
        $entities = $this->entityManager->getRepository(FeatureFlagEntity::class)->findBy([
            'tenantId' => $tenantId->toString(),
        ]);

        return array_map(fn (FeatureFlagEntity $e) => $e->toDomainModel(), $entities);
    }

    public function delete(FeatureFlag $flag): void
    {
        $entity = $this->entityManager->find(FeatureFlagEntity::class, $flag->id()->toString());
        if (null !== $entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
