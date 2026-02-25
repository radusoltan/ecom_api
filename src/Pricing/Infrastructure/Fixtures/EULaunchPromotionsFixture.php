<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Fixtures;

use App\Pricing\Application\Command\CreatePromotion\CreatePromotionCommand;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * EU Launch Promotions Fixture.
 *
 * Seeds common promotion scenarios for EU e-commerce launch:
 * - Welcome discount for new customers
 * - Free shipping promotions
 * - Seasonal sales (Winter, Spring, Summer, Black Friday)
 * - Volume discounts
 * - VIP customer promotions
 * - Flash sales
 * - Bundle deals
 * - Coupon codes for marketing campaigns
 */
final class EULaunchPromotionsFixture extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['promotions', 'eu', 'production'];
    }

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly Connection $connection,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Get first tenant dynamically
        $tenantIdString = $this->connection->fetchOne('SELECT id FROM tenants ORDER BY created_at LIMIT 1');
        if (!$tenantIdString) {
            echo "   ⚠️  No tenants found. Skipping promotions fixtures.\n";

            return;
        }
        $tenantId = TenantId::fromString($tenantIdString);

        // Set RLS context
        $this->connection->executeStatement("SET app.tenant_id = '{$tenantIdString}'");

        echo "🎁  Creating EU Launch Promotions...\n\n";

        // 1. Welcome Discounts
        echo "📦 Welcome & First Order Promotions:\n";
        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Welcome 10% Off',
            type: PromotionType::coupon(),
            discount: Discount::percentage(10.0),
            couponCode: 'WELCOME10',
            priority: 150,
            conditions: ['new_customer_only' => true]
        );
        echo "  ✓ WELCOME10 - 10% off for new customers\n";

        $this->createPromotion(
            tenantId: $tenantId,
            name: 'First Order Free Shipping',
            type: PromotionType::coupon(),
            discount: Discount::fixedAmount(9.99),  // €9.99 shipping credit
            couponCode: 'FREESHIP',
            priority: 140,
            conditions: ['new_customer_only' => true, 'min_cart_value' => 25.00]
        );
        echo "  ✓ FREESHIP - Free shipping on first order (min €25)\n\n";

        // 2. Seasonal Sales
        echo "🌸 Seasonal Promotions:\n";
        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Winter Sale 2025',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(20.0),
            priority: 110,
            validFrom: new \DateTimeImmutable('2025-01-01'),
            validTo: new \DateTimeImmutable('2025-02-28'),
            conditions: ['min_cart_value' => 50.00]
        );
        echo "  ✓ Winter Sale - 20% off (Jan-Feb, min €50)\n";

        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Spring Collection Launch',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(15.0),
            priority: 105,
            validFrom: new \DateTimeImmutable('2025-03-01'),
            validTo: new \DateTimeImmutable('2025-04-30'),
            conditions: ['product_categories' => ['clothing', 'accessories']]
        );
        echo "  ✓ Spring Launch - 15% off clothing & accessories (Mar-Apr)\n";

        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Summer Flash Sale',
            type: PromotionType::coupon(),
            discount: Discount::percentage(25.0),
            couponCode: 'SUMMER25',
            priority: 120,
            validFrom: new \DateTimeImmutable('2025-06-01'),
            validTo: new \DateTimeImmutable('2025-08-31'),
            conditions: ['min_cart_value' => 75.00]
        );
        echo "  ✓ SUMMER25 - 25% off summer sale (Jun-Aug, min €75)\n";

        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Black Friday 2025',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(40.0),
            priority: 200,  // Highest priority
            validFrom: new \DateTimeImmutable('2025-11-28'),
            validTo: new \DateTimeImmutable('2025-11-30'),
            conditions: ['min_cart_value' => 100.00, 'exclude_sale_items' => true]
        );
        echo "  ✓ Black Friday - 40% off (Nov 28-30, min €100)\n\n";

        // 3. Volume Discounts
        echo "📊 Volume & Bundle Discounts:\n";
        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Buy 3+ Items Get 10%',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(10.0),
            priority: 90,
            conditions: ['min_quantity' => 3]
        );
        echo "  ✓ Volume Discount - 10% off when buying 3+ items\n";

        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Spend €150 Save €20',
            type: PromotionType::cartRule(),
            discount: Discount::fixedAmount(20.00),  // €20.00
            priority: 95,
            conditions: ['min_cart_value' => 150.00]
        );
        echo "  ✓ Volume Discount - €20 off orders over €150\n\n";

        // 4. VIP & Customer Segment Promotions
        echo "👑 VIP & Loyalty Promotions:\n";
        $this->createPromotion(
            tenantId: $tenantId,
            name: 'VIP Exclusive 15%',
            type: PromotionType::cartRule(),
            discount: Discount::percentage(15.0),
            priority: 130,
            conditions: ['customer_segments' => ['vip', 'premium']]
        );
        echo "  ✓ VIP Exclusive - 15% off for VIP/Premium members\n";

        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Loyalty Rewards',
            type: PromotionType::coupon(),
            discount: Discount::fixedAmount(10.00),  // €10.00
            couponCode: 'LOYALTY10',
            priority: 125,
            conditions: ['customer_segments' => ['loyal', 'returning']]
        );
        echo "  ✓ LOYALTY10 - €10 off for loyal customers\n\n";

        // 5. Free Shipping Promotions
        echo "🚚 Free Shipping Promotions:\n";
        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Free Shipping Over €50',
            type: PromotionType::cartRule(),
            discount: Discount::fixedAmount(9.99),  // €9.99 typical shipping cost
            priority: 80,
            conditions: ['min_cart_value' => 50.00]
        );
        echo "  ✓ Free Shipping - Orders over €50\n";

        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Free Express Shipping',
            type: PromotionType::coupon(),
            discount: Discount::fixedAmount(14.99),  // €14.99 express shipping
            couponCode: 'EXPRESS',
            priority: 100,
            conditions: ['min_cart_value' => 100.00]
        );
        echo "  ✓ EXPRESS - Free express shipping (min €100)\n\n";

        // 6. Marketing Campaign Coupons
        echo "📢 Marketing Campaign Coupons:\n";
        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Newsletter Subscriber Discount',
            type: PromotionType::coupon(),
            discount: Discount::percentage(10.0),
            couponCode: 'NEWS10',
            priority: 100,
            conditions: ['newsletter_subscriber' => true]
        );
        echo "  ✓ NEWS10 - 10% off for newsletter subscribers\n";

        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Social Media Promotion',
            type: PromotionType::coupon(),
            discount: Discount::percentage(15.0),
            couponCode: 'SOCIAL15',
            priority: 110,
            validFrom: new \DateTimeImmutable('2025-02-01'),
            validTo: new \DateTimeImmutable('2025-12-31')
        );
        echo "  ✓ SOCIAL15 - 15% off social media campaign (2025)\n";

        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Referral Bonus',
            type: PromotionType::coupon(),
            discount: Discount::fixedAmount(15.00),  // €15.00
            couponCode: 'REFER15',
            priority: 120,
            conditions: ['referral_program' => true]
        );
        echo "  ✓ REFER15 - €15 off for successful referrals\n\n";

        // 7. Category-Specific Promotions
        echo "🏷️  Category-Specific Promotions:\n";
        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Electronics Sale',
            type: PromotionType::catalogRule(),
            discount: Discount::percentage(12.0),
            priority: 85,
            conditions: ['product_categories' => ['electronics', 'computers']]
        );
        echo "  ✓ Electronics - 12% off electronics & computers\n";

        $this->createPromotion(
            tenantId: $tenantId,
            name: 'Fashion Week Special',
            type: PromotionType::catalogRule(),
            discount: Discount::percentage(20.0),
            priority: 90,
            validFrom: new \DateTimeImmutable('2025-09-01'),
            validTo: new \DateTimeImmutable('2025-09-30'),
            conditions: ['product_categories' => ['clothing', 'shoes', 'accessories']]
        );
        echo "  ✓ Fashion Week - 20% off fashion items (September)\n\n";

        echo '✅ Created '. 18 ." EU Launch promotions\n\n";
    }

    private function createPromotion(
        TenantId $tenantId,
        string $name,
        PromotionType $type,
        Discount $discount,
        int $priority,
        ?string $couponCode = null,
        array $conditions = [],
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validTo = null,
    ): void {
        $command = new CreatePromotionCommand(
            promotionId: PromotionId::generate(),
            tenantId: $tenantId,
            name: $name,
            type: $type->toString(),
            discountType: $discount->type()->toString(),
            discountValue: $discount->value(),
            priority: $priority,
            couponCode: $couponCode,
            conditions: $conditions,
            validFrom: $validFrom?->format('Y-m-d H:i:s'),
            validTo: $validTo?->format('Y-m-d H:i:s')
        );

        $this->commandBus->dispatch($command);
    }
}
