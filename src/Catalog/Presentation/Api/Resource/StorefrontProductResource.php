<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Presentation\Api\State\FeaturedProductsProvider;
use App\Catalog\Presentation\Api\State\ProductListingProvider;

#[ApiResource(
    shortName: 'StorefrontProduct',
    operations: [
        new GetCollection(
            uriTemplate: '/storefront/featured-products',
            provider: FeaturedProductsProvider::class,
            normalizationContext: ['groups' => ['storefront:read']],
            description: 'Get featured products for storefront homepage'
        ),
        new GetCollection(
            uriTemplate: '/storefront/products',
            provider: ProductListingProvider::class,
            normalizationContext: ['groups' => ['storefront:read']],
            description: 'Get product listing with filters, facets, and pagination'
        ),
    ],
    normalizationContext: ['groups' => ['storefront:read']]
)]
class StorefrontProductResource
{
    public string $id;
    public string $slug;
    public string $name;
    /** @var array<string, mixed> */
    public array $price;
    public ?array $primaryImage = null;
    public bool $isFeatured = false;
    public ?float $rating = null;
    public ?string $availability = null;
    public ?array $breadcrumbs = null;
    public ?string $description = null;
}
