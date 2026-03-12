<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Application\Command\CreateVariant;
use App\Catalog\Application\Command\DefineOption;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\VariantId;
use App\Catalog\Domain\ValueObject\VariantSKU;
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
 * Product variations fixtures - creates options and variants for configurable products.
 */
class ProductVariationsFixtures extends AbstractTenantAwareFixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        TenantContext $tenantContext,
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct($entityManager, $tenantContext);
    }

    public static function getGroups(): array
    {
        return ['sprint3', 'sprint3-catalog', 'sprint3-variations'];
    }

    public function getDependencies(): array
    {
        return [
            TenantFixtures::class,
            ProductFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        echo "🎨 Creating configurable products with variants...\n";

        $tenantIds = $this->tenantIdsByOwnerEmail();

        foreach (Sprint3SeedData::tenants() as $tenant) {
            $tenantId = TenantId::fromString($tenantIds[$tenant['ownerEmail']]);
            $this->activateTenantContext($tenantId);

            $configurableProducts = 0;
            $variantsCreated = 0;

            foreach ($tenant['categories'] as $root) {
                foreach ($root['children'] as $leaf) {
                    $category = $this->entityManager->getRepository(CategoryEntity::class)->findOneBy([
                        'tenantId' => $tenantId->toString(),
                        'name' => $leaf['name']['en'],
                    ]);

                    if (!$category instanceof CategoryEntity) {
                        throw new \RuntimeException(sprintf('Category "%s" was not found for tenant %s', $leaf['name']['en'], $tenantId->toString()));
                    }

                    $products = $this->entityManager->getRepository(ProductEntity::class)->findBy(
                        ['tenantId' => $tenantId->toString(), 'categoryId' => $category->getId()],
                        ['createdAt' => 'ASC'],
                        Sprint3SeedData::CONFIGURABLE_PRODUCTS_PER_LEAF
                    );

                    $optionProfile = Sprint3SeedData::optionProfile($tenant['profile']);

                    foreach ($products as $product) {
                        if (!$product instanceof ProductEntity) {
                            continue;
                        }

                        $productId = ProductId::fromString($product->getId());
                        $this->commandBus->dispatch(new DefineOption(
                            productId: $productId,
                            tenantId: $tenantId,
                            code: 'home' === $tenant['profile'] ? 'finish' : 'color',
                            nameTranslations: $optionProfile['code']['nameTranslations'],
                            position: 1,
                            values: array_map(
                                static fn (array $value, int $position): array => [
                                    'code' => $value['code'],
                                    'nameTranslations' => $value['nameTranslations'],
                                    'position' => $position + 1,
                                ],
                                $optionProfile['code']['values'],
                                array_keys($optionProfile['code']['values'])
                            ),
                        ));

                        $this->commandBus->dispatch(new DefineOption(
                            productId: $productId,
                            tenantId: $tenantId,
                            code: 'electronics' === $tenant['profile'] ? 'storage' : 'size',
                            nameTranslations: $optionProfile['secondary']['nameTranslations'],
                            position: 2,
                            values: array_map(
                                static fn (array $value, int $position): array => [
                                    'code' => $value['code'],
                                    'nameTranslations' => $value['nameTranslations'],
                                    'position' => $position + 1,
                                ],
                                $optionProfile['secondary']['values'],
                                array_keys($optionProfile['secondary']['values'])
                            ),
                        ));

                        $sequence = substr($product->getSku(), -6);
                        $priceAdjustment = 0;

                        foreach (array_slice($optionProfile['code']['values'], 0, 3) as $primaryIndex => $primary) {
                            foreach (array_slice($optionProfile['secondary']['values'], 0, 2) as $secondary) {
                                $variantSku = VariantSKU::fromString(sprintf(
                                    '%s-%s-%s-%s-%s',
                                    $tenant['code'],
                                    $leaf['leafCode'],
                                    $sequence,
                                    $primary['code'],
                                    $secondary['code']
                                ));

                                $this->commandBus->dispatch(new CreateVariant(
                                    variantId: VariantId::fromString((string) \Symfony\Component\Uid\Uuid::v7()),
                                    productId: $productId,
                                    tenantId: $tenantId,
                                    sku: $variantSku,
                                    optionValueMap: [
                                        'home' === $tenant['profile'] ? 'finish' : 'color' => $primary['code'],
                                        'electronics' === $tenant['profile'] ? 'storage' : 'size' => $secondary['code'],
                                    ],
                                    price: Money::fromScalars($product->getPriceAmount() + ($primaryIndex * 700) + $priceAdjustment, $product->getPriceCurrency()),
                                    stockQuantity: max(6, intdiv($product->getStockQuantity(), 2)),
                                    trackInventory: true,
                                    allowBackorder: false,
                                    isActive: true,
                                ));

                                ++$variantsCreated;
                                $priceAdjustment += 300;
                            }
                        }

                        ++$configurableProducts;
                    }
                }
            }

            echo sprintf(
                "   ✓ %s configurable products: %d, variants: %d\n",
                $tenant['name'],
                $configurableProducts,
                $variantsCreated
            );

            $this->entityManager->clear();
        }

        $this->clearTenantContext();

        echo "✅ Product variation data created\n";
    }
}
