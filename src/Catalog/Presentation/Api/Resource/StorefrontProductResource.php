<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Application\DTO\MoneyDto;
use App\Catalog\Application\DTO\ProductImageDto;
use App\Catalog\Presentation\Api\State\FeaturedProductsProvider;
use App\Catalog\Presentation\Api\State\ProductListingProvider;
use App\Catalog\Presentation\Api\State\StorefrontProductProvider;

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
        new Get(
            uriTemplate: '/storefront/products/{slug}',
            uriVariables: ['slug'],
            provider: StorefrontProductProvider::class,
            normalizationContext: ['groups' => ['storefront:read']],
            description: 'Get a single storefront product by slug (active products only, current tenant)'
        ),
    ],
    normalizationContext: ['groups' => ['storefront:read']]
)]
class StorefrontProductResource
{
    public string $id;
    public string $slug;
    public string $name;
    public MoneyDto $price;
    public ?ProductImageDto $primaryImage = null;
    public bool $isFeatured = false;
    public ?float $rating = null;
    public ?string $availability = null;
    /** @var list<array{name: string, slug: string}>|null */
    public ?array $breadcrumbs = null;
    public ?string $description = null;
    public ?string $categoryId = null;
}
