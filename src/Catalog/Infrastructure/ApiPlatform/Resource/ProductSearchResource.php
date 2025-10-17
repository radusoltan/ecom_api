<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Infrastructure\ApiPlatform\State\ProductSearchStateProvider;

#[ApiResource(
    shortName: 'ProductSearch',
    operations: [
        new GetCollection(
            uriTemplate: '/search/products',
        ),
    ],
    provider: ProductSearchStateProvider::class,
    paginationEnabled: false, // We handle pagination manually
)]
final class ProductSearchResource
{
    #[ApiProperty(identifier: true, readable: false, writable: false)]
    public ?string $id = null;

    public ?string $productId = null;
    public ?string $tenantId = null;
    public ?string $sku = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $slug = null;
    public ?float $price = null;
    public ?int $priceMinor = null;
    public ?float $finalPrice = null;
    public ?int $finalPriceMinor = null;
    public ?int $totalDiscountMinor = null;
    public ?float $discountPercent = null;
    public ?string $currency = null;
    public ?string $status = null;
    public ?bool $inStock = null;
    public ?bool $trackInventory = null;
    public ?bool $allowBackorder = null;
    public ?array $categoryIds = null;
    public ?string $imageUrl = null;
    public ?string $locale = null;
    public ?float $score = null;

    // Inventory information
    public ?int $inventoryTotalAvailable = null;
    public ?bool $inventoryIsLow = null;
    public ?array $inventoryWarehouses = null;

    // Rating and review information
    public ?float $averageRating = null;
    public ?int $reviewCount = null;

    // Featured flag
    public ?bool $isFeatured = null;

    // Metadata properties (only in collection response)
    public ?int $total = null;
    public ?int $page = null;
    public ?int $limit = null;
    public ?int $totalPages = null;
    public ?bool $hasNextPage = null;
    public ?bool $hasPreviousPage = null;
    public ?array $facets = null;
}
