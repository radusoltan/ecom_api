<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\TenantTestTrait;
use Doctrine\ORM\EntityManagerInterface;

final class CategoryApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private TenantId $tenantId;
    private CategoryRepositoryInterface $categoryRepository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = $this->getDefaultTenantId();
        $this->setTenantContext($this->tenantId->toString());

        $container = static::getContainer();
        $this->categoryRepository = $container->get(CategoryRepositoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $this->cleanupQa03Categories();
    }

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->cleanupQa03Categories();
        }

        parent::tearDown();
    }

    public function testCategorySlugFilterUsesExactMatchOnly(): void
    {
        $category = $this->createCategory('QA 03 Category Exact Match', 10);
        $this->createCategory('QA 03 Category Exact Match Extra', 11);

        $exactResponse = static::createClient()->request('GET', '/api/v1/categories', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'query' => [
                'slug' => 'qa-03-category-exact-match',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $exactItems = $this->collectionItems($exactResponse->toArray());

        $this->assertCount(1, $exactItems);
        $this->assertSame($category->id()->toString(), $exactItems[0]['id'] ?? null);
        $this->assertSame('qa-03-category-exact-match', $exactItems[0]['slug'] ?? null);

        $substringResponse = static::createClient()->request('GET', '/api/v1/categories', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => $this->tenantId->toString(),
            ],
            'query' => [
                'slug' => 'qa-03-category-exact',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $this->collectionItems($substringResponse->toArray()));
    }

    private function createCategory(string $name, int $position): Category
    {
        $category = Category::create(
            id: CategoryId::generate(),
            tenantId: $this->tenantId,
            name: CategoryName::fromString($name),
            description: null,
            parentId: null,
            position: $position
        );

        $this->categoryRepository->save($category);

        return $category;
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $data
     *
     * @return list<array<string, mixed>>
     */
    private function collectionItems(array $data): array
    {
        if (array_is_list($data)) {
            return $data;
        }

        foreach (['member', 'hydra:member', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
                return $data[$key];
            }
        }

        return [];
    }

    private function cleanupQa03Categories(): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(CategoryEntity::class, 'c')
            ->where('c.tenantId = :tenantId')
            ->andWhere('c.slug LIKE :slugPrefix')
            ->setParameter('tenantId', $this->tenantId->toString())
            ->setParameter('slugPrefix', 'qa-03-category-exact%')
            ->getQuery()
            ->execute();

        $this->entityManager->clear();
    }
}
