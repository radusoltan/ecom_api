<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\ActivateTaxRule;
use App\Tax\Application\Command\ActivateTaxRuleHandler;
use App\Tax\Application\Command\CreateTaxRule;
use App\Tax\Application\Command\CreateTaxRuleHandler;
use App\Tax\Application\Command\DeactivateTaxRule;
use App\Tax\Application\Command\DeactivateTaxRuleHandler;
use App\Tax\Application\Command\DeleteTaxRule;
use App\Tax\Application\Command\DeleteTaxRuleHandler;
use App\Tax\Application\Command\UpdateTaxRate;
use App\Tax\Application\Command\UpdateTaxRateHandler;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateTaxRuleHandler::class)]
#[CoversClass(ActivateTaxRuleHandler::class)]
#[CoversClass(DeactivateTaxRuleHandler::class)]
#[CoversClass(DeleteTaxRuleHandler::class)]
#[CoversClass(UpdateTaxRateHandler::class)]
#[CoversClass(UpdateTaxRuleHandler::class)]
final class TaxRuleCommandHandlersTest extends TestCase
{
    private TaxRuleRepositoryInterface&MockObject $repository;
    private TenantId $tenantId;
    private TaxRuleId $ruleId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->ruleId = TaxRuleId::generate();
    }

    private function createActiveRule(): TaxRule
    {
        $rule = TaxRule::create(
            id: $this->ruleId,
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('DE'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'Standard VAT DE',
        );
        $rule->popEvents();

        return $rule;
    }

    private function createInactiveRule(): TaxRule
    {
        $rule = TaxRule::create(
            id: $this->ruleId,
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('DE'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'Inactive Rule',
            isActive: false,
        );
        $rule->popEvents();

        return $rule;
    }

    // -----------------------------------------------------------------------
    // CreateTaxRuleHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function createHandlerSavesNewRuleForCountryOnly(): void
    {
        $handler = new CreateTaxRuleHandler($this->repository);
        $command = new CreateTaxRule(
            id: $this->ruleId,
            tenantId: $this->tenantId,
            countryCode: 'DE',
            regionCode: null,
            category: 'standard',
            ratePercentage: 19.0,
            name: 'Standard VAT DE',
        );

        $this->repository->expects(self::once())->method('save')
            ->with(self::isInstanceOf(TaxRule::class));

        $handler($command);
    }

    #[Test]
    public function createHandlerSavesNewRuleWithRegionCode(): void
    {
        $handler = new CreateTaxRuleHandler($this->repository);
        $command = new CreateTaxRule(
            id: $this->ruleId,
            tenantId: $this->tenantId,
            countryCode: 'US',
            regionCode: 'CA',
            category: 'standard',
            ratePercentage: 7.25,
            name: 'California Sales Tax',
        );

        $this->repository->expects(self::once())->method('save');
        $handler($command);
    }

    #[Test]
    public function createHandlerSavesReducedRateRule(): void
    {
        $handler = new CreateTaxRuleHandler($this->repository);
        $command = new CreateTaxRule(
            id: $this->ruleId,
            tenantId: $this->tenantId,
            countryCode: 'DE',
            regionCode: null,
            category: 'reduced',
            ratePercentage: 7.0,
            name: 'Reduced VAT DE',
            description: 'For books and food',
            priority: 5,
        );

        $this->repository->expects(self::once())->method('save');
        $handler($command);
    }

    #[Test]
    public function createHandlerSavesZeroRateRule(): void
    {
        $handler = new CreateTaxRuleHandler($this->repository);
        $command = new CreateTaxRule(
            id: $this->ruleId,
            tenantId: $this->tenantId,
            countryCode: 'DE',
            regionCode: null,
            category: 'zero',
            ratePercentage: 0.0,
            name: 'Zero Rate',
        );

        $this->repository->expects(self::once())->method('save');
        $handler($command);
    }

    #[Test]
    public function createHandlerSavesExemptRule(): void
    {
        $handler = new CreateTaxRuleHandler($this->repository);
        $command = new CreateTaxRule(
            id: $this->ruleId,
            tenantId: $this->tenantId,
            countryCode: 'DE',
            regionCode: null,
            category: 'exempt',
            ratePercentage: 0.0,
            name: 'Exempt',
        );

        $this->repository->expects(self::once())->method('save');
        $handler($command);
    }

    // -----------------------------------------------------------------------
    // ActivateTaxRuleHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function activateHandlerActivatesRule(): void
    {
        $handler = new ActivateTaxRuleHandler($this->repository);
        $inactiveRule = $this->createInactiveRule();
        $command = new ActivateTaxRule($this->ruleId, $this->tenantId);

        $this->repository->method('findById')->willReturn($inactiveRule);
        $this->repository->expects(self::once())->method('save');

        $handler($command);

        self::assertTrue($inactiveRule->isActive());
    }

    #[Test]
    public function activateHandlerThrowsWhenRuleNotFound(): void
    {
        $handler = new ActivateTaxRuleHandler($this->repository);
        $command = new ActivateTaxRule($this->ruleId, $this->tenantId);

        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        $handler($command);
    }

    #[Test]
    public function activateHandlerThrowsWhenTenantMismatch(): void
    {
        $handler = new ActivateTaxRuleHandler($this->repository);
        $inactiveRule = $this->createInactiveRule();

        $otherTenantId = TenantId::fromString('00000000-0000-4000-8000-000000000099');
        $command = new ActivateTaxRule($this->ruleId, $otherTenantId);

        $this->repository->method('findById')->willReturn($inactiveRule);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not belong to this tenant');

        $handler($command);
    }

    // -----------------------------------------------------------------------
    // DeactivateTaxRuleHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function deactivateHandlerDeactivatesRule(): void
    {
        $handler = new DeactivateTaxRuleHandler($this->repository);
        $activeRule = $this->createActiveRule();
        $command = new DeactivateTaxRule($this->ruleId, $this->tenantId);

        $this->repository->method('findById')->willReturn($activeRule);
        $this->repository->expects(self::once())->method('save');

        $handler($command);

        self::assertFalse($activeRule->isActive());
    }

    #[Test]
    public function deactivateHandlerThrowsWhenRuleNotFound(): void
    {
        $handler = new DeactivateTaxRuleHandler($this->repository);
        $command = new DeactivateTaxRule($this->ruleId, $this->tenantId);

        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $handler($command);
    }

    #[Test]
    public function deactivateHandlerThrowsWhenTenantMismatch(): void
    {
        $handler = new DeactivateTaxRuleHandler($this->repository);
        $activeRule = $this->createActiveRule();
        $otherTenantId = TenantId::fromString('00000000-0000-4000-8000-000000000099');
        $command = new DeactivateTaxRule($this->ruleId, $otherTenantId);

        $this->repository->method('findById')->willReturn($activeRule);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not belong to this tenant');

        $handler($command);
    }

    // -----------------------------------------------------------------------
    // DeleteTaxRuleHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function deleteHandlerDeletesRule(): void
    {
        $handler = new DeleteTaxRuleHandler($this->repository);
        $rule = $this->createActiveRule();
        $command = new DeleteTaxRule($this->ruleId, $this->tenantId);

        $this->repository->method('findById')->willReturn($rule);
        $this->repository->expects(self::once())->method('delete')->with($this->ruleId);

        $handler($command);
    }

    #[Test]
    public function deleteHandlerThrowsWhenRuleNotFound(): void
    {
        $handler = new DeleteTaxRuleHandler($this->repository);
        $command = new DeleteTaxRule($this->ruleId, $this->tenantId);

        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $handler($command);
    }

    #[Test]
    public function deleteHandlerThrowsWhenTenantMismatch(): void
    {
        $handler = new DeleteTaxRuleHandler($this->repository);
        $rule = $this->createActiveRule();
        $otherTenant = TenantId::fromString('00000000-0000-4000-8000-000000000099');
        $command = new DeleteTaxRule($this->ruleId, $otherTenant);

        $this->repository->method('findById')->willReturn($rule);

        $this->expectException(\DomainException::class);
        $handler($command);
    }

    // -----------------------------------------------------------------------
    // UpdateTaxRateHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function updateRateHandlerUpdatesRate(): void
    {
        $handler = new UpdateTaxRateHandler($this->repository);
        $rule = $this->createActiveRule();
        $command = new UpdateTaxRate($this->ruleId, $this->tenantId, 20.0);

        $this->repository->method('findById')->willReturn($rule);
        $this->repository->expects(self::once())->method('save');

        $handler($command);

        self::assertEqualsWithDelta(20.0, $rule->rate()->percentage(), 0.001);
    }

    #[Test]
    public function updateRateHandlerThrowsWhenRuleNotFound(): void
    {
        $handler = new UpdateTaxRateHandler($this->repository);
        $command = new UpdateTaxRate($this->ruleId, $this->tenantId, 20.0);

        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $handler($command);
    }

    #[Test]
    public function updateRateHandlerThrowsWhenTenantMismatch(): void
    {
        $handler = new UpdateTaxRateHandler($this->repository);
        $rule = $this->createActiveRule();
        $otherTenant = TenantId::fromString('00000000-0000-4000-8000-000000000099');
        $command = new UpdateTaxRate($this->ruleId, $otherTenant, 20.0);

        $this->repository->method('findById')->willReturn($rule);

        $this->expectException(\DomainException::class);
        $handler($command);
    }

    // -----------------------------------------------------------------------
    // UpdateTaxRuleHandler
    // -----------------------------------------------------------------------

    #[Test]
    public function updateRuleHandlerUpdatesRule(): void
    {
        $handler = new UpdateTaxRuleHandler($this->repository);
        $rule = $this->createActiveRule();
        $command = new UpdateTaxRule($this->ruleId, $this->tenantId, 'New Name', 21.0);

        $this->repository->method('findById')->willReturn($rule);
        $this->repository->expects(self::once())->method('save');

        $handler($command);
    }

    #[Test]
    public function updateRuleHandlerThrowsWhenNotFound(): void
    {
        $handler = new UpdateTaxRuleHandler($this->repository);
        $command = new UpdateTaxRule($this->ruleId, $this->tenantId, 'Name', 19.0);

        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $handler($command);
    }

    #[Test]
    public function updateRuleHandlerThrowsWhenTenantMismatch(): void
    {
        $handler = new UpdateTaxRuleHandler($this->repository);
        $rule = $this->createActiveRule();
        $otherTenant = TenantId::fromString('00000000-0000-4000-8000-000000000099');
        $command = new UpdateTaxRule($this->ruleId, $otherTenant, 'Name', 19.0);

        $this->repository->method('findById')->willReturn($rule);

        $this->expectException(\DomainException::class);
        $handler($command);
    }
}
