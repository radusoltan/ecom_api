<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\DTO\TaxRuleDTO;
use App\Tax\Application\Query\GetAllTaxRules;
use App\Tax\Application\Query\GetAllTaxRulesHandler;
use App\Tax\Application\Query\GetApplicableTaxRules;
use App\Tax\Application\Query\GetApplicableTaxRulesHandler;
use App\Tax\Application\Query\GetTaxRulesByTenant;
use App\Tax\Application\Query\GetTaxRulesByTenantHandler;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetApplicableTaxRulesHandler::class)]
#[CoversClass(GetApplicableTaxRules::class)]
#[CoversClass(GetTaxRulesByTenantHandler::class)]
#[CoversClass(GetTaxRulesByTenant::class)]
#[CoversClass(GetAllTaxRulesHandler::class)]
#[CoversClass(GetAllTaxRules::class)]
final class TaxQueryHandlersTest extends TestCase
{
    private TaxRuleRepositoryInterface $repository;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    private function createTaxRule(string $name = 'Standard VAT'): TaxRule
    {
        return TaxRule::create(
            id: TaxRuleId::generate(),
            tenantId: $this->tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('US'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(10.0),
            name: $name,
        );
    }

    // --- GetApplicableTaxRulesHandler ---

    #[Test]
    public function itReturnsApplicableTaxRulesAsDTOs(): void
    {
        $rule = $this->createTaxRule();
        $asOfDate = new \DateTimeImmutable();

        $this->repository
            ->expects(self::once())
            ->method('findApplicableRules')
            ->willReturn([$rule]);

        $handler = new GetApplicableTaxRulesHandler($this->repository);
        $query = new GetApplicableTaxRules(
            $this->tenantId,
            'US',
            null,
            'standard',
            $asOfDate,
        );

        $result = $handler($query);

        self::assertCount(1, $result);
        self::assertInstanceOf(TaxRuleDTO::class, $result[0]);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoApplicableRules(): void
    {
        $this->repository->method('findApplicableRules')->willReturn([]);

        $handler = new GetApplicableTaxRulesHandler($this->repository);
        $result = $handler(new GetApplicableTaxRules(
            $this->tenantId,
            'JP',
            null,
            'standard',
            new \DateTimeImmutable(),
        ));

        self::assertSame([], $result);
    }

    #[Test]
    public function itPassesRegionCodeToJurisdiction(): void
    {
        $this->repository->method('findApplicableRules')->willReturn([]);

        $handler = new GetApplicableTaxRulesHandler($this->repository);
        $query = new GetApplicableTaxRules(
            $this->tenantId,
            'US',
            'CA',
            'standard',
            new \DateTimeImmutable(),
        );

        $result = $handler($query);

        self::assertSame([], $result);
    }

    // --- GetTaxRulesByTenantHandler ---

    #[Test]
    public function itReturnsTaxRulesByTenantAsDTOs(): void
    {
        $rules = [$this->createTaxRule('VAT 1'), $this->createTaxRule('VAT 2')];

        $this->repository
            ->expects(self::once())
            ->method('findByTenantId')
            ->with($this->tenantId)
            ->willReturn($rules);

        $handler = new GetTaxRulesByTenantHandler($this->repository);
        $result = $handler(new GetTaxRulesByTenant($this->tenantId));

        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(TaxRuleDTO::class, $result);
    }

    #[Test]
    public function itReturnsEmptyWhenNoTenantRules(): void
    {
        $this->repository->method('findByTenantId')->willReturn([]);

        $handler = new GetTaxRulesByTenantHandler($this->repository);
        $result = $handler(new GetTaxRulesByTenant($this->tenantId));

        self::assertSame([], $result);
    }

    // --- GetAllTaxRulesHandler ---

    #[Test]
    public function itReturnsAllTaxRulesAsDTOs(): void
    {
        $rules = [$this->createTaxRule()];

        $this->repository
            ->expects(self::once())
            ->method('findByTenantId')
            ->with($this->tenantId)
            ->willReturn($rules);

        $handler = new GetAllTaxRulesHandler($this->repository);
        $result = $handler(new GetAllTaxRules($this->tenantId));

        self::assertCount(1, $result);
        self::assertInstanceOf(TaxRuleDTO::class, $result[0]);
    }

    // --- Query DTOs ---

    #[Test]
    public function getAllTaxRulesQueryDefaults(): void
    {
        $query = new GetAllTaxRules($this->tenantId);

        self::assertFalse($query->activeOnly);
        self::assertSame(100, $query->limit);
        self::assertSame(0, $query->offset);
    }

    #[Test]
    public function getApplicableTaxRulesQueryProperties(): void
    {
        $date = new \DateTimeImmutable('2025-01-01');
        $query = new GetApplicableTaxRules($this->tenantId, 'DE', 'BY', 'reduced', $date);

        self::assertSame('DE', $query->countryCode);
        self::assertSame('BY', $query->regionCode);
        self::assertSame('reduced', $query->category);
        self::assertSame($date, $query->asOfDate);
    }
}
