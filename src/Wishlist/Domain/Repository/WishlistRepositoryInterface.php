<?php

declare(strict_types=1);

namespace App\Wishlist\Domain\Repository;

use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\ValueObject\WishlistId;

interface WishlistRepositoryInterface
{
    public function save(Wishlist $wishlist): void;

    public function findById(WishlistId $id): ?Wishlist;

    public function findByCustomerId(string $customerId, TenantId $tenantId): ?Wishlist;

    public function delete(Wishlist $wishlist): void;
}
