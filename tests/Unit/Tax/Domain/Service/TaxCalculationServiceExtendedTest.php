<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\Service;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use App\Tax\Domain\Service\TaxCalculationService;
use App\Tax\Domain\ValueObject\TaxJurisdiction as TaxJurisdictionVO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxCalculationService::class)]
final class TaxCalculationServiceExtendedTest extends TestCase
{
    private TaxRuleRepositoryInterface&MockObject $repository;
    private TaxCalculationService $service;
    private TenantId $tenantId;
    private TaxJurisdictionVO $jurisdiction;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $this->service = new TaxCalculationService($this->repository);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->jurisdiction = TaxJurisdictionVO::fromCountry('DE');
    }

    private function createTaxRule(float $ratePercent): TaxRule
    {
        return TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('DE'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage($ratePercent),
            name: 'Test Rule',
        );
    }

    // -----------------------------------------------------------------------
    // calculateTax() — no rule found
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsZeroTaxWhenNoRuleFound(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findByJurisdiction')
            ->willReturn(null);

        $result = $this->service->calculateTax(10000, $this->jurisdiction, $this->tenantId);

        self::assertSame(0, $result['taxAmount']);
        self::assertSame(0.0, $result['taxRate']);
        self::assertSame('DE', $result['jurisdiction']);
        self::assertNull($result['taxRuleId']);
        self::assertNull($result['taxRuleName']);
    }

    // -----------------------------------------------------------------------
    // calculateTax() — with rule found
    // -----------------------------------------------------------------------

    #[Test]
    public function itCalculatesTaxWithFoundRule(): void
    {
        $rule = $this->createTaxRule(19.0);
        $rule->popEvents();

        $this->repository
            ->expects(self::once())
            ->method('findByJurisdiction')
            ->willReturn($rule);

        $result = $this->service->calculateTax(10000, $this->jurisdiction, $this->tenantId);

        self::assertSame(1900, $result['taxAmount']); // 19% of 100 EUR
        self::assertSame(19.0, $result['taxRate']);
        self::assertSame('DE', $result['jurisdiction']);
        self::assertSame('Test Rule', $result['taxRuleName']);
        self::assertNotNull($result['taxRuleId']);
    }

    #[Test]
    public function itCalculates7PercentTax(): void
    {
        $rule = $this->createTaxRule(7.0);
        $rule->popEvents();

        $this->repository->method('findByJurisdiction')->willReturn($rule);

        $result = $this->service->calculateTax(10000, $this->jurisdiction, $this->tenantId);

        self::assertSame(700, $result['taxAmount']); // 7% of 100 EUR
        self::assertSame(7.0, $result['taxRate']);
    }

    #[Test]
    public function itCalculatesZeroTaxWithZeroRateRule(): void
    {
        $rule = TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('DE'),
            category: TaxCategory::ZERO,
            rate: TaxRate::zero(),
            name: 'Zero Rate',
        );
        $rule->popEvents();

        $this->repository->method('findByJurisdiction')->willReturn($rule);

        $result = $this->service->calculateTax(10000, $this->jurisdiction, $this->tenantId);

        self::assertSame(0, $result['taxAmount']);
        self::assertSame(0.0, $result['taxRate']);
    }

    // -----------------------------------------------------------------------
    // calculateOrderTax()
    // -----------------------------------------------------------------------

    #[Test]
    public function itCalculatesOrderTaxForLineItems(): void
    {
        $rule = $this->createTaxRule(19.0);
        $rule->popEvents();

        $this->repository->method('findByJurisdiction')->willReturn($rule);

        $lineItems = [
            ['amountInCents' => 5000, 'quantity' => 2], // 100 EUR
            ['amountInCents' => 3000, 'quantity' => 1], //  30 EUR
        ];

        $result = $this->service->calculateOrderTax($lineItems, $this->jurisdiction, $this->tenantId);

        self::assertSame(13000, $result['subtotal']); // 130 EUR
        self::assertSame(2470, $result['taxAmount']); // 19% of 130 EUR
        self::assertSame(15470, $result['total']);    // 130 + 24.70 EUR
        self::assertSame(19.0, $result['taxRate']);
    }

    #[Test]
    public function itCalculatesOrderTaxWithNoRuleFound(): void
    {
        $this->repository->method('findByJurisdiction')->willReturn(null);

        $lineItems = [
            ['amountInCents' => 10000, 'quantity' => 1],
        ];

        $result = $this->service->calculateOrderTax($lineItems, $this->jurisdiction, $this->tenantId);

        self::assertSame(10000, $result['subtotal']);
        self::assertSame(0, $result['taxAmount']);
        self::assertSame(10000, $result['total']);
        self::assertSame(0.0, $result['taxRate']);
    }

    #[Test]
    public function itCalculatesOrderTaxForMultipleQuantities(): void
    {
        $rule = $this->createTaxRule(20.0);
        $rule->popEvents();

        $this->repository->method('findByJurisdiction')->willReturn($rule);

        $lineItems = [
            ['amountInCents' => 1000, 'quantity' => 3], // 30 EUR
        ];

        $result = $this->service->calculateOrderTax($lineItems, $this->jurisdiction, $this->tenantId);

        self::assertSame(3000, $result['subtotal']);
        self::assertSame(600, $result['taxAmount']); // 20% of 30 EUR
        self::assertSame(3600, $result['total']);
    }

    // -----------------------------------------------------------------------
    // isTaxable()
    // -----------------------------------------------------------------------

    #[Test]
    public function itIsTaxableWhenActiveRuleExists(): void
    {
        $rule = $this->createTaxRule(19.0);
        $rule->popEvents();

        $this->repository->method('findByJurisdiction')->willReturn($rule);

        self::assertTrue($this->service->isTaxable($this->jurisdiction, $this->tenantId));
    }

    #[Test]
    public function itIsNotTaxableWhenNoRuleFound(): void
    {
        $this->repository->method('findByJurisdiction')->willReturn(null);

        self::assertFalse($this->service->isTaxable($this->jurisdiction, $this->tenantId));
    }

    // -----------------------------------------------------------------------
    // getTaxRate()
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsTaxRateWhenRuleFound(): void
    {
        $rule = $this->createTaxRule(19.0);
        $rule->popEvents();

        $this->repository->method('findByJurisdiction')->willReturn($rule);

        self::assertSame(19.0, $this->service->getTaxRate($this->jurisdiction, $this->tenantId));
    }

    #[Test]
    public function itReturnsZeroRateWhenNoRuleFound(): void
    {
        $this->repository->method('findByJurisdiction')->willReturn(null);

        self::assertSame(0.0, $this->service->getTaxRate($this->jurisdiction, $this->tenantId));
    }
}
