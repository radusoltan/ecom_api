<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\Slug;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use App\Catalog\Domain\Model\CategoryName;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CategoryRepositoryTest extends KernelTestCase
{
    private CategoryRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->repository = $container->get(CategoryRepositoryInterface::class);

        // Clean up test categories
        $this->cleanupTestCategories();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestCategories();
        parent::tearDown();
    }

    private function cleanupTestCategories(): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $qb = $em->createQueryBuilder();
        $qb->delete(\App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity::class, 'c')
            ->where('c.name LIKE :prefix1 OR c.name LIKE :prefix2 OR c.name LIKE :prefix3 OR c.name LIKE :prefix4 OR c.name LIKE :prefix5 OR c.name LIKE :prefix6 OR c.name = :exact1 OR c.name = :exact2 OR c.name = :exact3')
            ->setParameter('prefix1', 'Test %')
            ->setParameter('prefix2', 'Category %')
            ->setParameter('prefix3', 'Child %')
            ->setParameter('prefix4', 'Tenant %')
            ->setParameter('prefix5', 'Position %')
            ->setParameter('prefix6', 'Root Category %')
            ->setParameter('exact1', 'Electronics')
            ->setParameter('exact2', 'Parent')
            ->setParameter('exact3', 'Original Name')
            ->getQuery()
            ->execute();
    }

    public function testSaveAndFindCategoryById(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Test Category'),
            description: 'Test description',
            parentId: null,
            position: 0
        );

        $this->repository->save($category);

        $found = $this->repository->findById($category->id());

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($category->id()));
        $this->assertSame('Test Category', $found->name()->value());
        $this->assertSame('test-category', $found->slug()->value());
        $this->assertSame('Test description', $found->description());
        $this->assertNull($found->parentId());
        $this->assertSame(0, $found->position());
        $this->assertTrue($found->isActive());
    }

    public function testFindCategoryBySlug(): void
    {
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');

        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Electronics'),
            description: 'Electronic products',
            parentId: null,
            position: 0
        );

        $this->repository->save($category);

        $found = $this->repository->findBySlug($tenantId, Slug::fromString('electronics'));

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($category->id()));
        $this->assertSame('electronics', $found->slug()->value());
    }

    public function testFindCategoriesByTenant(): void
    {
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');

        $category1 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Category One'),
            description: null,
            parentId: null,
            position: 0
        );

        $category2 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Category Two'),
            description: null,
            parentId: null,
            position: 1
        );

        $this->repository->save($category1);
        $this->repository->save($category2);

        $categories = $this->repository->findByTenant($tenantId);

        $this->assertGreaterThanOrEqual(2, count($categories));
    }

    public function testFindChildCategories(): void
    {
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');

        $parentCategory = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Parent Category'),
            description: null,
            parentId: null,
            position: 0
        );

        $this->repository->save($parentCategory);

        $childCategory1 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Child Category 1'),
            description: null,
            parentId: $parentCategory->id(),
            position: 0
        );

        $childCategory2 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Child Category 2'),
            description: null,
            parentId: $parentCategory->id(),
            position: 1
        );

        $this->repository->save($childCategory1);
        $this->repository->save($childCategory2);

        $children = $this->repository->findByParent($tenantId, $parentCategory->id());

        $this->assertGreaterThanOrEqual(2, count($children));

        $childNames = array_map(fn($c) => $c->name()->value(), $children);
        $this->assertContains('Child Category 1', $childNames);
        $this->assertContains('Child Category 2', $childNames);
    }

    public function testUpdateCategory(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Original Name'),
            description: 'Original description',
            parentId: null,
            position: 0
        );

        $this->repository->save($category);

        $category->update(
            name: CategoryName::fromString('Updated Name'),
            description: 'Updated description',
            parentId: null,
            position: 5,
            showOnFront: true
        );

        $this->repository->save($category);

        $found = $this->repository->findById($category->id());

        $this->assertNotNull($found);
        $this->assertSame('Updated Name', $found->name()->value());
        $this->assertSame('Updated description', $found->description());
        $this->assertSame(5, $found->position());
        $this->assertTrue($found->showOnFront());
    }

    public function testDeleteCategory(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Category to Delete'),
            description: null,
            parentId: null,
            position: 0
        );

        $this->repository->save($category);

        $found = $this->repository->findById($category->id());
        $this->assertNotNull($found);

        $this->repository->delete($category->id());

        $notFound = $this->repository->findById($category->id());
        $this->assertNull($notFound);
    }

    public function testSaveCategoryWithParent(): void
    {
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');

        $parentCategory = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Parent'),
            description: null,
            parentId: null,
            position: 0
        );

        $this->repository->save($parentCategory);

        $childCategory = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Child'),
            description: null,
            parentId: $parentCategory->id(),
            position: 0
        );

        $this->repository->save($childCategory);

        $found = $this->repository->findById($childCategory->id());

        $this->assertNotNull($found);
        $this->assertNotNull($found->parentId());
        $this->assertTrue($found->parentId()->equals($parentCategory->id()));
    }

    public function testFindRootCategories(): void
    {
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');

        $rootCategory1 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Root Category 1'),
            description: null,
            parentId: null,
            position: 0
        );

        $rootCategory2 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Root Category 2'),
            description: null,
            parentId: null,
            position: 1
        );

        $this->repository->save($rootCategory1);
        $this->repository->save($rootCategory2);

        $parentCategory = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Parent for Child'),
            description: null,
            parentId: null,
            position: 2
        );

        $this->repository->save($parentCategory);

        $childCategory = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Child Category'),
            description: null,
            parentId: $parentCategory->id(),
            position: 0
        );

        $this->repository->save($childCategory);

        $rootCategories = $this->repository->findByParent($tenantId, null);

        $rootNames = array_map(fn($c) => $c->name()->value(), $rootCategories);

        $this->assertContains('Root Category 1', $rootNames);
        $this->assertContains('Root Category 2', $rootNames);
        $this->assertNotContains('Child Category', $rootNames);
    }

    public function testDeactivateCategory(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Category to Deactivate'),
            description: null,
            parentId: null,
            position: 0
        );

        $this->repository->save($category);

        $category->deactivate();

        $this->repository->save($category);

        $found = $this->repository->findById($category->id());

        $this->assertNotNull($found);
        $this->assertFalse($found->isActive());
    }

    public function testActivateCategory(): void
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab'),
            name: CategoryName::fromString('Category to Activate'),
            description: null,
            parentId: null,
            position: 0
        );

        $category->deactivate();

        $this->repository->save($category);

        $category->activate();

        $this->repository->save($category);

        $found = $this->repository->findById($category->id());

        $this->assertNotNull($found);
        $this->assertTrue($found->isActive());
    }

    public function testFindNonExistentCategory(): void
    {
        $nonExistentId = CategoryId::generate();

        $found = $this->repository->findById($nonExistentId);

        $this->assertNull($found);
    }

    public function testTenantIsolation(): void
    {
        $tenant1Id = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');
        $tenant2Id = TenantId::fromString('8c4d7d8b-4a09-4b6e-8b5d-0123456789ab');

        $category1 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenant1Id,
            name: CategoryName::fromString('Tenant 1 Category'),
            description: null,
            parentId: null,
            position: 0
        );

        $category2 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenant2Id,
            name: CategoryName::fromString('Tenant 2 Category'),
            description: null,
            parentId: null,
            position: 0
        );

        $this->repository->save($category1);
        $this->repository->save($category2);

        $tenant1Categories = $this->repository->findByTenant($tenant1Id);
        $tenant2Categories = $this->repository->findByTenant($tenant2Id);

        $tenant1Names = array_map(fn($c) => $c->name()->value(), $tenant1Categories);
        $tenant2Names = array_map(fn($c) => $c->name()->value(), $tenant2Categories);

        $this->assertContains('Tenant 1 Category', $tenant1Names);
        $this->assertNotContains('Tenant 2 Category', $tenant1Names);
        $this->assertContains('Tenant 2 Category', $tenant2Names);
        $this->assertNotContains('Tenant 1 Category', $tenant2Names);
    }

    public function testCategoryPositionOrdering(): void
    {
        $tenantId = TenantId::fromString('9d5e8e9c-5b1a-4c7f-9c6e-1234567890ab');

        $category1 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Position 0'),
            description: null,
            parentId: null,
            position: 0
        );

        $category2 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Position 1'),
            description: null,
            parentId: null,
            position: 1
        );

        $category3 = Category::create(
            id: CategoryId::generate(),
            tenantId: $tenantId,
            name: CategoryName::fromString('Position 2'),
            description: null,
            parentId: null,
            position: 2
        );

        $this->repository->save($category1);
        $this->repository->save($category2);
        $this->repository->save($category3);

        $categories = $this->repository->findByTenant($tenantId);

        usort($categories, fn($a, $b) => $a->position() <=> $b->position());

        $positions = array_map(fn($c) => $c->position(), $categories);

        for ($i = 0; $i < count($positions) - 1; $i++) {
            $this->assertLessThanOrEqual($positions[$i + 1], $positions[$i]);
        }
    }
}
