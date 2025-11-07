<?php

declare(strict_types=1);

namespace App\Tenant\Presentation\Api\Transformer;

use App\Tenant\Application\DTO\TenantDTO;
use App\Tenant\Presentation\Api\TenantResource;

final class TenantResourceTransformer
{
    public static function fromDTO(TenantDTO $dto): TenantResource
    {
        $resource = new TenantResource();
        $resource->id = $dto->id;
        $resource->name = $dto->name;
        $resource->ownerEmail = $dto->ownerEmail;
        $resource->status = $dto->status;
        $resource->createdAt = $dto->createdAt;
        $resource->defaultLocale = $dto->defaultLocale;
        $resource->enabledLocales = $dto->enabledLocales;

        return $resource;
    }

    /**
     * @param TenantDTO[] $dtos
     *
     * @return TenantResource[]
     */
    public static function fromDTOs(array $dtos): array
    {
        return array_map(
            fn (TenantDTO $dto): TenantResource => self::fromDTO($dto),
            $dtos
        );
    }
}
