<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Model\PricingRule;
use App\Pricing\Infrastructure\Persistence\Doctrine\Entity\PriceListEntity;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PriceListEntityTest extends TestCase
{
    // ============================================
    // fromDomainModel() Tests
    // ============================================

    public function testFromDomainModelMapsAllBasicProperties(): void
    {
        $id = PriceListId::generate();
        $tenantId = TenantId::generate();
        $name = PriceListName::fromString('Test Price List');
        $validFrom = new DateTimeImmutable('2025-01-01');
        $validTo = new DateTimeImmutable('2025-12-31');

        $priceList = PriceList::create($id, $tenantId, $name, 200, $validFrom, $validTo);

        $entity = PriceListEntity::fromDomainModel($priceList);

        $this->assertSame($id->toString(), $entity->getId());
        $this->assertSame($tenantId->toString(), $entity->getTenantId());
        $this->assertSame('Test Price List', $entity->getName());
        $this->assertSame(200, $entity->getPriority());
        $this->assertEquals($validFrom, $entity->getValidFrom());
        $this->assertEquals($validTo, $entity->getValidTo());
        $this->assertFalse($entity->isActive()); // Created inactive
    }

    public function testFromDomainModelMapsEmptyRules(): void
    {
        $priceList = $this->createPriceList();

        $entity = PriceListEntity::fromDomainModel($priceList);

        $this->assertIsArray($entity->getRules());
        $this->assertCount(0, $entity->getRules());
    }

    public function testFromDomainModelMapsSingleRule(): void
    {
        $priceList = $this->createPriceList();
        $productId = ProductId::generate();
        $priceList->addRule(PricingRule::forProduct($productId, Discount::percentage(10.0)));

        $entity = PriceListEntity::fromDomainModel($priceList);

        $rules = $entity->getRules();
        $this->assertCount(1, $rules);
        $this->assertSame('product', $rules[0]['scope']);
        $this->assertSame($productId->toString(), $rules[0]['product_id']);
        $this->assertSame('percentage', $rules[0]['discount']['type']);
        $this->assertSame(10.0, $rules[0]['discount']['percentage']);
    }

    public function testFromDomainModelMapsMultipleRules(): void
    {
        $priceList = $this->createPriceList();
        $product1 = ProductId::generate();
        $product2 = ProductId::generate();

        $priceList->addRule(PricingRule::forProduct($product1, Discount::percentage(10.0)));
        $priceList->addRule(PricingRule::forProduct($product2, Discount::percentage(15.0)));
        $priceList->addRule(PricingRule::forCategory('electronics', Discount::fixed(Money::of('20.00', 'USD'))));

        $entity = PriceListEntity::fromDomainModel($priceList);

        $this->assertCount(3, $entity->getRules());
    }

    public function testFromDomainModelMapsActivePriceList(): void
    {
        $priceList = $this->createPriceList();
        $priceList->activate();

        $entity = PriceListEntity::fromDomainModel($priceList);

        $this->assertTrue($entity->isActive());
    }

    public function testFromDomainModelMapsNullDates(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test'),
            100,
            null, // No validFrom
            null  // No validTo
        );

        $entity = PriceListEntity::fromDomainModel($priceList);

        $this->assertNull($entity->getValidFrom());
        $this->assertNull($entity->getValidTo());
    }

    public function testFromDomainModelMapsTimestamps(): void
    {
        $priceList = $this->createPriceList();

        $entity = PriceListEntity::fromDomainModel($priceList);

        $this->assertInstanceOf(DateTimeImmutable::class, $entity->getCreatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->getUpdatedAt());
    }

    // ============================================
    // toDomainModel() Tests
    // ============================================

    public function testToDomainModelReconstitutesAllProperties(): void
    {
        $original = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Original Name'),
            300
        );

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $this->assertTrue($original->id()->equals($reconstituted->id()));
        $this->assertTrue($original->tenantId()->equals($reconstituted->tenantId()));
        $this->assertTrue($original->name()->equals($reconstituted->name()));
        $this->assertSame($original->priority(), $reconstituted->priority());
        $this->assertSame($original->isActive(), $reconstituted->isActive());
    }

    public function testToDomainModelReconstitutesEmptyRules(): void
    {
        $original = $this->createPriceList();

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $this->assertCount(0, $reconstituted->rules());
    }

    public function testToDomainModelReconstitutesRulesWithPercentageDiscount(): void
    {
        $original = $this->createPriceList();
        $productId = ProductId::generate();
        $original->addRule(PricingRule::forProduct($productId, Discount::percentage(25.0), 5, null));

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $rules = $reconstituted->rules();
        $this->assertCount(1, $rules);
        $this->assertSame('product', $rules[0]->getScope());
        $this->assertTrue($rules[0]->getProductId()->equals($productId));
        $this->assertSame(25.0, $rules[0]->getDiscount()->getPercentage());
        $this->assertSame(5, $rules[0]->getMinQuantity());
    }

    public function testToDomainModelReconstitutesRulesWithFixedDiscount(): void
    {
        $original = $this->createPriceList();
        $fixedAmount = Money::of('50.00', 'EUR');
        $original->addRule(PricingRule::forCategory('books', Discount::fixed($fixedAmount)));

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $rules = $reconstituted->rules();
        $this->assertCount(1, $rules);
        $this->assertSame('category', $rules[0]->getScope());
        $this->assertSame('books', $rules[0]->getCategoryId());
        $this->assertTrue($rules[0]->getDiscount()->getFixedAmount()->equals($fixedAmount));
    }

    public function testToDomainModelReconstitutesRulesWithMinPurchaseAmount(): void
    {
        $original = $this->createPriceList();
        $minAmount = Money::of('100.00', 'USD');
        $original->addRule(PricingRule::forAll(Discount::percentage(10.0), 1, $minAmount));

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $rules = $reconstituted->rules();
        $this->assertCount(1, $rules);
        $this->assertTrue($rules[0]->getMinPurchaseAmount()->equals($minAmount));
    }

    public function testToDomainModelReconstitutesMultipleRules(): void
    {
        $original = $this->createPriceList();
        $product1 = ProductId::generate();
        $product2 = ProductId::generate();

        $original->addRule(PricingRule::forProduct($product1, Discount::percentage(10.0)));
        $original->addRule(PricingRule::forProduct($product2, Discount::fixed(Money::of('15.00', 'USD'))));
        $original->addRule(PricingRule::forCategory('electronics', Discount::percentage(5.0)));

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $this->assertCount(3, $reconstituted->rules());
    }

    public function testToDomainModelReconstitutesNullDates(): void
    {
        $original = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Test'),
            100,
            null,
            null
        );

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $this->assertNull($reconstituted->validFrom());
        $this->assertNull($reconstituted->validTo());
    }

    public function testToDomainModelReconstitutesActiveStatus(): void
    {
        $original = $this->createPriceList();
        $original->activate();

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $this->assertTrue($reconstituted->isActive());
    }

    // ============================================
    // Round-Trip Tests
    // ============================================

    public function testRoundTripPreservesAllDataWithNoRules(): void
    {
        $original = $this->createPriceList();

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $this->assertPriceListsEqual($original, $reconstituted);
    }

    public function testRoundTripPreservesAllDataWithRules(): void
    {
        $original = $this->createPriceList();
        $productId = ProductId::generate();
        $minAmount = Money::of('200.00', 'USD');

        $original->addRule(PricingRule::forProduct($productId, Discount::percentage(15.0), 5, $minAmount));
        $original->addRule(PricingRule::forCategory('clothing', Discount::fixed(Money::of('10.00', 'EUR'))));
        $original->addRule(PricingRule::forAll(Discount::percentage(5.0)));

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $this->assertPriceListsEqual($original, $reconstituted);
        $this->assertCount(3, $reconstituted->rules());
    }

    public function testRoundTripPreservesActivatedPriceList(): void
    {
        $original = $this->createPriceList();
        $original->activate();

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $this->assertTrue($reconstituted->isActive());
    }

    public function testRoundTripPreservesComplexScenario(): void
    {
        $validFrom = new DateTimeImmutable('2025-06-01');
        $validTo = new DateTimeImmutable('2025-08-31');

        $original = PriceList::create(
            PriceListId::generate(),
            TenantId::generate(),
            PriceListName::fromString('Summer Sale 2025'),
            500,
            $validFrom,
            $validTo
        );

        $product1 = ProductId::generate();
        $product2 = ProductId::generate();

        $original->addRule(PricingRule::forProduct($product1, Discount::percentage(20.0), 3, Money::of('50.00', 'USD')));
        $original->addRule(PricingRule::forProduct($product2, Discount::fixed(Money::of('25.00', 'EUR')), 1, null));
        $original->addRule(PricingRule::forCategory('summer-wear', Discount::percentage(15.0), 2, Money::of('100.00', 'USD')));
        $original->activate();

        $entity = PriceListEntity::fromDomainModel($original);
        $reconstituted = $entity->toDomainModel();

        $this->assertPriceListsEqual($original, $reconstituted);
        $this->assertCount(3, $reconstituted->rules());
        $this->assertTrue($reconstituted->isActive());
        $this->assertEquals($validFrom, $reconstituted->validFrom());
        $this->assertEquals($validTo, $reconstituted->validTo());
    }

    // ============================================
    // updateFromDomainModel() Tests
    // ============================================

    public function testUpdateFromDomainModelUpdatesProperties(): void
    {
        $original = $this->createPriceList();
        $entity = PriceListEntity::fromDomainModel($original);

        // Modify the price list
        $original->update(
            PriceListName::fromString('Updated Name'),
            400,
            new DateTimeImmutable('2025-03-01'),
            new DateTimeImmutable('2025-05-31')
        );
        $original->activate();
        $original->addRule(PricingRule::forAll(Discount::percentage(10.0)));

        // Update entity
        $entity->updateFromDomainModel($original);

        $this->assertSame('Updated Name', $entity->getName());
        $this->assertSame(400, $entity->getPriority());
        $this->assertTrue($entity->isActive());
        $this->assertCount(1, $entity->getRules());
    }

    public function testUpdateFromDomainModelPreservesId(): void
    {
        $original = $this->createPriceList();
        $originalId = $original->id()->toString();
        $entity = PriceListEntity::fromDomainModel($original);

        $original->update(PriceListName::fromString('Updated'), 200, null, null);
        $entity->updateFromDomainModel($original);

        // ID should not change
        $this->assertSame($originalId, $entity->getId());
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

    private function assertPriceListsEqual(PriceList $expected, PriceList $actual): void
    {
        $this->assertTrue($expected->id()->equals($actual->id()));
        $this->assertTrue($expected->tenantId()->equals($actual->tenantId()));
        $this->assertTrue($expected->name()->equals($actual->name()));
        $this->assertSame($expected->priority(), $actual->priority());
        $this->assertSame($expected->isActive(), $actual->isActive());
        $this->assertEquals($expected->validFrom(), $actual->validFrom());
        $this->assertEquals($expected->validTo(), $actual->validTo());
        $this->assertSame(count($expected->rules()), count($actual->rules()));
    }
}
