<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Presentation\Api\State\StorefrontProductOptionsProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Storefront API resource for product options (color, size, etc.) scoped to a single product.
 *
 * GET /api/v1/products/{productId}/options
 *
 * Returns [] for simple products (no entry in catalog_configurable_products).
 * Returns 404 for non-existent productId.
 * RLS enforced via app.tenant_id GUC set by TenantRequestSubscriber.
 */
#[ApiResource(
    shortName: 'StorefrontProductOption',
    operations: [
        new GetCollection(
            uriTemplate: '/products/{productId}/options',
            uriVariables: ['productId'],
            provider: StorefrontProductOptionsProvider::class,
            normalizationContext: ['groups' => ['storefront:read']],
            security: "is_granted('PUBLIC_ACCESS')",
            description: 'Get options for a single product (returns [] for simple products, 404 for non-existent product)',
            paginationEnabled: false,
        ),
    ],
    normalizationContext: ['groups' => ['storefront:read']]
)]
final class StorefrontProductOptionResource
{
    #[Groups(['storefront:read'])]
    public string $id;

    #[Groups(['storefront:read'])]
    public string $code;

    #[Groups(['storefront:read'])]
    public string $name;

    /** @var array<string, string> */
    #[Groups(['storefront:read'])]
    public array $nameTranslations = [];

    #[Groups(['storefront:read'])]
    public int $position = 0;

    /** @var list<StorefrontProductOptionValueResource> */
    #[Groups(['storefront:read'])]
    public array $values = [];
}
