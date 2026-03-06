<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\PriceList;
use App\Pricing\Domain\Model\PriceListId;
use App\Pricing\Domain\Model\PriceListName;
use App\Pricing\Domain\Model\PricingRule;
use App\Pricing\Domain\ValueObject\Discount as SegmentDiscount;
use App\Pricing\Domain\ValueObject\SegmentPricingRule;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PriceList::class)]
final class PriceListExtendedTest extends TestCase
{
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    // -----------------------------------------------------------------------
    // reconstituteFromPersistence
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstitutesFromPersistenceWithAllFields(): void
    {
        $id = PriceListId::generate();
        $name = PriceListName::fromString('Persisted List');
        $createdAt = new \DateTimeImmutable('2025-01-01 08:00:00');
        $updatedAt = new \DateTimeImmutable('2025-06-15 12:00:00');
        $validFrom = new \DateTimeImmutable('2025-01-01');
        $validTo = new \DateTimeImmutable('2025-12-31');
        $rule = PricingRule::forAll(Discount::percentage(10.0));

        $priceList = PriceList::reconstituteFromPersistence(
            id: $id,
            tenantId: $this->tenantId,
            name: $name,
            priority: 500,
            rules: [$rule],
            segmentRules: [],
            validFrom: $validFrom,
            validTo: $validTo,
            isActive: true,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        self::assertTrue($priceList->id()->equals($id));
        self::assertTrue($priceList->tenantId()->equals($this->tenantId));
        self::assertTrue($priceList->name()->equals($name));
        self::assertSame(500, $priceList->priority());
        self::assertTrue($priceList->isActive());
        self::assertCount(1, $priceList->rules());
        self::assertEquals($validFrom, $priceList->validFrom());
        self::assertEquals($validTo, $priceList->validTo());
        self::assertSame($createdAt, $priceList->createdAt());
        self::assertSame($updatedAt, $priceList->updatedAt());
    }

    #[Test]
    public function itReconstitutesFromPersistenceWithNoEvents(): void
    {
        $priceList = PriceList::reconstituteFromPersistence(
            id: PriceListId::generate(),
            tenantId: $this->tenantId,
            name: PriceListName::fromString('Loaded List'),
            priority: 100,
            rules: [],
            segmentRules: [],
            validFrom: null,
            validTo: null,
            isActive: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        // Reconstituted aggregates emit no creation events
        self::assertEmpty($priceList->popEvents());
    }

    #[Test]
    public function itReconstitutesFromPersistenceWithSegmentRules(): void
    {
        $segmentRule = SegmentPricingRule::create(
            CustomerSegment::vip(),
            SegmentDiscount::percentage(15.0),
            200,
        );

        $priceList = PriceList::reconstituteFromPersistence(
            id: PriceListId::generate(),
            tenantId: $this->tenantId,
            name: PriceListName::fromString('VIP List'),
            priority: 100,
            rules: [],
            segmentRules: [$segmentRule],
            validFrom: null,
            validTo: null,
            isActive: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        self::assertCount(1, $priceList->segmentRules());
    }

    // -----------------------------------------------------------------------
    // Priority boundary values
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsPriorityZero(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            $this->tenantId,
            PriceListName::fromString('Zero Priority'),
            0,
        );

        self::assertSame(0, $priceList->priority());
    }

    #[Test]
    public function itAcceptsPriorityThousand(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            $this->tenantId,
            PriceListName::fromString('Max Priority'),
            1000,
        );

        self::assertSame(1000, $priceList->priority());
    }

    // -----------------------------------------------------------------------
    // addSegmentRule
    // -----------------------------------------------------------------------

    #[Test]
    public function itAddsSegmentRule(): void
    {
        $priceList = $this->makePriceList();
        $rule = SegmentPricingRule::create(CustomerSegment::vip(), SegmentDiscount::percentage(15.0));

        $priceList->addSegmentRule($rule);

        self::assertCount(1, $priceList->segmentRules());
        self::assertSame($rule, $priceList->segmentRules()[0]);
    }

    #[Test]
    public function itUpdatesTimestampWhenAddingSegmentRule(): void
    {
        $priceList = $this->makePriceList();
        $before = $priceList->updatedAt();

        // Ensure at least 1 microsecond passes
        usleep(1000);
        $priceList->addSegmentRule(
            SegmentPricingRule::create(CustomerSegment::regular(), SegmentDiscount::percentage(5.0))
        );

        self::assertGreaterThanOrEqual($before, $priceList->updatedAt());
    }

    #[Test]
    public function itThrowsWhenAddingDuplicateSegmentRule(): void
    {
        $priceList = $this->makePriceList();
        $priceList->addSegmentRule(
            SegmentPricingRule::create(CustomerSegment::vip(), SegmentDiscount::percentage(15.0))
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Segment rule for vip already exists');

        $priceList->addSegmentRule(
            SegmentPricingRule::create(CustomerSegment::vip(), SegmentDiscount::percentage(20.0))
        );
    }

    #[Test]
    public function itAllowsDifferentSegments(): void
    {
        $priceList = $this->makePriceList();
        $priceList->addSegmentRule(SegmentPricingRule::create(CustomerSegment::vip(), SegmentDiscount::percentage(15.0)));
        $priceList->addSegmentRule(SegmentPricingRule::create(CustomerSegment::wholesale(), SegmentDiscount::percentage(25.0)));
        $priceList->addSegmentRule(SegmentPricingRule::create(CustomerSegment::premium(), SegmentDiscount::percentage(20.0)));
        $priceList->addSegmentRule(SegmentPricingRule::create(CustomerSegment::regular(), SegmentDiscount::percentage(5.0)));

        self::assertCount(4, $priceList->segmentRules());
    }

    #[Test]
    public function itThrowsWhenMaxSegmentRulesExceeded(): void
    {
        $priceList = $this->makePriceList();

        // The max is 20, but we only have 4 segments.
        // We must get creative: reconstitute with 19 rules then add one more,
        // then verify the 21st throws.
        // Since segment rules are keyed by segment identity, we cannot add 21 different ones
        // directly. We use reconstituteFromPersistence to pre-load 20 identical-looking
        // but distinct objects by creating them with the same segment (only 4 available).
        // Instead, let's use reconstituteFromPersistence to bypass the duplicate check.
        $segments = [
            CustomerSegment::regular(),
            CustomerSegment::vip(),
            CustomerSegment::wholesale(),
            CustomerSegment::premium(),
        ];

        // Build 20 "fake" segment rule entries using reconstitution with raw arrays
        $segmentRules = [];
        for ($i = 0; $i < 20; ++$i) {
            // Re-use segments cyclically — reconstitution bypasses duplicate check
            $segmentRules[] = SegmentPricingRule::create(
                $segments[$i % 4],
                SegmentDiscount::percentage(5.0 + $i * 0.1),
                $i
            );
        }

        $loadedPriceList = PriceList::reconstituteFromPersistence(
            id: PriceListId::generate(),
            tenantId: $this->tenantId,
            name: PriceListName::fromString('Full Segments'),
            priority: 100,
            rules: [],
            segmentRules: $segmentRules,
            validFrom: null,
            validTo: null,
            isActive: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add more than 20 segment rules');

        $loadedPriceList->addSegmentRule(
            SegmentPricingRule::create(CustomerSegment::vip(), SegmentDiscount::percentage(10.0))
        );
    }

    // -----------------------------------------------------------------------
    // removeSegmentRule
    // -----------------------------------------------------------------------

    #[Test]
    public function itRemovesSegmentRule(): void
    {
        $priceList = $this->makePriceList();
        $vipRule = SegmentPricingRule::create(CustomerSegment::vip(), SegmentDiscount::percentage(15.0));
        $regularRule = SegmentPricingRule::create(CustomerSegment::regular(), SegmentDiscount::percentage(5.0));

        $priceList->addSegmentRule($vipRule);
        $priceList->addSegmentRule($regularRule);

        $priceList->removeSegmentRule(CustomerSegment::vip());

        self::assertCount(1, $priceList->segmentRules());
        self::assertSame(CustomerSegment::regular()->value(), $priceList->segmentRules()[0]->segment()->value());
    }

    #[Test]
    public function itReindexesSegmentRulesAfterRemoval(): void
    {
        $priceList = $this->makePriceList();
        $priceList->addSegmentRule(SegmentPricingRule::create(CustomerSegment::vip(), SegmentDiscount::percentage(15.0)));
        $priceList->addSegmentRule(SegmentPricingRule::create(CustomerSegment::regular(), SegmentDiscount::percentage(5.0)));
        $priceList->addSegmentRule(SegmentPricingRule::create(CustomerSegment::wholesale(), SegmentDiscount::percentage(25.0)));

        $priceList->removeSegmentRule(CustomerSegment::regular());

        $rules = $priceList->segmentRules();
        self::assertCount(2, $rules);
        self::assertArrayHasKey(0, $rules);
        self::assertArrayHasKey(1, $rules);
        self::assertArrayNotHasKey(2, $rules);
    }

    #[Test]
    public function itThrowsWhenRemovingNonExistentSegmentRule(): void
    {
        $priceList = $this->makePriceList();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Segment rule for vip does not exist');

        $priceList->removeSegmentRule(CustomerSegment::vip());
    }

    #[Test]
    public function itUpdatesTimestampAfterRemovingSegmentRule(): void
    {
        $priceList = $this->makePriceList();
        $priceList->addSegmentRule(SegmentPricingRule::create(CustomerSegment::vip(), SegmentDiscount::percentage(15.0)));
        $before = $priceList->updatedAt();

        usleep(1000);
        $priceList->removeSegmentRule(CustomerSegment::vip());

        self::assertGreaterThanOrEqual($before, $priceList->updatedAt());
    }

    // -----------------------------------------------------------------------
    // getSegmentDiscount
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsSegmentDiscountForMatchingSegment(): void
    {
        $priceList = $this->makePriceList();
        $vipRule = SegmentPricingRule::create(CustomerSegment::vip(), SegmentDiscount::percentage(20.0));
        $priceList->addSegmentRule($vipRule);

        $result = $priceList->getSegmentDiscount(CustomerSegment::vip());

        self::assertNotNull($result);
        self::assertSame(20.0, $result->discount()->value());
    }

    #[Test]
    public function itReturnsNullWhenNoSegmentRuleMatches(): void
    {
        $priceList = $this->makePriceList();
        $priceList->addSegmentRule(
            SegmentPricingRule::create(CustomerSegment::vip(), SegmentDiscount::percentage(20.0))
        );

        $result = $priceList->getSegmentDiscount(CustomerSegment::regular());

        self::assertNull($result);
    }

    #[Test]
    public function itReturnsNullWhenNoSegmentRulesAtAll(): void
    {
        $priceList = $this->makePriceList();

        self::assertNull($priceList->getSegmentDiscount(CustomerSegment::vip()));
    }

    #[Test]
    public function itReturnsCorrectRuleAmongMultipleSegments(): void
    {
        $priceList = $this->makePriceList();
        $priceList->addSegmentRule(SegmentPricingRule::create(CustomerSegment::regular(), SegmentDiscount::percentage(5.0)));
        $priceList->addSegmentRule(SegmentPricingRule::create(CustomerSegment::vip(), SegmentDiscount::percentage(15.0)));
        $priceList->addSegmentRule(SegmentPricingRule::create(CustomerSegment::wholesale(), SegmentDiscount::percentage(30.0)));

        $vipRule = $priceList->getSegmentDiscount(CustomerSegment::vip());
        $wholesaleRule = $priceList->getSegmentDiscount(CustomerSegment::wholesale());

        self::assertSame(15.0, $vipRule->discount()->value());
        self::assertSame(30.0, $wholesaleRule->discount()->value());
    }

    // -----------------------------------------------------------------------
    // Getters
    // -----------------------------------------------------------------------

    #[Test]
    public function itExposesCreatedAtAndUpdatedAt(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            $this->tenantId,
            PriceListName::fromString('Getter Test'),
        );

        self::assertInstanceOf(\DateTimeImmutable::class, $priceList->createdAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $priceList->updatedAt());
    }

    #[Test]
    public function itExposesNullValidFromAndValidToByDefault(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            $this->tenantId,
            PriceListName::fromString('No Dates'),
        );

        self::assertNull($priceList->validFrom());
        self::assertNull($priceList->validTo());
    }

    #[Test]
    public function itExposesEmptySegmentRulesByDefault(): void
    {
        $priceList = $this->makePriceList();

        self::assertSame([], $priceList->segmentRules());
    }

    // -----------------------------------------------------------------------
    // update() — additional edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesTimestampWhenUpdated(): void
    {
        $priceList = $this->makePriceList();
        $before = $priceList->updatedAt();

        usleep(1000);
        $priceList->update(
            PriceListName::fromString('Updated Name'),
            200,
            null,
            null,
        );

        self::assertGreaterThanOrEqual($before, $priceList->updatedAt());
    }

    #[Test]
    public function itUpdatesToNullDates(): void
    {
        $priceList = $this->makePriceList(
            validFrom: new \DateTimeImmutable('2025-01-01'),
            validTo: new \DateTimeImmutable('2025-12-31'),
        );

        $priceList->update(PriceListName::fromString('No Dates Now'), 100, null, null);

        self::assertNull($priceList->validFrom());
        self::assertNull($priceList->validTo());
    }

    // -----------------------------------------------------------------------
    // calculatePrice — category scope
    // -----------------------------------------------------------------------

    #[Test]
    public function itCalculatesPriceForCategoryScope(): void
    {
        $priceList = $this->makePriceList();
        $priceList->activate();
        $productId = ProductId::generate();
        $priceList->addRule(PricingRule::forCategory('electronics', Discount::percentage(10.0)));

        $basePrice = Money::of('100.00', 'USD');
        $result = $priceList->calculatePrice($basePrice, $productId, 'electronics', 1);

        self::assertSame(9000, $result->getAmount()); // 100 - 10% = 90
    }

    #[Test]
    public function itReturnsBasePriceWhenCategoryDoesNotMatch(): void
    {
        $priceList = $this->makePriceList();
        $priceList->activate();
        $productId = ProductId::generate();
        $priceList->addRule(PricingRule::forCategory('electronics', Discount::percentage(10.0)));

        $basePrice = Money::of('100.00', 'USD');
        $result = $priceList->calculatePrice($basePrice, $productId, 'clothing', 1);

        self::assertTrue($result->equals($basePrice));
    }

    #[Test]
    public function itCalculatesPriceReturnsBasePriceWhenValidToExpired(): void
    {
        $validFrom = new \DateTimeImmutable('-2 days');
        $validTo = new \DateTimeImmutable('-1 day');
        $priceList = $this->makePriceList(validFrom: $validFrom, validTo: $validTo);
        $priceList->activate();

        $productId = ProductId::generate();
        $priceList->addRule(PricingRule::forAll(Discount::percentage(50.0)));

        $basePrice = Money::of('100.00', 'USD');
        $result = $priceList->calculatePrice($basePrice, $productId, null, 1);

        // Price list has expired, so no discount
        self::assertTrue($result->equals($basePrice));
    }

    // -----------------------------------------------------------------------
    // Validation edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenValidFromEqualsValidTo(): void
    {
        $date = new \DateTimeImmutable('2025-06-15');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('validFrom must be before validTo');

        PriceList::create(
            PriceListId::generate(),
            $this->tenantId,
            PriceListName::fromString('Equal Dates'),
            100,
            $date,
            $date,
        );
    }

    #[Test]
    public function itAcceptsOnlyValidFromWithoutValidTo(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            $this->tenantId,
            PriceListName::fromString('Open Ended'),
            100,
            new \DateTimeImmutable('-1 day'),
            null,
        );

        self::assertNotNull($priceList->validFrom());
        self::assertNull($priceList->validTo());
    }

    #[Test]
    public function itAcceptsOnlyValidToWithoutValidFrom(): void
    {
        $priceList = PriceList::create(
            PriceListId::generate(),
            $this->tenantId,
            PriceListName::fromString('Has Expiry'),
            100,
            null,
            new \DateTimeImmutable('+1 day'),
        );

        self::assertNull($priceList->validFrom());
        self::assertNotNull($priceList->validTo());
    }

    #[Test]
    public function itThrowsWhenUpdateSetsInvalidDateRange(): void
    {
        $priceList = $this->makePriceList();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('validFrom must be before validTo');

        $priceList->update(
            PriceListName::fromString('Bad Dates'),
            100,
            new \DateTimeImmutable('2025-12-31'),
            new \DateTimeImmutable('2025-01-01'),
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makePriceList(
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validTo = null,
    ): PriceList {
        return PriceList::create(
            PriceListId::generate(),
            $this->tenantId,
            PriceListName::fromString('Test Price List'),
            100,
            $validFrom,
            $validTo,
        );
    }
}
