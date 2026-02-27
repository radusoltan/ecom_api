<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Pricing\Presentation\Api\Processor\ActivatePriceListProcessor;
use App\Pricing\Presentation\Api\Processor\CreatePriceListProcessor;
use App\Pricing\Presentation\Api\Processor\DeactivatePriceListProcessor;
use App\Pricing\Presentation\Api\Provider\PriceListCollectionProvider;
use App\Pricing\Presentation\Api\Provider\PriceListItemProvider;

#[ApiResource(
    shortName: 'PriceList',
    security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_MANAGER')",
    operations: [
        new GetCollection(
            uriTemplate: '/price-lists',
            provider: PriceListCollectionProvider::class
        ),
        new Get(
            uriTemplate: '/price-lists/{id}',
            provider: PriceListItemProvider::class
        ),
        new Post(
            uriTemplate: '/price-lists',
            processor: CreatePriceListProcessor::class
        ),
        new Patch(
            uriTemplate: '/price-lists/{id}/activate',
            provider: PriceListItemProvider::class,
            processor: ActivatePriceListProcessor::class
        ),
        new Patch(
            uriTemplate: '/price-lists/{id}/deactivate',
            provider: PriceListItemProvider::class,
            processor: DeactivatePriceListProcessor::class
        ),
    ]
)]
class PriceListResource
{
    public ?string $id = null;
    public ?string $tenantId = null;
    public ?string $name = null;
    public ?int $priority = null;
    /** @var array<string, mixed> */
    public array $rules = [];
    public ?string $validFrom = null;
    public ?string $validTo = null;
    public ?bool $isActive = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
