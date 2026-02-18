<?php

declare(strict_types=1);

namespace App\Media\Infrastructure\Persistence\Doctrine\Repository;

use App\Media\Application\Message\DeleteImageAssetsMessage;
use App\Media\Domain\Model\Image;
use App\Media\Domain\Repository\ImageRepositoryInterface;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\OwnerReference;
use App\Media\Infrastructure\Persistence\Doctrine\Entity\ImageEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class DoctrineImageRepository implements ImageRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function save(Image $image): void
    {
        $entity = $this->entityManager->find(ImageEntity::class, $image->id()->toString());

        if (null === $entity) {
            $entity = ImageEntity::fromDomain($image);
            $this->entityManager->persist($entity);
        } else {
            $entity->updateFromDomain($image);
        }

        $this->entityManager->flush();
    }

    public function findById(ImageId $id): ?Image
    {
        $entity = $this->entityManager->find(ImageEntity::class, $id->toString());

        if (null === $entity) {
            return null;
        }

        return $entity->toDomain();
    }

    public function findByOwner(OwnerReference $owner): array
    {
        $repository = $this->entityManager->getRepository(ImageEntity::class);

        $entities = $repository->findBy([
            'tenantId' => $owner->tenantId()->toString(),
            'ownerType' => $owner->type()->value,
            'ownerId' => $owner->ownerId(),
        ], [
            'uploadedAt' => 'DESC',
        ]);

        return array_map(
            static fn (ImageEntity $entity): Image => $entity->toDomain(),
            $entities
        );
    }

    public function findByTenant(TenantId $tenantId, int $limit = 50, int $offset = 0): array
    {
        $repository = $this->entityManager->getRepository(ImageEntity::class);

        $entities = $repository->findBy(
            ['tenantId' => $tenantId->toString()],
            ['uploadedAt' => 'DESC'],
            $limit,
            $offset
        );

        return array_map(
            static fn (ImageEntity $entity): Image => $entity->toDomain(),
            $entities
        );
    }

    public function remove(Image $image): void
    {
        $entity = $this->entityManager->find(ImageEntity::class, $image->id()->toString());

        if (null === $entity) {
            return;
        }

        $this->messageBus->dispatch(new DeleteImageAssetsMessage(
            $image->originalPath()->toString(),
            array_map(static fn ($thumbnail) => $thumbnail->path()->toString(), $image->thumbnails())
        ));

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
