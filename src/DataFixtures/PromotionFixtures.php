<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Pricing\Application\Command\ActivatePromotion\ActivatePromotionCommand;
use App\Pricing\Application\Command\CreatePromotion\CreatePromotionCommand;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

final class PromotionFixtures extends AbstractTenantAwareFixture implements DependentFixtureInterface, FixtureGroupInterface
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
        return ['sprint3', 'sprint3-promotions'];
    }

    public function load(ObjectManager $manager): void
    {
        echo "🎁 Creating promotions and coupons...\n";

        $tenantIds = $this->tenantIdsByOwnerEmail();
        $today = new \DateTimeImmutable('today');

        foreach (Sprint3SeedData::tenants() as $tenant) {
            $tenantId = TenantId::fromString($tenantIds[$tenant['ownerEmail']]);
            $this->activateTenantContext($tenantId);

            $definitions = $this->promotionDefinitions($tenant['code'], $today);
            foreach ($definitions as $definition) {
                $promotionId = PromotionId::generate();

                $this->commandBus->dispatch(new CreatePromotionCommand(
                    promotionId: $promotionId,
                    tenantId: $tenantId,
                    name: $definition['name'],
                    type: $definition['type'],
                    discountType: $definition['discountType'],
                    discountValue: $definition['discountValue'],
                    priority: $definition['priority'],
                    couponCode: $definition['couponCode'],
                    conditions: $definition['conditions'],
                    validFrom: $definition['validFrom']?->format(\DateTimeInterface::ATOM),
                    validTo: $definition['validTo']?->format(\DateTimeInterface::ATOM),
                ));

                $this->commandBus->dispatch(new ActivatePromotionCommand($promotionId, $tenantId));
            }

            echo sprintf("   ✓ %s promotions: %d\n", $tenant['name'], count($definitions));
        }

        $this->clearTenantContext();

        echo "✅ Promotions and coupons created\n";
    }

    public function getDependencies(): array
    {
        return [TenantFixtures::class];
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     type: string,
     *     discountType: string,
     *     discountValue: float,
     *     priority: int,
     *     couponCode: string|null,
     *     conditions: array<string, mixed>,
     *     validFrom: \DateTimeImmutable|null,
     *     validTo: \DateTimeImmutable|null
     * }>
     */
    private function promotionDefinitions(string $tenantCode, \DateTimeImmutable $today): array
    {
        $pastStart = $today->modify('-45 days');
        $pastEnd = $today->modify('-10 days');
        $futureStart = $today->modify('+15 days');
        $futureEnd = $today->modify('+45 days');

        return [
            ['name' => 'Welcome Ten Percent', 'type' => 'coupon', 'discountType' => 'percentage', 'discountValue' => 10.0, 'priority' => 180, 'couponCode' => $tenantCode.'NEW10', 'conditions' => ['new_customer_only' => true], 'validFrom' => $today->modify('-14 days'), 'validTo' => $today->modify('+120 days')],
            ['name' => 'Free Shipping Over 75', 'type' => 'cart_rule', 'discountType' => 'fixed_amount', 'discountValue' => 12.0, 'priority' => 150, 'couponCode' => null, 'conditions' => ['min_cart_value' => 75.0], 'validFrom' => $today->modify('-7 days'), 'validTo' => $today->modify('+90 days')],
            ['name' => 'Bundle Cart Boost', 'type' => 'cart_rule', 'discountType' => 'percentage', 'discountValue' => 12.5, 'priority' => 145, 'couponCode' => null, 'conditions' => ['min_quantity' => 3], 'validFrom' => $today->modify('-30 days'), 'validTo' => $today->modify('+60 days')],
            ['name' => 'VIP Access', 'type' => 'coupon', 'discountType' => 'percentage', 'discountValue' => 15.0, 'priority' => 170, 'couponCode' => $tenantCode.'VIP15', 'conditions' => ['customer_segments' => ['vip', 'premium']], 'validFrom' => $today->modify('-20 days'), 'validTo' => $today->modify('+75 days')],
            ['name' => 'Launch Catalog Spotlight', 'type' => 'catalog_rule', 'discountType' => 'percentage', 'discountValue' => 8.0, 'priority' => 130, 'couponCode' => null, 'conditions' => ['product_categories' => ['launch']], 'validFrom' => $today->modify('-5 days'), 'validTo' => $today->modify('+50 days')],
            ['name' => 'Spend 150 Save 20', 'type' => 'cart_rule', 'discountType' => 'fixed_amount', 'discountValue' => 20.0, 'priority' => 155, 'couponCode' => null, 'conditions' => ['min_cart_value' => 150.0], 'validFrom' => $today->modify('-25 days'), 'validTo' => $today->modify('+60 days')],
            ['name' => 'Weekend Cart Drop', 'type' => 'coupon', 'discountType' => 'percentage', 'discountValue' => 12.0, 'priority' => 140, 'couponCode' => $tenantCode.'WKND12', 'conditions' => ['min_cart_value' => 90.0], 'validFrom' => $today->modify('-3 days'), 'validTo' => $today->modify('+25 days')],
            ['name' => 'Premium Delivery Credit', 'type' => 'coupon', 'discountType' => 'fixed_amount', 'discountValue' => 18.0, 'priority' => 160, 'couponCode' => $tenantCode.'SHIP18', 'conditions' => ['min_cart_value' => 120.0], 'validFrom' => $today->modify('-12 days'), 'validTo' => $today->modify('+80 days')],
            ['name' => 'Category Feature Event', 'type' => 'catalog_rule', 'discountType' => 'percentage', 'discountValue' => 9.0, 'priority' => 125, 'couponCode' => null, 'conditions' => ['featured_only' => true], 'validFrom' => $today->modify('-15 days'), 'validTo' => $today->modify('+40 days')],
            ['name' => 'Member Recharge', 'type' => 'coupon', 'discountType' => 'fixed_amount', 'discountValue' => 14.0, 'priority' => 135, 'couponCode' => $tenantCode.'CARE14', 'conditions' => ['min_cart_value' => 80.0], 'validFrom' => $today->modify('-18 days'), 'validTo' => $today->modify('+35 days')],
            ['name' => 'Last Season Markdown', 'type' => 'catalog_rule', 'discountType' => 'percentage', 'discountValue' => 20.0, 'priority' => 110, 'couponCode' => null, 'conditions' => ['inventory_age' => 'legacy'], 'validFrom' => $pastStart, 'validTo' => $pastEnd],
            ['name' => 'Clearance Coupon', 'type' => 'coupon', 'discountType' => 'percentage', 'discountValue' => 18.0, 'priority' => 105, 'couponCode' => $tenantCode.'CLR18', 'conditions' => ['min_cart_value' => 60.0], 'validFrom' => $pastStart, 'validTo' => $pastEnd],
            ['name' => 'Dormant Basket Rescue', 'type' => 'coupon', 'discountType' => 'fixed_amount', 'discountValue' => 15.0, 'priority' => 108, 'couponCode' => $tenantCode.'SAVE15', 'conditions' => ['abandoned_cart' => true], 'validFrom' => $pastStart, 'validTo' => $pastEnd],
            ['name' => 'Spring Preview', 'type' => 'catalog_rule', 'discountType' => 'percentage', 'discountValue' => 11.0, 'priority' => 115, 'couponCode' => null, 'conditions' => ['collection' => 'preview'], 'validFrom' => $futureStart, 'validTo' => $futureEnd],
            ['name' => 'Next Month First Order', 'type' => 'coupon', 'discountType' => 'percentage', 'discountValue' => 9.5, 'priority' => 165, 'couponCode' => $tenantCode.'NEXT9', 'conditions' => ['new_customer_only' => true], 'validFrom' => $futureStart, 'validTo' => $futureEnd],
            ['name' => 'Warehouse Move Sale', 'type' => 'cart_rule', 'discountType' => 'fixed_amount', 'discountValue' => 25.0, 'priority' => 120, 'couponCode' => null, 'conditions' => ['min_cart_value' => 180.0], 'validFrom' => $futureStart, 'validTo' => $futureEnd],
            ['name' => 'Manager Spotlight Coupon', 'type' => 'coupon', 'discountType' => 'percentage', 'discountValue' => 13.0, 'priority' => 150, 'couponCode' => $tenantCode.'SPOT13', 'conditions' => ['manager_pick' => true], 'validFrom' => $today->modify('-8 days'), 'validTo' => $today->modify('+55 days')],
            ['name' => 'Subscriber Circle', 'type' => 'coupon', 'discountType' => 'percentage', 'discountValue' => 7.5, 'priority' => 118, 'couponCode' => $tenantCode.'NEWS75', 'conditions' => ['newsletter_subscriber' => true], 'validFrom' => $today->modify('-1 day'), 'validTo' => $today->modify('+120 days')],
        ];
    }
}
