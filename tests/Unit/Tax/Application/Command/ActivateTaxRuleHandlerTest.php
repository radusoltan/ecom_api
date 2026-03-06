<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\ActivateTaxRule;
use App\Tax\Application\Command\ActivateTaxRuleHandler;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivateTaxRuleHandler::class)]
final class ActivateTaxRuleHandlerTest extends TestCase
{
    private TaxRuleRepositoryInterface $repository;
    private ActivateTaxRuleHandler $handler;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $this->handler = new ActivateTaxRuleHandler($this->repository);
    }

    // -------
    // Success path
    // -------

    #[Test]
    public function itActivatesAnInactiveTaxRule(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $id = TaxRuleId::generate();
        $taxRule = $this->buildInactiveTaxRule($id, $tenantId);

        $this->repository->method('findById')->with($id)->willReturn($taxRule);
        $this->repository->expects(self::once())->method('save')->with($taxRule);

        $command = new ActivateTaxRule(id: $id, tenantId: $tenantId);
        ($this->handler)($command);

        self::assertTrue($taxRule->isActive());
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

        ($this->handler)(new ActivateTaxRule(id: $id, tenantId: $tenantId));
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

        $taxRule = $this->buildInactiveTaxRule($id, $ownerTenant);
        $this->repository->method('findById')->willReturn($taxRule);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not belong to this tenant');

        ($this->handler)(new ActivateTaxRule(id: $id, tenantId: $callerTenant));
    }

    // -------
    // Business rule: already active
    // -------

    #[Test]
    public function itThrowsWhenTaxRuleIsAlreadyActive(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $id = TaxRuleId::generate();
        $taxRule = $this->buildActiveTaxRule($id, $tenantId);

        $this->repository->method('findById')->willReturn($taxRule);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already active');

        ($this->handler)(new ActivateTaxRule(id: $id, tenantId: $tenantId));
    }

    // -------
    // Helpers
    // -------

    private function buildInactiveTaxRule(TaxRuleId $id, TenantId $tenantId): TaxRule
    {
        return TaxRule::reconstituteFromPersistence(
            id: $id,
            tenantId: $tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('DE'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
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

    private function buildActiveTaxRule(TaxRuleId $id, TenantId $tenantId): TaxRule
    {
        return TaxRule::reconstituteFromPersistence(
            id: $id,
            tenantId: $tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('DE'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
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
}
