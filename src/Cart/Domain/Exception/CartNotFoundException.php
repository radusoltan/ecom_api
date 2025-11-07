<?php

declare(strict_types=1);

namespace App\Cart\Domain\Exception;

final class CartNotFoundException extends \RuntimeException
{
    public static function withId(string $cartId): self
    {
        return new self(sprintf('Cart with ID "%s" not found', $cartId));
    }
}
