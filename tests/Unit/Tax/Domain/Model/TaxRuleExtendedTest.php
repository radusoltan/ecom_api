<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\Model;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Event\TaxRateChanged;
use App\Tax\Domain\Event\TaxRuleActivated;
use App\Tax\Domain\Event\TaxRuleCreated;
use App\Tax\Domain\Event\TaxRuleDeactivated;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxRule::class)]
final class TaxRuleExtendedTest extends TestCase
{
    private TaxRuleId $id;
    private TenantId $tenantId;
    private TaxJurisdiction $jurisdiction;
    private TaxCategory $category;
    private TaxRate $rate;

    protected function setUp(): void
    {
        $this->id = TaxRuleId::generate();
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->jurisdiction = TaxJurisdiction::fromCountryCode('DE');
        $this->category = TaxCategory::STANDARD;
        $this->rate = TaxRate::fromPercentage(19.0);
    }

    private function createActiveRule(
        string $name = 'Standard VAT DE',
        ?string $description = null,
        int $priority = 0,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validTo = null,
        bool $isReverseCharge = false,
    ): TaxRule {
        return TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->jurisdiction,
            category: $this->category,
            rate: $this->rate,
            name: $name,
            description: $description,
            priority: $priority,
            isActive: true,
            validFrom: $validFrom,
            validTo: $validTo,
            isReverseCharge: $isReverseCharge,
        );
    }

    // -----------------------------------------------------------------------
    // TaxRule::create() happy paths
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesRuleWithAllRequiredFields(): void
    {
        $rule = $this->createActiveRule();

        self::assertTrue($this->id->equals($rule->id()));
        self::assertTrue($this->tenantId->equals($rule->tenantId()));
        self::assertTrue($this->jurisdiction->equals($rule->jurisdiction()));
        self::assertSame(TaxCategory::STANDARD, $rule->category());
        self::assertTrue($this->rate->equals($rule->rate()));
        self::assertSame('Standard VAT DE', $rule->name());
        self::assertTrue($rule->isActive());
        self::assertFalse($rule->isReverseCharge());
        self::assertSame(0, $rule->priority());
    }

    #[Test]
    public function itTrimsWhitespaceFromName(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->jurisdiction,
            category: $this->category,
            rate: $this->rate,
            name: '  Trimmed Name  ',
        );

        self::assertSame('Trimmed Name', $rule->name());
    }

    #[Test]
    public function itSetsDescriptionWhenProvided(): void
    {
        $rule = $this->createActiveRule(description: 'A tax rule for Germany');

        self::assertSame('A tax rule for Germany', $rule->description());
    }

    #[Test]
    public function itSetsNullDescriptionByDefault(): void
    {
        $rule = $this->createActiveRule();

        self::assertNull($rule->description());
    }

    #[Test]
    public function itSetsPriorityWhenProvided(): void
    {
        $rule = $this->createActiveRule(priority: 10);

        self::assertSame(10, $rule->priority());
    }

    #[Test]
    public function itCreatesWithInactiveStatus(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->jurisdiction,
            category: $this->category,
            rate: $this->rate,
            name: 'Inactive Rule',
            isActive: false,
        );

        self::assertFalse($rule->isActive());
    }

    #[Test]
    public function itSetsValidFromAndValidTo(): void
    {
        $from = new \DateTimeImmutable('2024-01-01');
        $to = new \DateTimeImmutable('2024-12-31');

        $rule = $this->createActiveRule(validFrom: $from, validTo: $to);

        self::assertSame($from->getTimestamp(), $rule->validFrom()->getTimestamp());
        self::assertSame($to->getTimestamp(), $rule->validTo()->getTimestamp());
    }

    #[Test]
    public function itSetsNullValidToByDefault(): void
    {
        $rule = $this->createActiveRule();

        self::assertNull($rule->validTo());
    }

    #[Test]
    public function itRecordsTaxRuleCreatedEvent(): void
    {
        $rule = $this->createActiveRule();
        $events = $rule->popEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(TaxRuleCreated::class, $events[0]);
    }

    #[Test]
    public function itSetsCreatedAtAndUpdatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $rule = $this->createActiveRule();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $rule->createdAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $rule->createdAt()->getTimestamp());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $rule->updatedAt()->getTimestamp());
    }

    // -----------------------------------------------------------------------
    // TaxRule::create() — validation errors
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenNameIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax rule name cannot be empty');

        TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->jurisdiction,
            category: $this->category,
            rate: $this->rate,
            name: '',
        );
    }

    #[Test]
    public function itThrowsWhenNameIsOnlyWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax rule name cannot be empty');

        TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->jurisdiction,
            category: $this->category,
            rate: $this->rate,
            name: '   ',
        );
    }

    #[Test]
    public function itThrowsWhenPriorityIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be >= 0');

        $this->createActiveRule(priority: -1);
    }

    #[Test]
    public function itThrowsWhenValidFromIsAfterValidTo(): void
    {
        $from = new \DateTimeImmutable('2024-12-31');
        $to = new \DateTimeImmutable('2024-01-01');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('validFrom');

        $this->createActiveRule(validFrom: $from, validTo: $to);
    }

    #[Test]
    public function itThrowsWhenReverseChargeOnNonEuJurisdiction(): void
    {
        $usJurisdiction = TaxJurisdiction::fromCountryCode('US');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Reverse charge mechanism is only applicable to EU jurisdictions');

        TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $usJurisdiction,
            category: $this->category,
            rate: $this->rate,
            name: 'US Rule',
            isReverseCharge: true,
        );
    }

    #[Test]
    public function itThrowsWhenReverseChargeOnExemptCategory(): void
    {
        $euJurisdiction = TaxJurisdiction::fromCountryCode('DE');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Reverse charge cannot be applied to exempt category');

        TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $euJurisdiction,
            category: TaxCategory::EXEMPT,
            rate: $this->rate,
            name: 'Exempt Reverse Charge',
            isReverseCharge: true,
        );
    }

    // -----------------------------------------------------------------------
    // TaxRule::reconstituteFromPersistence()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReconstitutesFromPersistenceWithoutEvents(): void
    {
        $createdAt = new \DateTimeImmutable('2024-01-01');
        $updatedAt = new \DateTimeImmutable('2024-06-01');

        $rule = TaxRule::reconstituteFromPersistence(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->jurisdiction,
            category: $this->category,
            rate: $this->rate,
            name: 'Reconstituted Rule',
            description: 'desc',
            priority: 5,
            isActive: false,
            validFrom: $createdAt,
            validTo: null,
            isReverseCharge: false,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        self::assertSame('Reconstituted Rule', $rule->name());
        self::assertFalse($rule->isActive());
        self::assertSame(5, $rule->priority());
        self::assertCount(0, $rule->popEvents());
    }

    // -----------------------------------------------------------------------
    // activate() / deactivate()
    // -----------------------------------------------------------------------

    #[Test]
    public function itActivatesInactiveRule(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->jurisdiction,
            category: $this->category,
            rate: $this->rate,
            name: 'Inactive',
            isActive: false,
        );
        $rule->popEvents(); // Clear created event

        $rule->activate();

        self::assertTrue($rule->isActive());
        $events = $rule->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TaxRuleActivated::class, $events[0]);
    }

    #[Test]
    public function itThrowsWhenActivatingAlreadyActiveRule(): void
    {
        $rule = $this->createActiveRule();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax rule is already active');

        $rule->activate();
    }

    #[Test]
    public function itDeactivatesActiveRule(): void
    {
        $rule = $this->createActiveRule();
        $rule->popEvents();

        $rule->deactivate();

        self::assertFalse($rule->isActive());
        $events = $rule->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TaxRuleDeactivated::class, $events[0]);
    }

    #[Test]
    public function itThrowsWhenDeactivatingAlreadyInactiveRule(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->jurisdiction,
            category: $this->category,
            rate: $this->rate,
            name: 'Inactive',
            isActive: false,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax rule is already inactive');

        $rule->deactivate();
    }

    // -----------------------------------------------------------------------
    // updateRate()
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesRateOnActiveRule(): void
    {
        $rule = $this->createActiveRule();
        $rule->popEvents();
        $newRate = TaxRate::fromPercentage(20.0);

        $rule->updateRate($newRate);

        self::assertTrue($newRate->equals($rule->rate()));
        $events = $rule->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TaxRateChanged::class, $events[0]);
    }

    #[Test]
    public function itSkipsUpdateWhenRateIsUnchanged(): void
    {
        $rule = $this->createActiveRule();
        $rule->popEvents();
        $sameRate = TaxRate::fromPercentage(19.0);

        $rule->updateRate($sameRate);

        self::assertCount(0, $rule->popEvents());
    }

    #[Test]
    public function itThrowsWhenUpdatingRateOnInactiveRule(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->jurisdiction,
            category: $this->category,
            rate: $this->rate,
            name: 'Inactive',
            isActive: false,
        );
        $newRate = TaxRate::fromPercentage(20.0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot change tax rate on inactive rule');

        $rule->updateRate($newRate);
    }

    // -----------------------------------------------------------------------
    // updateValidity()
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesValidityDates(): void
    {
        $rule = $this->createActiveRule();
        $from = new \DateTimeImmutable('2025-01-01');
        $to = new \DateTimeImmutable('2025-12-31');

        $rule->updateValidity($from, $to);

        self::assertSame($from->getTimestamp(), $rule->validFrom()->getTimestamp());
        self::assertSame($to->getTimestamp(), $rule->validTo()->getTimestamp());
    }

    #[Test]
    public function itUpdatesValidityWithNullTo(): void
    {
        $rule = $this->createActiveRule();
        $from = new \DateTimeImmutable('2025-01-01');

        $rule->updateValidity($from, null);

        self::assertNull($rule->validTo());
    }

    #[Test]
    public function itThrowsWhenUpdatingValidityWithInvalidRange(): void
    {
        $rule = $this->createActiveRule();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('validFrom');

        $rule->updateValidity(
            new \DateTimeImmutable('2025-12-31'),
            new \DateTimeImmutable('2025-01-01')
        );
    }

    // -----------------------------------------------------------------------
    // isValidAt()
    // -----------------------------------------------------------------------

    #[Test]
    public function itIsValidAtDateWithinRange(): void
    {
        $rule = $this->createActiveRule(
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validTo: new \DateTimeImmutable('2024-12-31'),
        );

        self::assertTrue($rule->isValidAt(new \DateTimeImmutable('2024-06-15')));
    }

    #[Test]
    public function itIsNotValidBeforeValidFrom(): void
    {
        $rule = $this->createActiveRule(
            validFrom: new \DateTimeImmutable('2024-06-01'),
        );

        self::assertFalse($rule->isValidAt(new \DateTimeImmutable('2024-01-01')));
    }

    #[Test]
    public function itIsNotValidAfterValidTo(): void
    {
        $rule = $this->createActiveRule(
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validTo: new \DateTimeImmutable('2024-06-30'),
        );

        self::assertFalse($rule->isValidAt(new \DateTimeImmutable('2024-12-01')));
    }

    #[Test]
    public function itIsNotValidWhenInactive(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->jurisdiction,
            category: $this->category,
            rate: $this->rate,
            name: 'Inactive',
            isActive: false,
        );

        self::assertFalse($rule->isValidAt(new \DateTimeImmutable()));
    }

    #[Test]
    public function itIsValidWhenNoValidToSet(): void
    {
        $rule = $this->createActiveRule(validFrom: new \DateTimeImmutable('2020-01-01'));

        self::assertTrue($rule->isValidAt(new \DateTimeImmutable('2030-01-01')));
    }

    // -----------------------------------------------------------------------
    // calculateTax()
    // -----------------------------------------------------------------------

    #[Test]
    public function itCalculatesTaxAmount(): void
    {
        $rule = $this->createActiveRule(); // 19%
        // 100 EUR = 10000 cents => 19% = 1900 cents
        $taxAmount = $rule->calculateTax(10000);

        self::assertSame(1900, $taxAmount);
    }

    #[Test]
    public function itCalculatesZeroTaxForZeroRate(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->jurisdiction,
            category: $this->category,
            rate: TaxRate::zero(),
            name: 'Zero Rate',
        );

        self::assertSame(0, $rule->calculateTax(10000));
    }

    // -----------------------------------------------------------------------
    // appliesTo()
    // -----------------------------------------------------------------------

    #[Test]
    public function itAppliesToMatchingJurisdictionAndCategory(): void
    {
        $rule = $this->createActiveRule();

        self::assertTrue($rule->appliesTo($this->jurisdiction, $this->category));
    }

    #[Test]
    public function itDoesNotApplyToNonMatchingJurisdiction(): void
    {
        $rule = $this->createActiveRule();
        $frJurisdiction = TaxJurisdiction::fromCountryCode('FR');

        self::assertFalse($rule->appliesTo($frJurisdiction, $this->category));
    }

    #[Test]
    public function itDoesNotApplyToNonMatchingCategory(): void
    {
        $rule = $this->createActiveRule();

        self::assertFalse($rule->appliesTo($this->jurisdiction, TaxCategory::REDUCED));
    }

    // -----------------------------------------------------------------------
    // Reverse charge (EU)
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesReverseChargeRuleForEuJurisdiction(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('FR'),
            category: TaxCategory::STANDARD,
            rate: $this->rate,
            name: 'FR Reverse Charge',
            isReverseCharge: true,
        );

        self::assertTrue($rule->isReverseCharge());
    }
}
