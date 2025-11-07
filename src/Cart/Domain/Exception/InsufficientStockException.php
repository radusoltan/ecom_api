<?php

declare(strict_types=1);

namespace App\Cart\Domain\Exception;

final class InsufficientStockException extends \RuntimeException
{
    public static function forProduct(string $productId, int $requested, int $available): self
    {
        return new self(
            sprintf(
                'Insufficient stock for product "%s". Requested: %d, Available: %d',
                $productId,
                $requested,
                $available
            )
        );
    }
}
