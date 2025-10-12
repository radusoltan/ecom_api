<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Slug;

final class CategoryNotFoundException extends \DomainException
{
    public static function withId(CategoryId $id): self
    {
        return new self(
            sprintf('Category with ID "%s" not found', $id->toString())
        );
    }

    public static function withSlug(Slug $slug): self
    {
        return new self(
            sprintf('Category with slug "%s" not found', $slug->value())
        );
    }
}
