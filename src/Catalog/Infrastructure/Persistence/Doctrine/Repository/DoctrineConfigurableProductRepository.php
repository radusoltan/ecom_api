<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\ConfigurableProduct;
use App\Catalog\Domain\Model\ConfigurableProductId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ConfigurableProductRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ConfigurableProductEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Doctrine repository implementation for ConfigurableProduct
 */
class DoctrineConfigurableProductRepository extends ServiceEntityRepository implements ConfigurableProductRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfigurableProductEntity::class);
    }

    public function save(ConfigurableProduct $configurableProduct): void
    {
        $entity = $this->find($configurableProduct->getId()->toString());

        if ($entity) {
            // Update existing entity
            $entity->updateFromDomainModel($configurableProduct);
        } else {
            // Create new entity
            $entity = ConfigurableProductEntity::fromDomainModel($configurableProduct);
            $this->getEntityManager()->persist($entity);
        }

        $this->getEntityManager()->flush();

        // Dispatch domain events
        foreach ($configurableProduct->pullDomainEvents() as $event) {
            // Events would be dispatched via event bus here
        }
    }

    public function findById(ConfigurableProductId $id, TenantId $tenantId): ?ConfigurableProduct
    {
        $entity = $this->findOneBy([
            'id' => $id->toString(),
            'tenantId' => $tenantId->toString(),
        ]);

        return $entity?->toDomainModel();
    }

    public function findByProductId(ProductId $productId, TenantId $tenantId): ?ConfigurableProduct
    {
        $entity = $this->findOneBy([
            'productId' => $productId->toString(),
            'tenantId' => $tenantId->toString(),
        ]);

        return $entity?->toDomainModel();
    }

    public function exists(ConfigurableProductId $id, TenantId $tenantId): bool
    {
        return null !== $this->findOneBy([
            'id' => $id->toString(),
            'tenantId' => $tenantId->toString(),
        ]);
    }

    public function delete(ConfigurableProduct $configurableProduct): void
    {
        $entity = $this->find($configurableProduct->getId()->toString());

        if ($entity) {
            $this->getEntityManager()->remove($entity);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return ConfigurableProduct[]
     */
    public function findAllByTenant(TenantId $tenantId, int $limit = 100, int $offset = 0): array
    {
        $entities = $this->findBy(
            ['tenantId' => $tenantId->toString()],
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );

        return array_map(
            fn(ConfigurableProductEntity $entity) => $entity->toDomainModel(),
            $entities
        );
    }

    public function countByTenant(TenantId $tenantId): int
    {
        return $this->count(['tenantId' => $tenantId->toString()]);
    }
}
