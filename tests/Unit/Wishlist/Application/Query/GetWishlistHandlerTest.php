<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Application\Query\GetWishlist;
use App\Wishlist\Application\Query\GetWishlistHandler;
use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\Repository\WishlistRepositoryInterface;
use App\Wishlist\Domain\ValueObject\WishlistId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetWishlistHandler::class)]
final class GetWishlistHandlerTest extends TestCase
{
    private WishlistRepositoryInterface $repository;
    private GetWishlistHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WishlistRepositoryInterface::class);
        $this->handler = new GetWishlistHandler($this->repository);
    }

    #[Test]
    public function handleReturnsWishlistWhenFound(): void
    {
        $tenantId = TenantId::generate();
        $wishlist = Wishlist::create(WishlistId::generate(), 'cust-001', $tenantId);
        $query = new GetWishlist('cust-001', $tenantId);

        $this->repository->method('findByCustomerId')->willReturn($wishlist);

        $result = ($this->handler)($query);

        self::assertSame($wishlist, $result);
    }

    #[Test]
    public function handleReturnsNullWhenNotFound(): void
    {
        $query = new GetWishlist('cust-001', TenantId::generate());

        $this->repository->method('findByCustomerId')->willReturn(null);

        $result = ($this->handler)($query);

        self::assertNull($result);
    }
}
