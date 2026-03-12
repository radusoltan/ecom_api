<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Application\Command\CreateProduct;
use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\SKU;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\CategoryEntity;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductEntity;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Product fixtures - creates realistic localized products for all tenants.
 */
class ProductFixtures extends AbstractTenantAwareFixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /** @var array<string, int> */
    private array $skuCounters = [];

    public function __construct(
        EntityManagerInterface $entityManager,
        TenantContext $tenantContext,
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($entityManager, $tenantContext);
    }

    public static function getGroups(): array
    {
        return ['sprint3', 'sprint3-catalog', 'sprint3-products'];
    }

    public function load(ObjectManager $manager): void
    {
        echo "🛍️  Creating product catalog data...\n";

        $tenantIds = $this->tenantIdsByOwnerEmail();

        foreach (Sprint3SeedData::tenants() as $tenant) {
            $tenantId = TenantId::fromString($tenantIds[$tenant['ownerEmail']]);
            $this->activateTenantContext($tenantId);

            $productsCreated = 0;

            foreach ($tenant['categories'] as $root) {
                foreach ($root['children'] as $leaf) {
                    $category = $this->findCategoryEntity($tenantId, $leaf['name']['en']);

                    for ($index = 1; $index <= Sprint3SeedData::PRODUCTS_PER_LEAF; ++$index) {
                        $this->seedProduct($tenant, $leaf, $category, $tenantId, $index);
                        ++$productsCreated;
                    }
                }
            }

            echo sprintf("   ✓ %s products: %d\n", $tenant['name'], $productsCreated);
            $this->entityManager->clear();
        }

        $this->clearTenantContext();

        echo "✅ Product catalog ready for all tenants\n";
    }

    /**
     * @param array{
     *     alias: string,
     *     code: string,
     *     name: string,
     *     profile: string,
     *     brands: array<int, string>,
     *     series: array<int, string>
     * } $tenant
     * @param array{
     *     key: string,
     *     leafCode: string,
     *     label: array<string, string>,
     *     name: array<string, string>,
     *     description: array<string, string>
     * } $leaf
     */
    private function seedProduct(
        array $tenant,
        array $leaf,
        CategoryEntity $category,
        TenantId $tenantId,
        int $index,
    ): void {
        $productId = ProductId::fromString((string) \Symfony\Component\Uid\Uuid::v7());
        $sku = SKU::fromString(sprintf(
            '%s-%06d',
            $tenant['code'],
            $this->nextSkuCounter($tenant['alias'])
        ));

        $brand = $tenant['brands'][($index - 1) % count($tenant['brands'])];
        $series = $tenant['series'][($index + 1) % count($tenant['series'])];
        $model = sprintf('%s-%03d', strtoupper($leaf['leafCode']), $index);
        $copy = $this->buildLocalizedCopy($tenant, $leaf, $brand, $series, $model);
        $price = $this->pickPrice($tenant['profile'], $tenant['alias'], $leaf['key'], $index);
        $stock = 18 + (($index * 7) % 55);

        $this->commandBus->dispatch(new CreateProduct(
            id: $productId,
            tenantId: $tenantId,
            sku: $sku,
            name: ProductName::fromString($copy['name']['en']),
            description: $copy['description']['en'],
            shortDescription: $copy['shortDescription']['en'],
            price: Money::fromScalars($price, 'USD'),
            categoryId: CategoryId::fromString($category->getId()),
            stockQuantity: $stock,
            trackInventory: true,
            allowBackorder: false,
            isFeatured: $index <= 2,
        ));

        $entity = $this->entityManager->find(ProductEntity::class, $productId->toString());

        if (!$entity instanceof ProductEntity) {
            throw new \RuntimeException(sprintf('Product %s could not be reloaded after creation', $productId->toString()));
        }

        $entity->setActive(true);
        $entity->setImages($this->productImages($tenant['alias'], $leaf['key'], $index));
        $entity->setNameTranslations($copy['name']);
        $entity->setDescriptionTranslations($copy['description']);
        $entity->setShortDescriptionTranslations($copy['shortDescription']);

        $this->entityManager->flush();
    }

    private function nextSkuCounter(string $tenantAlias): int
    {
        $this->skuCounters[$tenantAlias] = ($this->skuCounters[$tenantAlias] ?? 0) + 1;

        return $this->skuCounters[$tenantAlias];
    }

    private function findCategoryEntity(TenantId $tenantId, string $englishName): CategoryEntity
    {
        $category = $this->entityManager->getRepository(CategoryEntity::class)->findOneBy([
            'tenantId' => $tenantId->toString(),
            'name' => $englishName,
        ]);

        if (!$category instanceof CategoryEntity) {
            throw new \RuntimeException(sprintf('Category "%s" was not found for tenant %s', $englishName, $tenantId->toString()));
        }

        return $category;
    }

    /**
     * @param array{alias: string, name: string, profile: string} $tenant
     * @param array{key: string, label: array<string, string>}    $leaf
     *
     * @return array{
     *     name: array<string, string>,
     *     shortDescription: array<string, string>,
     *     description: array<string, string>
     * }
     */
    private function buildLocalizedCopy(array $tenant, array $leaf, string $brand, string $series, string $model): array
    {
        $traits = Sprint3SeedData::localizedTraits();
        $profile = $tenant['profile'];

        $names = [];
        $shortDescriptions = [];
        $descriptions = [];

        foreach (Sprint3SeedData::LOCALES as $locale) {
            $localeTraits = $traits[$profile.'.'.$locale];
            $traitA = $localeTraits[abs(crc32($leaf['key'].'-'.$locale.'-a')) % count($localeTraits)];
            $traitB = $localeTraits[abs(crc32($leaf['key'].'-'.$locale.'-b')) % count($localeTraits)];
            $label = $leaf['label'][$locale];

            $names[$locale] = sprintf('%s %s %s %s', $brand, $series, $label, $model);
            $shortDescriptions[$locale] = match ($locale) {
                'fr' => sprintf('%s avec %s et %s.', $label, $traitA, $traitB),
                'de' => sprintf('%s mit %s und %s.', $label, $traitA, $traitB),
                default => sprintf('%s with %s and %s.', $label, $traitA, $traitB),
            };
            $descriptions[$locale] = match ($locale) {
                'fr' => sprintf('Concu pour %s, ce %s combine %s, %s et une finition fiable pour un usage quotidien.', $tenant['name'], strtolower($label), $traitA, $traitB),
                'de' => sprintf('Fuer %s entwickelt, kombiniert dieses %s %s, %s und eine zuverlaessige Verarbeitung fuer den Alltag.', $tenant['name'], strtolower($label), $traitA, $traitB),
                default => sprintf('Built for %s customers, this %s combines %s, %s, and dependable everyday performance.', $tenant['name'], strtolower($label), $traitA, $traitB),
            };
        }

        return [
            'name' => $names,
            'shortDescription' => $shortDescriptions,
            'description' => $descriptions,
        ];
    }

    private function pickPrice(string $profile, string $tenantAlias, string $leafKey, int $index): int
    {
        $range = Sprint3SeedData::priceRanges()[$profile];
        $spread = $range['max'] - $range['min'];
        $offset = abs(crc32($tenantAlias.'|'.$leafKey.'|'.$index)) % max(1, $spread);

        return $range['min'] + $offset;
    }

    /**
     * @return array<int, array{url: string, position: int, isPrimary: bool}>
     */
    private function productImages(string $tenantAlias, string $leafKey, int $index): array
    {
        return [
            [
                'url' => sprintf('https://picsum.photos/seed/%s-%s-%d-main/900/700', $tenantAlias, $leafKey, $index),
                'position' => 1,
                'isPrimary' => true,
            ],
            [
                'url' => sprintf('https://picsum.photos/seed/%s-%s-%d-alt/900/700', $tenantAlias, $leafKey, $index),
                'position' => 2,
                'isPrimary' => false,
            ],
        ];
    }

    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
            CategoryFixtures::class,
        ];
    }
}
