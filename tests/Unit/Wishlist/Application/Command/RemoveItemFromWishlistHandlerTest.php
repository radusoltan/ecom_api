<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Application\Command\RemoveItemFromWishlist;
use App\Wishlist\Application\Command\RemoveItemFromWishlistHandler;
use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\Repository\WishlistRepositoryInterface;
use App\Wishlist\Domain\ValueObject\WishlistId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoveItemFromWishlistHandler::class)]
final class RemoveItemFromWishlistHandlerTest extends TestCase
{
    private WishlistRepositoryInterface $repository;
    private RemoveItemFromWishlistHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WishlistRepositoryInterface::class);
        $this->handler = new RemoveItemFromWishlistHandler($this->repository);
    }

    #[Test]
    public function handleRemovesItemAndSaves(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $wishlist = Wishlist::create(WishlistId::generate(), 'cust-001', $tenantId);
        $wishlist->addItem($productId);
        $command = new RemoveItemFromWishlist('cust-001', $productId, $tenantId);

        $this->repository->method('findByCustomerId')->willReturn($wishlist);
        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(
                fn (Wishlist $w) => !$w->hasItem($productId),
            ));

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsWhenWishlistNotFound(): void
    {
        $command = new RemoveItemFromWishlist('cust-001', ProductId::generate(), TenantId::generate());

        $this->repository->method('findByCustomerId')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Wishlist not found');

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsWhenProductNotInWishlist(): void
    {
        $tenantId = TenantId::generate();
        $wishlist = Wishlist::create(WishlistId::generate(), 'cust-001', $tenantId);
        $command = new RemoveItemFromWishlist('cust-001', ProductId::generate(), $tenantId);

        $this->repository->method('findByCustomerId')->willReturn($wishlist);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product is not in the wishlist');

        ($this->handler)($command);
    }
}
