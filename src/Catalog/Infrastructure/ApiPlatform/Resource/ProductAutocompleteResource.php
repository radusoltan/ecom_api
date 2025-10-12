<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Infrastructure\ApiPlatform\State\ProductAutocompleteStateProvider;

#[ApiResource(
    shortName: 'ProductAutocomplete',
    operations: [
        new GetCollection(
            uriTemplate: '/search/autocomplete',
        ),
    ],
    provider: ProductAutocompleteStateProvider::class,
    paginationEnabled: false,
)]
final class ProductAutocompleteResource
{
    #[ApiProperty(identifier: true, readable: false, writable: false)]
    public ?string $id = null;

    public ?string $text = null;
    public ?float $score = null;
    public ?array $context = null; // Additional context (product ID, SKU, etc.)
}
