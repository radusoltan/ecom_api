<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Api\Resource;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Nested resource representing a single value for a product option.
 * Embedded within StorefrontProductOptionResource.
 */
final class StorefrontProductOptionValueResource
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
}
