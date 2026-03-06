<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\DeleteTaxRule;
use App\Tax\Application\Command\DeleteTaxRuleHandler;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteTaxRuleHandler::class)]
final class DeleteTaxRuleHandlerTest extends TestCase
{
    private TaxRuleRepositoryInterface $repository;
    private DeleteTaxRuleHandler $handler;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $this->handler = new DeleteTaxRuleHandler($this->repository);
    }

    // -------
    // Success path
    // -------

    #[Test]
    public function itDeletesExistingTaxRule(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $id = TaxRuleId::generate();
        $taxRule = $this->buildTaxRule($id, $tenantId);

        $this->repository->method('findById')->with($id)->willReturn($taxRule);
        $this->repository->expects(self::once())->method('delete')->with($id);

        ($this->handler)(new DeleteTaxRule(id: $id, tenantId: $tenantId));
    }

    #[Test]
    public function itDoesNotCallSaveOnDelete(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $id = TaxRuleId::generate();
        $taxRule = $this->buildTaxRule($id, $tenantId);

        $this->repository->method('findById')->willReturn($taxRule);
        $this->repository->expects(self::never())->method('save');

        ($this->handler)(new DeleteTaxRule(id: $id, tenantId: $tenantId));
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

        ($this->handler)(new DeleteTaxRule(id: $id, tenantId: $tenantId));
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

        $taxRule = $this->buildTaxRule($id, $ownerTenant);
        $this->repository->method('findById')->willReturn($taxRule);
        $this->repository->expects(self::never())->method('delete');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not belong to this tenant');

        ($this->handler)(new DeleteTaxRule(id: $id, tenantId: $callerTenant));
    }

    // -------
    // Helpers
    // -------

    private function buildTaxRule(TaxRuleId $id, TenantId $tenantId): TaxRule
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
