<?php

declare(strict_types=1);

namespace App\Inventory\Presentation\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Inventory\Presentation\Api\Processor\ActivateWarehouseProcessor;
use App\Inventory\Presentation\Api\Processor\CreateWarehouseProcessor;
use App\Inventory\Presentation\Api\Processor\DeactivateWarehouseProcessor;
use App\Inventory\Presentation\Api\Processor\UpdateWarehouseProcessor;
use App\Inventory\Presentation\Api\Provider\WarehouseCollectionProvider;
use App\Inventory\Presentation\Api\Provider\WarehouseItemProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Warehouse',
    operations: [
        new GetCollection(
            uriTemplate: '/warehouses',
            normalizationContext: ['groups' => ['warehouse:read']],
            provider: WarehouseCollectionProvider::class
        ),
        new Get(
            uriTemplate: '/warehouses/{id}',
            normalizationContext: ['groups' => ['warehouse:read']],
            provider: WarehouseItemProvider::class
        ),
        new Post(
            uriTemplate: '/warehouses',
            normalizationContext: ['groups' => ['warehouse:read']],
            denormalizationContext: ['groups' => ['warehouse:create']],
            processor: CreateWarehouseProcessor::class
        ),
        new Put(
            uriTemplate: '/warehouses/{id}',
            normalizationContext: ['groups' => ['warehouse:read']],
            denormalizationContext: ['groups' => ['warehouse:update']],
            provider: WarehouseItemProvider::class,
            processor: UpdateWarehouseProcessor::class
        ),
        new Patch(
            uriTemplate: '/warehouses/{id}/activate',
            normalizationContext: ['groups' => ['warehouse:read']],
            denormalizationContext: ['groups' => ['warehouse:activate']],
            provider: WarehouseItemProvider::class,
            processor: ActivateWarehouseProcessor::class
        ),
        new Patch(
            uriTemplate: '/warehouses/{id}/deactivate',
            normalizationContext: ['groups' => ['warehouse:read']],
            denormalizationContext: ['groups' => ['warehouse:deactivate']],
            provider: WarehouseItemProvider::class,
            processor: DeactivateWarehouseProcessor::class
        ),
    ]
)]
final class WarehouseResource
{
    #[Groups(['warehouse:read'])]
    public ?string $id = null;

    #[Groups(['warehouse:read'])]
    public ?string $tenantId = null;

    #[Groups(['warehouse:read', 'warehouse:create'])]
    #[Assert\NotBlank(groups: ['warehouse:create'])]
    #[Assert\Regex(pattern: '/^[A-Z0-9]{3,10}$/', message: 'Warehouse code must be 3-10 uppercase alphanumeric characters', groups: ['warehouse:create'])]
    public ?string $code = null;

    #[Groups(['warehouse:read', 'warehouse:create', 'warehouse:update'])]
    #[Assert\NotBlank(groups: ['warehouse:create', 'warehouse:update'])]
    #[Assert\Length(min: 2, max: 100, groups: ['warehouse:create', 'warehouse:update'])]
    public ?string $name = null;

    #[Groups(['warehouse:read', 'warehouse:create', 'warehouse:update'])]
    #[Assert\NotBlank(groups: ['warehouse:create', 'warehouse:update'])]
    public ?array $address = null;

    #[Groups(['warehouse:read', 'warehouse:create', 'warehouse:update'])]
    #[Assert\PositiveOrZero(groups: ['warehouse:create', 'warehouse:update'])]
    public ?int $priority = 100;

    #[Groups(['warehouse:read'])]
    public ?bool $isActive = null;

    #[Groups(['warehouse:read'])]
    public ?string $createdAt = null;

    #[Groups(['warehouse:read'])]
    public ?string $updatedAt = null;
}
