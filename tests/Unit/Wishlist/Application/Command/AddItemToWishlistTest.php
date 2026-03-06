<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Application\Command\AddItemToWishlist;
use PHPUnit\Framework\TestCase;

final class AddItemToWishlistTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    public function testItCreatesCommandWithAllFields(): void
    {
        // Arrange
        $customerId = 'customer-uuid-001';
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        // Act
        $command = new AddItemToWishlist(
            customerId: $customerId,
            productId: $productId,
            tenantId: $tenantId,
        );

        // Assert
        self::assertSame($customerId, $command->customerId);
        self::assertSame($productId, $command->productId);
        self::assertSame($tenantId, $command->tenantId);
    }

    public function testItStoresProductIdReference(): void
    {
        // Arrange
        $productId = ProductId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        // Act
        $command = new AddItemToWishlist(
            customerId: 'customer-001',
            productId: $productId,
            tenantId: $tenantId,
        );

        // Assert
        self::assertTrue($productId->equals($command->productId));
    }

    public function testItStoresTenantIdReference(): void
    {
        // Arrange
        $tenantId = TenantId::fromString(self::TENANT_ID);

        // Act
        $command = new AddItemToWishlist(
            customerId: 'customer-001',
            productId: ProductId::generate(),
            tenantId: $tenantId,
        );

        // Assert
        self::assertTrue($tenantId->equals($command->tenantId));
    }
}
