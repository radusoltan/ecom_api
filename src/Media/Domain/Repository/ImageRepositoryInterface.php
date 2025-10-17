<?php

declare(strict_types=1);

namespace App\Media\Domain\Repository;

use App\Media\Domain\Model\Image;
use App\Media\Domain\ValueObject\ImageId;
use App\Media\Domain\ValueObject\OwnerReference;
use App\Shared\Domain\ValueObject\TenantId;

interface ImageRepositoryInterface
{
    public function save(Image $image): void;

    public function findById(ImageId $id): ?Image;

    /**
     * @return Image[]
     */
    public function findByOwner(OwnerReference $owner): array;

    /**
     * @return Image[]
     */
    public function findByTenant(TenantId $tenantId, int $limit = 50, int $offset = 0): array;

    public function remove(Image $image): void;
}
