<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\UpdateTaxRule;
use App\Tax\Application\Command\UpdateTaxRuleHandler;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateTaxRuleHandler::class)]
final class UpdateTaxRuleHandlerTest extends TestCase
{
    private TaxRuleRepositoryInterface $repository;
    private UpdateTaxRuleHandler $handler;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $this->handler = new UpdateTaxRuleHandler($this->repository);
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

        $this->repository->method('findById')->willReturn($taxRule);
        $this->repository->expects(self::once())->method('save')->with($taxRule);

        ($this->handler)(new UpdateTaxRule(id: $id, tenantId: $tenantId, name: 'Updated VAT', ratePercentage: 21.0));

        self::assertSame(21.0, $taxRule->rate()->percentage());
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

        ($this->handler)(new UpdateTaxRule(id: $id, tenantId: $tenantId, name: 'X', ratePercentage: 10.0));
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

        ($this->handler)(new UpdateTaxRule(id: $id, tenantId: $callerTenant, name: 'X', ratePercentage: 10.0));
    }

    // -------
    // Helpers
    // -------

    private function buildActiveTaxRule(TaxRuleId $id, TenantId $tenantId, float $rate): TaxRule
    {
        return TaxRule::reconstituteFromPersistence(
            id: $id,
            tenantId: $tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('IT'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage($rate),
            name: 'Italian Standard VAT',
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
}
