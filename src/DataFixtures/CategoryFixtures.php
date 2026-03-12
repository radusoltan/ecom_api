<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Application\Command\CreateCategory;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\CategoryName;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Category fixtures - creates hierarchical categories with translations
 * 3 root categories, each with 3 subcategories (12 total).
 */
class CategoryFixtures extends AbstractTenantAwareFixture implements DependentFixtureInterface, FixtureGroupInterface
{
    // Reference constants for ProductFixtures
    public const CATEGORY_ELECTRONICS = 'category_electronics';
    public const CATEGORY_ELECTRONICS_LAPTOPS = 'category_electronics_laptops';
    public const CATEGORY_ELECTRONICS_SMARTPHONES = 'category_electronics_smartphones';
    public const CATEGORY_ELECTRONICS_ACCESSORIES = 'category_electronics_accessories';

    public const CATEGORY_FASHION = 'category_fashion';
    public const CATEGORY_FASHION_MENS = 'category_fashion_mens';
    public const CATEGORY_FASHION_WOMENS = 'category_fashion_womens';
    public const CATEGORY_FASHION_KIDS = 'category_fashion_kids';

    public const CATEGORY_HOME = 'category_home';
    public const CATEGORY_HOME_FURNITURE = 'category_home_furniture';
    public const CATEGORY_HOME_DECOR = 'category_home_decor';
    public const CATEGORY_HOME_KITCHEN = 'category_home_kitchen';

    public function __construct(
        EntityManagerInterface $entityManager,
        TenantContext $tenantContext,
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($entityManager, $tenantContext);
    }

    public static function getGroups(): array
    {
        return ['sprint3', 'sprint3-catalog', 'sprint3-categories'];
    }

    public function load(ObjectManager $manager): void
    {
        echo "📂 Creating localized category trees...\n";

        $tenantIds = $this->tenantIdsByOwnerEmail();

        foreach (Sprint3SeedData::tenants() as $tenant) {
            $tenantId = TenantId::fromString($tenantIds[$tenant['ownerEmail']]);
            $this->activateTenantContext($tenantId);

            $createdForTenant = 0;

            foreach ($tenant['categories'] as $rootIndex => $root) {
                $rootId = CategoryId::fromString((string) Uuid::v7());
                $this->commandBus->dispatch(new CreateCategory(
                    id: $rootId,
                    tenantId: $tenantId,
                    name: CategoryName::fromString($root['name']['en']),
                    description: $root['description']['en'],
                    parentId: null,
                    position: $rootIndex + 1,
                    showOnFront: true,
                ));

                $this->applyCategoryPresentation($rootId, $root, $tenant['alias']);
                ++$createdForTenant;

                foreach ($root['children'] as $childIndex => $child) {
                    $childId = CategoryId::fromString((string) Uuid::v7());
                    $this->commandBus->dispatch(new CreateCategory(
                        id: $childId,
                        tenantId: $tenantId,
                        name: CategoryName::fromString($child['name']['en']),
                        description: $child['description']['en'],
                        parentId: $rootId,
                        position: $childIndex + 1,
                        showOnFront: true,
                    ));

                    $this->applyCategoryPresentation($childId, $child, $tenant['alias']);
                    ++$createdForTenant;
                }
            }

            echo sprintf("   ✓ %s categories: %d\n", $tenant['name'], $createdForTenant);
            $this->entityManager->clear();
        }

        $this->clearTenantContext();

        echo "✅ Category trees ready for all tenants\n";
    }

    /**
     * @param array{name: array<string, string>, description: array<string, string>} $category
     */
    private function applyCategoryPresentation(CategoryId $categoryId, array $category, string $tenantAlias): void
    {
        $entity = $this->entityManager->find(CategoryEntity::class, $categoryId->toString());

        if (!$entity instanceof CategoryEntity) {
            throw new \RuntimeException(sprintf('Category %s could not be reloaded after creation', $categoryId->toString()));
        }

        $entity->setNameTranslations($category['name']);
        $entity->setDescriptionTranslations($category['description']);
        $entity->setCoverImage(sprintf(
            'https://picsum.photos/seed/%s-%s/1200/420',
            $tenantAlias,
            strtolower(str_replace(' ', '-', $category['name']['en']))
        ));

        $this->entityManager->flush();
    }

    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
        ];
    }
}
