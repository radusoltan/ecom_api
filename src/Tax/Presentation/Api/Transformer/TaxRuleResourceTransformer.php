<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Api\Transformer;

use App\Tax\Application\DTO\TaxRuleDTO;
use App\Tax\Presentation\Api\Resource\TaxRuleResource;

/**
 * Transforms TaxRuleDTO to TaxRuleResource.
 */
final readonly class TaxRuleResourceTransformer
{
    public function fromDTO(TaxRuleDTO $dto): TaxRuleResource
    {
        $resource = new TaxRuleResource();
        $resource->id = $dto->id;
        $resource->tenantId = $dto->tenantId;
        $resource->name = $dto->name;
        $resource->countryCode = $dto->countryCode;
        $resource->regionCode = $dto->regionCode;
        $resource->ratePercentage = (float) $dto->ratePercentage; // Explicit cast to ensure float serialization
        $resource->isActive = $dto->isActive;
        $resource->createdAt = new \DateTimeImmutable($dto->createdAt);
        $resource->updatedAt = new \DateTimeImmutable($dto->updatedAt);

        return $resource;
    }

    /**
     * @param TaxRuleDTO[] $dtos
     *
     * @return TaxRuleResource[]
     */
    public function fromDTOs(array $dtos): array
    {
        return array_map(fn (TaxRuleDTO $dto) => $this->fromDTO($dto), $dtos);
    }
}
