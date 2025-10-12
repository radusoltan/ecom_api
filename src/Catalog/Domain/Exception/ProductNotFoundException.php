<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Domain\Model\Slug;

final class ProductNotFoundException extends \DomainException
{
    public static function withId(ProductId $id): self
    {
        return new self(
            sprintf('Product with ID "%s" not found', $id->toString())
        );
    }

    public static function withSKU(SKU $sku): self
    {
        return new self(
            sprintf('Product with SKU "%s" not found', $sku->value())
        );
    }

    public static function withSlug(Slug $slug): self
    {
        return new self(
            sprintf('Product with slug "%s" not found', $slug->value())
        );
    }
}
