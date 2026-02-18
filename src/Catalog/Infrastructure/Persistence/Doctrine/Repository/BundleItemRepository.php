<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\Bundle;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\BundleItemEntity;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * BundleItemRepository.
 *
 * Manages persistence of bundle items separately from products.
 *
 * @extends ServiceEntityRepository<BundleItemEntity>
 */
final class BundleItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BundleItemEntity::class);
    }

    /**
     * Save bundle items for a product (replaces existing).
     */
    public function saveBundleItems(ProductId $bundleProductId, Bundle $bundle): void
    {
        $em = $this->getEntityManager();

        // Delete existing bundle items
        $this->deleteBundleItems($bundleProductId);

        // Create new bundle items
        foreach ($bundle->items() as $item) {
            $entity = new BundleItemEntity();
            $entity->setId((string) new Ulid());
            $entity->setBundleProductId($bundleProductId->toString());
            $entity->setProductId($item->productId()->toString());
            $entity->setQuantity($item->quantity());
            $entity->setPriceAmount($item->price()->getAmount());
            $entity->setPriceCurrency($item->price()->getCurrency()->getCurrencyCode());

            $em->persist($entity);
        }

        $em->flush();
    }

    /**
     * Delete all bundle items for a product.
     */
    public function deleteBundleItems(ProductId $bundleProductId): void
    {
        $this->createQueryBuilder('bi')
            ->delete()
            ->where('bi.bundleProductId = :bundleProductId')
            ->setParameter('bundleProductId', $bundleProductId->toString())
            ->getQuery()
            ->execute();
    }

    /**
     * Load bundle for a product.
     */
    public function findBundleByProductId(ProductId $bundleProductId, float $discountPercentage): ?Bundle
    {
        $entities = $this->createQueryBuilder('bi')
            ->where('bi.bundleProductId = :bundleProductId')
            ->setParameter('bundleProductId', $bundleProductId->toString())
            ->orderBy('bi.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($entities)) {
            return null;
        }

        // Convert entities to BundleItem VOs
        $items = [];
        foreach ($entities as $entity) {
            $items[] = BundleItem::fromPersistence(
                ProductId::fromString($entity->getProductId()),
                $entity->getQuantity(),
                Money::fromScalars(
                    $entity->getPriceAmount(),
                    $entity->getPriceCurrency()
                )
            );
        }

        return Bundle::create($items, $discountPercentage);
    }
}
