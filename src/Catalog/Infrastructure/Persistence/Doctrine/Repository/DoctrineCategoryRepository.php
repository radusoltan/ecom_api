<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class DoctrineCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $eventBus
    ) {}

    public function save(Category $category): void
    {
        // Check if entity already exists
        $existingEntity = $this->entityManager->find(CategoryEntity::class, $category->id()->toString());

        if ($existingEntity !== null) {
            // Update existing entity - use its setters instead of creating new instance
            $existingEntity->setTenantId($category->tenantId()->toString());
            $existingEntity->setName($category->name()->value());
            $existingEntity->setDescription($category->description());
            $existingEntity->setParentId($category->parentId()?->toString());
            $existingEntity->setPosition($category->position());
            $existingEntity->setActive($category->isActive());
            $existingEntity->setShowOnFront($category->showOnFront());
            $existingEntity->setCoverImage($category->coverImage());
            // Doctrine will auto-detect changes and update on flush
        } else {
            // Create new entity
            $entity = CategoryEntity::fromDomainModel($category);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($category->popEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function findById(CategoryId $id): ?Category
    {
        $entity = $this->entityManager->find(CategoryEntity::class, $id->toString());

        if ($entity === null) {
            return null;
        }

        return $entity->toDomainModel();
    }

    public function findBySlug(TenantId $tenantId, Slug $slug): ?Category
    {
        $repository = $this->entityManager->getRepository(CategoryEntity::class);

        $entity = $repository->findOneBy([
            'tenantId' => $tenantId->toString(),
            'slug' => $slug->value(),
        ]);

        if ($entity === null) {
            return null;
        }

        return $entity->toDomainModel();
    }

    public function findByTenant(TenantId $tenantId): array
    {
        $repository = $this->entityManager->getRepository(CategoryEntity::class);

        $entities = $repository->findBy(
            ['tenantId' => $tenantId->toString()],
            ['position' => 'ASC']
        );

        return array_map(
            static fn (CategoryEntity $entity): Category => $entity->toDomainModel(),
            $entities
        );
    }

    public function findByParent(TenantId $tenantId, ?CategoryId $parentId): array
    {
        $repository = $this->entityManager->getRepository(CategoryEntity::class);

        $entities = $repository->findBy(
            [
                'tenantId' => $tenantId->toString(),
                'parentId' => $parentId?->toString(),
            ],
            ['position' => 'ASC']
        );

        return array_map(
            static fn (CategoryEntity $entity): Category => $entity->toDomainModel(),
            $entities
        );
    }

    public function delete(CategoryId $id): void
    {
        $entity = $this->entityManager->find(CategoryEntity::class, $id->toString());

        if ($entity === null) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
