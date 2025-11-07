<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class DoctrineProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $eventBus
    ) {
    }

    public function save(Product $product): void
    {
        // Check if entity already exists
        $existingEntity = $this->entityManager->find(ProductEntity::class, $product->id()->toString());

        if (null !== $existingEntity) {
            // Update existing entity - use its setters instead of creating new instance
            $existingEntity->setTenantId($product->tenantId()->toString());
            $existingEntity->setSku($product->sku()->value());
            $existingEntity->setName($product->name()->value());
            $existingEntity->setDescription($product->description());
            $existingEntity->setShortDescription($product->shortDescription());
            $existingEntity->setPriceAmount($product->price()->getAmount());
            $existingEntity->setPriceCurrency($product->price()->getCurrency()->getCurrencyCode());
            $existingEntity->setCategoryId($product->categoryId()?->toString());
            $existingEntity->setStockQuantity($product->stock()->quantity());
            $existingEntity->setTrackInventory($product->stock()->trackInventory());
            $existingEntity->setAllowBackorder($product->stock()->allowBackorder());
            $existingEntity->setImages(array_map(fn ($img) => $img->toArray(), $product->images()));
            $existingEntity->setActive($product->isActive());
            $existingEntity->setIsFeatured($product->isFeatured());
        // Doctrine will auto-detect changes and update on flush
        } else {
            // Create new entity
            $entity = ProductEntity::fromDomainModel($product);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();

        // Dispatch domain events
        foreach ($product->popEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    public function findById(ProductId $id): ?Product
    {
        $entity = $this->entityManager->find(ProductEntity::class, $id->toString());

        if (null === $entity) {
            return null;
        }

        return $entity->toDomainModel();
    }

    public function findBySKU(TenantId $tenantId, SKU $sku): ?Product
    {
        $repository = $this->entityManager->getRepository(ProductEntity::class);

        $entity = $repository->findOneBy([
            'tenantId' => $tenantId->toString(),
            'sku' => $sku->value(),
        ]);

        if (null === $entity) {
            return null;
        }

        return $entity->toDomainModel();
    }

    public function findBySlug(TenantId $tenantId, Slug $slug): ?Product
    {
        $repository = $this->entityManager->getRepository(ProductEntity::class);

        $entity = $repository->findOneBy([
            'tenantId' => $tenantId->toString(),
            'slug' => $slug->value(),
        ]);

        if (null === $entity) {
            return null;
        }

        return $entity->toDomainModel();
    }

    public function findByTenant(TenantId $tenantId, int $limit = 100, int $offset = 0): array
    {
        $repository = $this->entityManager->getRepository(ProductEntity::class);

        $entities = $repository->findBy(
            ['tenantId' => $tenantId->toString()],
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );

        return array_map(
            static fn (ProductEntity $entity): Product => $entity->toDomainModel(),
            $entities
        );
    }

    public function delete(ProductId $id): void
    {
        $entity = $this->entityManager->find(ProductEntity::class, $id->toString());

        if (null === $entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
