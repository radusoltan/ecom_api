<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Application\Command\AddItemToWishlist;
use App\Wishlist\Application\Command\AddItemToWishlistHandler;
use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\Repository\WishlistRepositoryInterface;
use App\Wishlist\Domain\ValueObject\WishlistId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddItemToWishlistHandler::class)]
final class AddItemToWishlistHandlerTest extends TestCase
{
    private WishlistRepositoryInterface $repository;
    private AddItemToWishlistHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WishlistRepositoryInterface::class);
        $this->handler = new AddItemToWishlistHandler($this->repository);
    }

    #[Test]
    public function handleCreatesNewWishlistWhenNoneExists(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $command = new AddItemToWishlist('cust-001', $productId, $tenantId);

        $this->repository->method('findByCustomerId')->willReturn(null);
        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Wishlist $wishlist) use ($productId): bool {
                return $wishlist->hasItem($productId) && 1 === $wishlist->itemCount();
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleAddsToExistingWishlist(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $wishlist = Wishlist::create(WishlistId::generate(), 'cust-001', $tenantId);
        $command = new AddItemToWishlist('cust-001', $productId, $tenantId);

        $this->repository->method('findByCustomerId')->willReturn($wishlist);
        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(
                fn (Wishlist $w) => $w->hasItem($productId),
            ));

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsWhenProductAlreadyInWishlist(): void
    {
        $productId = ProductId::generate();
        $tenantId = TenantId::generate();
        $wishlist = Wishlist::create(WishlistId::generate(), 'cust-001', $tenantId);
        $wishlist->addItem($productId);
        $command = new AddItemToWishlist('cust-001', $productId, $tenantId);

        $this->repository->method('findByCustomerId')->willReturn($wishlist);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product is already in the wishlist');

        ($this->handler)($command);
    }
}
