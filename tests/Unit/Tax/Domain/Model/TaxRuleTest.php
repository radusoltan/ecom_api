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
use PHPUnit\Framework\TestCase;

/**
 * Tests for TaxRule aggregate root.
 *
 * Verifies creation validation, state transitions, and domain events.
 */
final class TaxRuleTest extends TestCase
{
    private TaxRuleId $id;
    private TenantId $tenantId;
    private TaxJurisdiction $deJurisdiction;

    protected function setUp(): void
    {
        $this->id = TaxRuleId::generate();
        $this->tenantId = TenantId::generate();
        $this->deJurisdiction = TaxJurisdiction::fromCountryCode('DE');
    }

    // ==========================================
    // TaxRule::create()
    // ==========================================

    public function testCreateValidTaxRule(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'German Standard VAT',
            description: '19% standard VAT for Germany',
            priority: 10,
        );

        $this->assertSame($this->id, $rule->id());
        $this->assertSame($this->tenantId, $rule->tenantId());
        $this->assertSame('DE', $rule->jurisdiction()->countryCode());
        $this->assertSame(TaxCategory::STANDARD, $rule->category());
        $this->assertTrue($rule->rate()->equals(TaxRate::fromPercentage(19.0)));
        $this->assertSame('German Standard VAT', $rule->name());
        $this->assertSame('19% standard VAT for Germany', $rule->description());
        $this->assertSame(10, $rule->priority());
        $this->assertTrue($rule->isActive());
        $this->assertFalse($rule->isReverseCharge());
    }

    public function testCreateRecordsTaxRuleCreatedEvent(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'German VAT',
        );

        $events = $rule->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TaxRuleCreated::class, $events[0]);
    }

    public function testCreateFailsWithEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name cannot be empty');

        TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: '',
        );
    }

    public function testCreateFailsWithWhitespaceOnlyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name cannot be empty');

        TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: '   ',
        );
    }

    public function testCreateFailsWithNegativePriority(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be >= 0');

        TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'Test',
            priority: -1,
        );
    }

    public function testCreateFailsWithInvalidDateRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('validFrom');

        TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'Test',
            validFrom: new \DateTimeImmutable('2026-12-31'),
            validTo: new \DateTimeImmutable('2026-01-01'),
        );
    }

    public function testCreateFailsWithReverseChargeOnNonEuJurisdiction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('only applicable to EU jurisdictions');

        TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('US'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(10.0),
            name: 'Invalid reverse charge',
            isReverseCharge: true,
        );
    }

    public function testCreateFailsWithReverseChargeOnExemptCategory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Reverse charge cannot be applied to exempt');

        TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::EXEMPT,
            rate: TaxRate::zero(),
            name: 'Invalid exempt reverse charge',
            isReverseCharge: true,
        );
    }

    public function testCreateWithReverseChargeOnEuJurisdiction(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::zero(),
            name: 'EU B2B Reverse Charge',
            isReverseCharge: true,
        );

        $this->assertTrue($rule->isReverseCharge());
    }

    // ==========================================
    // activate() / deactivate()
    // ==========================================

    public function testDeactivateSuccessfully(): void
    {
        $rule = $this->createActiveRule();
        $rule->popEvents(); // clear creation event

        $rule->deactivate();

        $this->assertFalse($rule->isActive());
        $events = $rule->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TaxRuleDeactivated::class, $events[0]);
    }

    public function testDeactivateFailsWhenAlreadyInactive(): void
    {
        $rule = $this->createActiveRule();
        $rule->deactivate();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already inactive');

        $rule->deactivate();
    }

    public function testActivateSuccessfully(): void
    {
        $rule = $this->createActiveRule();
        $rule->deactivate();
        $rule->popEvents(); // clear prior events

        $rule->activate();

        $this->assertTrue($rule->isActive());
        $events = $rule->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TaxRuleActivated::class, $events[0]);
    }

    public function testActivateFailsWhenAlreadyActive(): void
    {
        $rule = $this->createActiveRule();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already active');

        $rule->activate();
    }

    // ==========================================
    // updateRate()
    // ==========================================

    public function testUpdateRateSuccessfully(): void
    {
        $rule = $this->createActiveRule();
        $rule->popEvents();

        $newRate = TaxRate::fromPercentage(16.0);
        $rule->updateRate($newRate);

        $this->assertTrue($rule->rate()->equals($newRate));
        $events = $rule->popEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TaxRateChanged::class, $events[0]);
    }

    public function testUpdateRateNoOpWhenSameRate(): void
    {
        $rule = $this->createActiveRule();
        $rule->popEvents();

        $sameRate = TaxRate::fromPercentage(19.0);
        $rule->updateRate($sameRate);

        $events = $rule->popEvents();
        $this->assertCount(0, $events);
    }

    public function testUpdateRateFailsOnInactiveRule(): void
    {
        $rule = $this->createActiveRule();
        $rule->deactivate();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot change tax rate on inactive rule');

        $rule->updateRate(TaxRate::fromPercentage(16.0));
    }

    // ==========================================
    // updateValidity()
    // ==========================================

    public function testUpdateValidityWithValidDateRange(): void
    {
        $rule = $this->createActiveRule();

        $from = new \DateTimeImmutable('2026-01-01');
        $to = new \DateTimeImmutable('2026-12-31');
        $rule->updateValidity($from, $to);

        $this->assertEquals($from, $rule->validFrom());
        $this->assertEquals($to, $rule->validTo());
    }

    public function testUpdateValidityFailsWithInvalidRange(): void
    {
        $rule = $this->createActiveRule();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('validFrom');

        $rule->updateValidity(
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-01-01'),
        );
    }

    public function testUpdateValidityWithOpenEndDate(): void
    {
        $rule = $this->createActiveRule();

        $from = new \DateTimeImmutable('2026-01-01');
        $rule->updateValidity($from, null);

        $this->assertEquals($from, $rule->validFrom());
        $this->assertNull($rule->validTo());
    }

    // ==========================================
    // isValidAt()
    // ==========================================

    public function testIsValidAtReturnsFalseForInactiveRule(): void
    {
        $rule = $this->createActiveRule();
        $rule->deactivate();

        $this->assertFalse($rule->isValidAt(new \DateTimeImmutable()));
    }

    public function testIsValidAtReturnsFalseBeforeValidFrom(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'Future rule',
            validFrom: new \DateTimeImmutable('2027-01-01'),
        );

        $this->assertFalse($rule->isValidAt(new \DateTimeImmutable('2026-06-15')));
    }

    public function testIsValidAtReturnsFalseAfterValidTo(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(16.0),
            name: 'Expired rule',
            validFrom: new \DateTimeImmutable('2020-07-01'),
            validTo: new \DateTimeImmutable('2020-12-31'),
        );

        $this->assertFalse($rule->isValidAt(new \DateTimeImmutable('2021-01-15')));
    }

    public function testIsValidAtReturnsTrueWithinDateRange(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(16.0),
            name: 'COVID relief',
            validFrom: new \DateTimeImmutable('2020-07-01'),
            validTo: new \DateTimeImmutable('2020-12-31'),
        );

        $this->assertTrue($rule->isValidAt(new \DateTimeImmutable('2020-09-15')));
    }

    // ==========================================
    // appliesTo()
    // ==========================================

    public function testAppliesToMatchingJurisdictionAndCategory(): void
    {
        $rule = $this->createActiveRule();

        $this->assertTrue($rule->appliesTo($this->deJurisdiction, TaxCategory::STANDARD));
    }

    public function testAppliesToReturnsFalseForDifferentJurisdiction(): void
    {
        $rule = $this->createActiveRule();

        $this->assertFalse($rule->appliesTo(
            TaxJurisdiction::fromCountryCode('FR'),
            TaxCategory::STANDARD,
        ));
    }

    public function testAppliesToReturnsFalseForDifferentCategory(): void
    {
        $rule = $this->createActiveRule();

        $this->assertFalse($rule->appliesTo($this->deJurisdiction, TaxCategory::REDUCED));
    }

    // ==========================================
    // calculateTax()
    // ==========================================

    public function testCalculateTaxDelegatesToRate(): void
    {
        $rule = $this->createActiveRule(); // 19%

        $this->assertSame(190, $rule->calculateTax(1000));
    }

    public function testCalculateTaxWithZeroRate(): void
    {
        $rule = TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::ZERO,
            rate: TaxRate::zero(),
            name: 'Zero rate',
        );

        $this->assertSame(0, $rule->calculateTax(1000));
    }

    // ==========================================
    // reconstituteFromPersistence()
    // ==========================================

    public function testReconstituteFromPersistence(): void
    {
        $now = new \DateTimeImmutable();
        $rule = TaxRule::reconstituteFromPersistence(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'Reconstituted',
            description: 'Test',
            priority: 5,
            isActive: false,
            validFrom: $now,
            validTo: null,
            isReverseCharge: false,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->assertSame('Reconstituted', $rule->name());
        $this->assertSame(5, $rule->priority());
        $this->assertFalse($rule->isActive());
        // Reconstitute does NOT record events
        $this->assertCount(0, $rule->popEvents());
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createActiveRule(): TaxRule
    {
        return TaxRule::create(
            id: $this->id,
            tenantId: $this->tenantId,
            jurisdiction: $this->deJurisdiction,
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'German Standard VAT',
        );
    }
}
