<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\UpdateTaxRate;
use App\Tax\Application\Command\UpdateTaxRateHandler;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateTaxRateHandler::class)]
final class UpdateTaxRateHandlerTest extends TestCase
{
    private TaxRuleRepositoryInterface $repository;
    private UpdateTaxRateHandler $handler;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $this->handler = new UpdateTaxRateHandler($this->repository);
    }

    // -------
    // Success path
    // -------

    #[Test]
    public function itUpdatesRateOnActiveTaxRule(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $id = TaxRuleId::generate();
        $taxRule = $this->buildActiveTaxRule($id, $tenantId, 19.0);

        $this->repository->method('findById')->with($id)->willReturn($taxRule);
        $this->repository->expects(self::once())->method('save')->with($taxRule);

        ($this->handler)(new UpdateTaxRate(id: $id, tenantId: $tenantId, ratePercentage: 21.0));

        self::assertSame(21.0, $taxRule->rate()->percentage());
    }

    #[Test]
    public function itAcceptsZeroRate(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $id = TaxRuleId::generate();
        $taxRule = $this->buildActiveTaxRule($id, $tenantId, 7.0);

        $this->repository->method('findById')->willReturn($taxRule);
        $this->repository->expects(self::once())->method('save');

        ($this->handler)(new UpdateTaxRate(id: $id, tenantId: $tenantId, ratePercentage: 0.0));

        self::assertTrue($taxRule->rate()->isZero());
    }

    // -------
    // Not found
    // -------

    #[Test]
    public function itThrowsWhenTaxRuleNotFound(): void
    {
        $id = TaxRuleId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)(new UpdateTaxRate(id: $id, tenantId: $tenantId, ratePercentage: 15.0));
    }

    // -------
    // Tenant isolation
    // -------

    #[Test]
    public function itThrowsWhenTaxRuleBelongsToAnotherTenant(): void
    {
        $id = TaxRuleId::generate();
        $ownerTenant = TenantId::fromString('00000000-0000-4000-8000-000000000002');
        $callerTenant = TenantId::fromString(self::TENANT_ID);

        $taxRule = $this->buildActiveTaxRule($id, $ownerTenant, 19.0);
        $this->repository->method('findById')->willReturn($taxRule);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not belong to this tenant');

        ($this->handler)(new UpdateTaxRate(id: $id, tenantId: $callerTenant, ratePercentage: 15.0));
    }

    // -------
    // Business rule: cannot update rate on inactive rule
    // -------

    #[Test]
    public function itThrowsWhenTaxRuleIsInactive(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $id = TaxRuleId::generate();
        $taxRule = $this->buildInactiveTaxRule($id, $tenantId, 19.0);

        $this->repository->method('findById')->willReturn($taxRule);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot change tax rate on inactive rule');

        ($this->handler)(new UpdateTaxRate(id: $id, tenantId: $tenantId, ratePercentage: 15.0));
    }

    // -------
    // Helpers
    // -------

    private function buildActiveTaxRule(TaxRuleId $id, TenantId $tenantId, float $rate): TaxRule
    {
        return TaxRule::reconstituteFromPersistence(
            id: $id,
            tenantId: $tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('DE'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage($rate),
            name: 'German Standard VAT',
            description: null,
            priority: 0,
            isActive: true,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validTo: null,
            isReverseCharge: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    private function buildInactiveTaxRule(TaxRuleId $id, TenantId $tenantId, float $rate): TaxRule
    {
        return TaxRule::reconstituteFromPersistence(
            id: $id,
            tenantId: $tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('DE'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage($rate),
            name: 'German Standard VAT',
            description: null,
            priority: 0,
            isActive: false,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validTo: null,
            isReverseCharge: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
