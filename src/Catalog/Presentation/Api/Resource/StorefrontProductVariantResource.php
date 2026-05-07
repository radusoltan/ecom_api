<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Presentation\Api\State\StorefrontProductVariantsProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Storefront API resource for product variants scoped to a single product.
 *
 * GET /api/v1/products/{productId}/variants
 * GET /api/v1/products/{productId}/variants?activeOnly=true  (default: true)
 * GET /api/v1/products/{productId}/variants?activeOnly=false
 *
 * Returns [] for simple products (no entry in catalog_configurable_products).
 * Returns 404 for non-existent productId.
 * RLS enforced via app.tenant_id GUC set by TenantRequestSubscriber.
 */
#[ApiResource(
    shortName: 'StorefrontProductVariant',
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/variants',
            uriVariables: ['productId'],
            provider: StorefrontProductVariantsProvider::class,
            normalizationContext: ['groups' => ['storefront:read']],
            security: "is_granted('PUBLIC_ACCESS')",
            description: 'Get variants for a single product (returns [] for simple products, 404 for non-existent product)',
            paginationEnabled: false,
        ),
    ],
    normalizationContext: ['groups' => ['storefront:read']]
)]
final class StorefrontProductVariantResource
{
    #[Groups(['storefront:read'])]
    public string $id;

    #[Groups(['storefront:read'])]
    public string $sku;

    /** @var array<string, string> */
    #[Groups(['storefront:read'])]
    public array $optionValueMap = [];

    #[Groups(['storefront:read'])]
    public int $priceAmount;

    #[Groups(['storefront:read'])]
    public string $priceCurrency;

    #[Groups(['storefront:read'])]
    public int $stockQuantity;

    #[Groups(['storefront:read'])]
    public bool $trackInventory;

    #[Groups(['storefront:read'])]
    public bool $allowBackorder;

    #[Groups(['storefront:read'])]
    public bool $isActive;

    #[Groups(['storefront:read'])]
    public bool $isAvailable;

    /** @var list<array{url: string, position: int, isPrimary: bool}> */
    #[Groups(['storefront:read'])]
    public array $images = [];
}
