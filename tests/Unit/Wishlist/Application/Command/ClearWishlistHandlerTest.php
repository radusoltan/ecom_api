<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Application\Command\ClearWishlist;
use App\Wishlist\Application\Command\ClearWishlistHandler;
use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\Repository\WishlistRepositoryInterface;
use App\Wishlist\Domain\ValueObject\WishlistId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClearWishlistHandler::class)]
final class ClearWishlistHandlerTest extends TestCase
{
    private WishlistRepositoryInterface $repository;
    private ClearWishlistHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(WishlistRepositoryInterface::class);
        $this->handler = new ClearWishlistHandler($this->repository);
    }

    #[Test]
    public function handleClearsAndSavesWishlist(): void
    {
        $tenantId = TenantId::generate();
        $wishlist = Wishlist::create(WishlistId::generate(), 'cust-001', $tenantId);
        $wishlist->addItem(ProductId::generate());
        $command = new ClearWishlist('cust-001', $tenantId);

        $this->repository->method('findByCustomerId')->willReturn($wishlist);
        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(
                fn (Wishlist $w) => $w->isEmpty(),
            ));

        ($this->handler)($command);
    }

    #[Test]
    public function handleDoesNothingWhenWishlistNotFound(): void
    {
        $command = new ClearWishlist('cust-001', TenantId::generate());

        $this->repository->method('findByCustomerId')->willReturn(null);
        $this->repository->expects(self::never())->method('save');

        ($this->handler)($command);
    }
}
