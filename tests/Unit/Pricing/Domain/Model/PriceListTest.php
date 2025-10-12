<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Event\PriceListActivated;
use App\Pricing\Domain\Event\PriceListCreated;
use App\Pricing\Domain\Event\PriceListDeactivated;
use App\Pricing\Domain\Event\PricingRuleAdded;
use App\Pricing\Domain\Event\PricingRuleRemoved;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Model\PricingRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PriceListTest extends TestCase
{
    // ============================================
    // Factory Method Tests
    // ============================================

    public function testCreateCreatesNewPriceList(): void
    {
        $id = PriceListId::generate();
        $tenantId = TenantId::generate();
        $name = PriceListName::fromString('Summer Sale');

        $priceList = PriceList::create($id, $tenantId, $name);

        $this->assertTrue($priceList->id()->equals($id));
        $this->assertTrue($priceList->tenantId()->equals($tenantId));
        $this->assertTrue($priceList->name()->equals($name));
        $this->assertSame(100, $priceList->priority()); // Default
        $this->assertFalse($priceList->isActive()); // Created inactive
        $this->assertCount(0, $priceList->rules()); // Empty rules
    }

    public function testCreateWithCustomPriority(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('VIP Pricing'),
            500
        );

        $this->assertSame(500, $priceList->priority());
    }

    public function testCreateWithValidityDates(): void
    {
        $validFrom = new DateTimeImmutable('2025-01-01');
        $validTo = new DateTimeImmutable('2025-12-31');

        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Year 2025'),
            100,
            $validFrom,
            $validTo
        );

        $this->assertEquals($validFrom, $priceList->validFrom());
        $this->assertEquals($validTo, $priceList->validTo());
    }

    public function testCreateRecordsPriceListCreatedEvent(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test')
        );

        $events = $priceList->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PriceListCreated::class, $events[0]);
    }

    // ============================================
    // addRule() Tests
    // ============================================

    public function testAddRuleAddsRuleSuccessfully(): void
    {
        $priceList = $this->createPriceList();
        $rule = PricingRule::forAll(Discount::percentage(10.0));

        $priceList->addRule($rule);

        $this->assertCount(1, $priceList->rules());
        $this->assertSame($rule, $priceList->rules()[0]);
    }

    public function testAddRuleRecordsEvent(): void
    {
        $priceList = $this->createPriceList();
        $rule = PricingRule::forAll(Discount::percentage(10.0));

        $priceList->popEvents(); // Clear creation event
        $priceList->addRule($rule);

        $events = $priceList->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PricingRuleAdded::class, $events[0]);
    }

    public function testAddRuleThrowsExceptionWhenMaxRulesExceeded(): void
    {
        $priceList = $this->createPriceList();

        // Add 100 rules (maximum)
        for ($i = 0; $i < 100; $i++) {
            $productId = ProductId::generate();
            $priceList->addRule(PricingRule::forProduct($productId, Discount::percentage(5.0)));
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add more than 100 rules');

        // Try to add 101st rule
        $priceList->addRule(PricingRule::forProduct(ProductId::generate(), Discount::percentage(5.0)));
    }

    public function testAddRuleThrowsExceptionForOverlappingProductRules(): void
    {
        $priceList = $this->createPriceList();
        $productId = ProductId::generate();

        $priceList->addRule(PricingRule::forProduct($productId, Discount::percentage(10.0)));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('overlapping pricing rule');

        // Try to add another rule for same product
        $priceList->addRule(PricingRule::forProduct($productId, Discount::percentage(20.0)));
    }

    public function testAddRuleThrowsExceptionForOverlappingCategoryRules(): void
    {
        $priceList = $this->createPriceList();

        $priceList->addRule(PricingRule::forCategory('electronics', Discount::percentage(10.0)));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('overlapping pricing rule');

        // Try to add another rule for same category
        $priceList->addRule(PricingRule::forCategory('electronics', Discount::percentage(15.0)));
    }

    public function testAddRuleThrowsExceptionForOverlappingAllRules(): void
    {
        $priceList = $this->createPriceList();

        $priceList->addRule(PricingRule::forAll(Discount::percentage(5.0)));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('overlapping pricing rule');

        // Try to add another "all" rule
        $priceList->addRule(PricingRule::forAll(Discount::percentage(10.0)));
    }

    public function testAddRuleAllowsNonOverlappingRules(): void
    {
        $priceList = $this->createPriceList();
        $product1 = ProductId::generate();
        $product2 = ProductId::generate();

        $priceList->addRule(PricingRule::forProduct($product1, Discount::percentage(10.0)));
        $priceList->addRule(PricingRule::forProduct($product2, Discount::percentage(15.0)));
        $priceList->addRule(PricingRule::forCategory('electronics', Discount::percentage(12.0)));

        $this->assertCount(3, $priceList->rules());
    }

    // ============================================
    // removeRule() Tests
    // ============================================

    public function testRemoveRuleRemovesRuleByIndex(): void
    {
        $priceList = $this->createPriceList();
        $rule1 = PricingRule::forProduct(ProductId::generate(), Discount::percentage(10.0));
        $rule2 = PricingRule::forProduct(ProductId::generate(), Discount::percentage(15.0));

        $priceList->addRule($rule1);
        $priceList->addRule($rule2);

        $priceList->removeRule(0);

        $this->assertCount(1, $priceList->rules());
        $this->assertSame($rule2, $priceList->rules()[0]);
    }

    public function testRemoveRuleRecordsEvent(): void
    {
        $priceList = $this->createPriceList();
        $rule = PricingRule::forAll(Discount::percentage(10.0));
        $priceList->addRule($rule);

        $priceList->popEvents(); // Clear previous events
        $priceList->removeRule(0);

        $events = $priceList->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PricingRuleRemoved::class, $events[0]);
    }

    public function testRemoveRuleThrowsExceptionForInvalidIndex(): void
    {
        $priceList = $this->createPriceList();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rule at index 0 does not exist');

        $priceList->removeRule(0);
    }

    public function testRemoveRuleReindexesArray(): void
    {
        $priceList = $this->createPriceList();
        $rule1 = PricingRule::forProduct(ProductId::generate(), Discount::percentage(10.0));
        $rule2 = PricingRule::forProduct(ProductId::generate(), Discount::percentage(15.0));
        $rule3 = PricingRule::forProduct(ProductId::generate(), Discount::percentage(20.0));

        $priceList->addRule($rule1);
        $priceList->addRule($rule2);
        $priceList->addRule($rule3);

        $priceList->removeRule(1); // Remove middle rule

        $rules = $priceList->rules();
        $this->assertCount(2, $rules);
        $this->assertSame($rule1, $rules[0]);
        $this->assertSame($rule3, $rules[1]); // Re-indexed from 2 to 1
    }

    // ============================================
    // activate() / deactivate() Tests
    // ============================================

    public function testActivateChangesStatusToActive(): void
    {
        $priceList = $this->createPriceList();
        $this->assertFalse($priceList->isActive());

        $priceList->activate();

        $this->assertTrue($priceList->isActive());
    }

    public function testActivateRecordsEvent(): void
    {
        $priceList = $this->createPriceList();
        $priceList->popEvents(); // Clear creation event

        $priceList->activate();

        $events = $priceList->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PriceListActivated::class, $events[0]);
    }

    public function testActivateThrowsExceptionWhenAlreadyActive(): void
    {
        $priceList = $this->createPriceList();
        $priceList->activate();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already active');

        $priceList->activate();
    }

    public function testDeactivateChangesStatusToInactive(): void
    {
        $priceList = $this->createPriceList();
        $priceList->activate();

        $priceList->deactivate();

        $this->assertFalse($priceList->isActive());
    }

    public function testDeactivateRecordsEvent(): void
    {
        $priceList = $this->createPriceList();
        $priceList->activate();
        $priceList->popEvents(); // Clear previous events

        $priceList->deactivate();

        $events = $priceList->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PriceListDeactivated::class, $events[0]);
    }

    public function testDeactivateThrowsExceptionWhenAlreadyInactive(): void
    {
        $priceList = $this->createPriceList();
        $this->assertFalse($priceList->isActive());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already inactive');

        $priceList->deactivate();
    }

    // ============================================
    // update() Tests
    // ============================================

    public function testUpdateChangesProperties(): void
    {
        $priceList = $this->createPriceList();
        $newName = PriceListName::fromString('Updated Name');
        $validFrom = new DateTimeImmutable('2025-06-01');
        $validTo = new DateTimeImmutable('2025-12-31');

        $priceList->update($newName, 200, $validFrom, $validTo);

        $this->assertTrue($priceList->name()->equals($newName));
        $this->assertSame(200, $priceList->priority());
        $this->assertEquals($validFrom, $priceList->validFrom());
        $this->assertEquals($validTo, $priceList->validTo());
    }

    // ============================================
    // isValidNow() Tests
    // ============================================

    public function testIsValidNowReturnsFalseWhenInactive(): void
    {
        $priceList = $this->createPriceList();

        $this->assertFalse($priceList->isValidNow());
    }

    public function testIsValidNowReturnsTrueWhenActiveAndNoDates(): void
    {
        $priceList = $this->createPriceList();
        $priceList->activate();

        $this->assertTrue($priceList->isValidNow());
    }

    public function testIsValidNowReturnsFalseBeforeValidFrom(): void
    {
        $validFrom = new DateTimeImmutable('+1 day');
        $priceList = $this->createPriceListWithDates($validFrom, null);
        $priceList->activate();

        $this->assertFalse($priceList->isValidNow());
    }

    public function testIsValidNowReturnsTrueAfterValidFrom(): void
    {
        $validFrom = new DateTimeImmutable('-1 day');
        $priceList = $this->createPriceListWithDates($validFrom, null);
        $priceList->activate();

        $this->assertTrue($priceList->isValidNow());
    }

    public function testIsValidNowReturnsFalseAfterValidTo(): void
    {
        $validTo = new DateTimeImmutable('-1 day');
        $priceList = $this->createPriceListWithDates(null, $validTo);
        $priceList->activate();

        $this->assertFalse($priceList->isValidNow());
    }

    public function testIsValidNowReturnsTrueBeforeValidTo(): void
    {
        $validTo = new DateTimeImmutable('+1 day');
        $priceList = $this->createPriceListWithDates(null, $validTo);
        $priceList->activate();

        $this->assertTrue($priceList->isValidNow());
    }

    public function testIsValidNowWithinDateRange(): void
    {
        $validFrom = new DateTimeImmutable('-1 day');
        $validTo = new DateTimeImmutable('+1 day');
        $priceList = $this->createPriceListWithDates($validFrom, $validTo);
        $priceList->activate();

        $this->assertTrue($priceList->isValidNow());
    }

    // ============================================
    // calculatePrice() Tests
    // ============================================

    public function testCalculatePriceReturnsOriginalPriceWhenInactive(): void
    {
        $priceList = $this->createPriceList();
        $basePrice = Money::of('100.00', 'USD');
        $productId = ProductId::generate();

        $priceList->addRule(PricingRule::forProduct($productId, Discount::percentage(50.0)));

        $finalPrice = $priceList->calculatePrice($basePrice, $productId, null, 1);

        $this->assertTrue($finalPrice->equals($basePrice)); // No discount applied
    }

    public function testCalculatePriceAppliesDiscountWhenActive(): void
    {
        $priceList = $this->createPriceList();
        $priceList->activate();

        $basePrice = Money::of('100.00', 'USD');
        $productId = ProductId::generate();
        $priceList->addRule(PricingRule::forProduct($productId, Discount::percentage(20.0)));

        $finalPrice = $priceList->calculatePrice($basePrice, $productId, null, 1);

        $this->assertSame(8000, $finalPrice->getAmount()); // 100 - 20% = 80
    }

    public function testCalculatePriceReturnsOriginalWhenNoRulesApply(): void
    {
        $priceList = $this->createPriceList();
        $priceList->activate();

        $basePrice = Money::of('100.00', 'USD');
        $product1 = ProductId::generate();
        $product2 = ProductId::generate();

        $priceList->addRule(PricingRule::forProduct($product1, Discount::percentage(20.0)));

        $finalPrice = $priceList->calculatePrice($basePrice, $product2, null, 1);

        $this->assertTrue($finalPrice->equals($basePrice));
    }

    public function testCalculatePriceWithQuantityCondition(): void
    {
        $priceList = $this->createPriceList();
        $priceList->activate();

        $basePrice = Money::of('50.00', 'USD');
        $productId = ProductId::generate();

        // Discount only if quantity >= 5
        $priceList->addRule(PricingRule::forProduct($productId, Discount::percentage(15.0), 5, null));

        // Quantity 3 - no discount
        $price1 = $priceList->calculatePrice($basePrice, $productId, null, 3);
        $this->assertTrue($price1->equals($basePrice));

        // Quantity 5 - discount applied
        $price2 = $priceList->calculatePrice($basePrice, $productId, null, 5);
        $this->assertSame(4250, $price2->getAmount()); // 50 - 15% = 42.50
    }

    public function testCalculatePriceWithAmountCondition(): void
    {
        $priceList = $this->createPriceList();
        $priceList->activate();

        $basePrice = Money::of('40.00', 'USD');
        $productId = ProductId::generate();
        $minAmount = Money::of('100.00', 'USD');

        // Discount only if total >= 100
        $priceList->addRule(PricingRule::forProduct($productId, Discount::percentage(10.0), 1, $minAmount));

        // Quantity 2 (total 80) - no discount
        $price1 = $priceList->calculatePrice($basePrice, $productId, null, 2);
        $this->assertTrue($price1->equals($basePrice));

        // Quantity 3 (total 120) - discount applied
        $price2 = $priceList->calculatePrice($basePrice, $productId, null, 3);
        $this->assertSame(3600, $price2->getAmount()); // 40 - 10% = 36
    }

    public function testCalculatePriceAppliesFirstApplicableRule(): void
    {
        $priceList = $this->createPriceList();
        $priceList->activate();

        $basePrice = Money::of('100.00', 'USD');
        $productId = ProductId::generate();

        // Add all scope rule (applies to everything)
        $priceList->addRule(PricingRule::forAll(Discount::percentage(10.0)));

        $finalPrice = $priceList->calculatePrice($basePrice, $productId, null, 1);

        $this->assertSame(9000, $finalPrice->getAmount()); // 10% discount
    }

    // ============================================
    // Business Rules Validation Tests
    // ============================================

    public function testCreateThrowsExceptionForInvalidPriority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be between 0 and 1000');

        PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test'),
            1001 // Invalid priority
        );
    }

    public function testCreateThrowsExceptionForNegativePriority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be between 0 and 1000');

        PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test'),
            -1
        );
    }

    public function testCreateThrowsExceptionWhenValidFromAfterValidTo(): void
    {
        $validFrom = new DateTimeImmutable('2025-12-31');
        $validTo = new DateTimeImmutable('2025-01-01');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('validFrom must be before validTo');

        PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test'),
            100,
            $validFrom,
            $validTo
        );
    }

    public function testUpdateThrowsExceptionForInvalidPriority(): void
    {
        $priceList = $this->createPriceList();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be between 0 and 1000');

        $priceList->update(
            PriceListName::fromString('Updated'),
            2000, // Invalid
            null,
            null
        );
    }

    public function testUpdateThrowsExceptionForInvalidDateRange(): void
    {
        $priceList = $this->createPriceList();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('validFrom must be before validTo');

        $priceList->update(
            PriceListName::fromString('Updated'),
            100,
            new DateTimeImmutable('2025-12-31'),
            new DateTimeImmutable('2025-01-01')
        );
    }

    // ============================================
    // Helper Methods
    // ============================================

    private function createPriceList(): PriceList
    {
        return PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test Price List')
        );
    }

    private function createPriceListWithDates(
        ?DateTimeImmutable $validFrom,
        ?DateTimeImmutable $validTo
    ): PriceList {
        return PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test Price List'),
            100,
            $validFrom,
            $validTo
        );
    }
}
