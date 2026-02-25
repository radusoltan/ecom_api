<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Application\Query;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Application\Query\GetWishlistItemCount;
use App\Wishlist\Application\Query\GetWishlistItemCountHandler;
use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\Repository\WishlistRepositoryInterface;
use App\Wishlist\Domain\ValueObject\WishlistId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetWishlistItemCountHandler::class)]
final class GetWishlistItemCountHandlerTest extends TestCase
{
    private WishlistRepositoryInterface $repository;
    private GetWishlistItemCountHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WishlistRepositoryInterface::class);
        $this->handler = new GetWishlistItemCountHandler($this->repository);
    }

    #[Test]
    public function handleReturnsCountFromWishlist(): void
    {
        $tenantId = TenantId::generate();
        $wishlist = Wishlist::create(WishlistId::generate(), 'cust-001', $tenantId);
        $wishlist->addItem(ProductId::generate());
        $wishlist->addItem(ProductId::generate());
        $query = new GetWishlistItemCount('cust-001', $tenantId);

        $this->repository->method('findByCustomerId')->willReturn($wishlist);

        $result = ($this->handler)($query);

        self::assertSame(2, $result);
    }

    #[Test]
    public function handleReturnsZeroWhenWishlistNotFound(): void
    {
        $query = new GetWishlistItemCount('cust-001', TenantId::generate());

        $this->repository->method('findByCustomerId')->willReturn(null);

        $result = ($this->handler)($query);

        self::assertSame(0, $result);
    }
}
