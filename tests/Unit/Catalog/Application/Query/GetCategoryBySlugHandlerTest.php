<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query;

use App\Catalog\Application\Query\GetCategoryBySlug;
use App\Catalog\Application\Query\GetCategoryBySlugHandler;
use App\Catalog\Domain\Exception\CategoryNotFoundException;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetCategoryBySlugHandlerTest extends TestCase
{
    public function testItReturnsCategoryBySlug(): void
    {
        $tenantId = TenantId::generate();
        $category = Category::create(CategoryId::generate(), $tenantId, CategoryName::fromString('Electronics'), null, null);

        $repo = $this->createStub(CategoryRepositoryInterface::class);
        $repo->method('findBySlug')->willReturn($category);

        $result = (new GetCategoryBySlugHandler($repo))(new GetCategoryBySlug($tenantId, Slug::fromString('electronics')));

        self::assertSame('Electronics', $result->name()->value());
    }

    public function testItThrowsWhenNotFound(): void
    {
        $repo = $this->createStub(CategoryRepositoryInterface::class);
        $repo->method('findBySlug')->willReturn(null);

        $this->expectException(CategoryNotFoundException::class);

        (new GetCategoryBySlugHandler($repo))(new GetCategoryBySlug(TenantId::generate(), Slug::fromString('nonexistent')));
    }
}
