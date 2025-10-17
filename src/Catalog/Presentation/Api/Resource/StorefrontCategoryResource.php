<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Presentation\Api\State\FrontCategoriesProvider;

#[ApiResource(
    shortName: 'StorefrontCategory',
    operations: [
        new GetCollection(
            uriTemplate: '/storefront/home-categories',
            provider: FrontCategoriesProvider::class,
            normalizationContext: ['groups' => ['storefront:read']],
            description: 'Get categories marked as showOnFront for storefront homepage'
        )
    ],
    normalizationContext: ['groups' => ['storefront:read']]
)]
class StorefrontCategoryResource
{
    public string $id;
    public string $slug;
    public string $name;
    public ?array $image = null;
    public bool $showOnFront = false;
    public int $childrenCount = 0;
    public ?string $description = null;
}