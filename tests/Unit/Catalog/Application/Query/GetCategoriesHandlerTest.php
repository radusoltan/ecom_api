<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\GetCategories;
use App\Catalog\Application\Query\GetCategoriesHandler;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetCategoriesHandlerTest extends TestCase
{
    public function testItReturnsCategoriesForTenant(): void
    {
        $tenantId = TenantId::generate();
        $category = Category::create(CategoryId::generate(), $tenantId, CategoryName::fromString('Electronics'), null, null);

        $repo = $this->createStub(CategoryRepositoryInterface::class);
        $repo->method('findByTenant')->willReturn([$category]);

        $result = (new GetCategoriesHandler($repo))(new GetCategories($tenantId));

        self::assertCount(1, $result);
        self::assertSame('Electronics', $result[0]->name()->value());
    }

    public function testItReturnsEmptyWhenNoCategories(): void
    {
        $repo = $this->createStub(CategoryRepositoryInterface::class);
        $repo->method('findByTenant')->willReturn([]);

        $result = (new GetCategoriesHandler($repo))(new GetCategories(TenantId::generate()));

        self::assertSame([], $result);
    }
}
