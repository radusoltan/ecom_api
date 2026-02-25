<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Command;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Command\CreateTaxRule;
use App\Tax\Application\Command\CreateTaxRuleHandler;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CreateTaxRuleHandler.
 */
final class CreateTaxRuleHandlerTest extends TestCase
{
    private TaxRuleRepositoryInterface $repository;
    private CreateTaxRuleHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TaxRuleRepositoryInterface::class);
        $this->handler = new CreateTaxRuleHandler($this->repository);
    }

    public function testHandleValidCreateCommand(): void
    {
        $command = new CreateTaxRule(
            id: TaxRuleId::generate(),
            tenantId: TenantId::generate(),
            countryCode: 'DE',
            regionCode: null,
            category: 'standard',
            ratePercentage: 19.0,
            name: 'German Standard VAT',
            description: 'Standard 19% VAT for Germany',
        );

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (TaxRule $rule) use ($command): bool {
                return $rule->id()->equals($command->id)
                    && $rule->name() === 'German Standard VAT'
                    && $rule->jurisdiction()->countryCode() === 'DE'
                    && $rule->rate()->percentage() === 19.0
                    && $rule->isActive();
            }));

        ($this->handler)($command);
    }

    public function testHandleCreatesJurisdictionWithRegion(): void
    {
        $command = new CreateTaxRule(
            id: TaxRuleId::generate(),
            tenantId: TenantId::generate(),
            countryCode: 'US',
            regionCode: 'CA',
            category: 'standard',
            ratePercentage: 7.25,
            name: 'California Sales Tax',
        );

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (TaxRule $rule): bool {
                return $rule->jurisdiction()->countryCode() === 'US'
                    && $rule->jurisdiction()->regionCode() === 'CA';
            }));

        ($this->handler)($command);
    }

    public function testHandleWithReducedCategory(): void
    {
        $command = new CreateTaxRule(
            id: TaxRuleId::generate(),
            tenantId: TenantId::generate(),
            countryCode: 'DE',
            regionCode: null,
            category: 'reduced',
            ratePercentage: 7.0,
            name: 'German Reduced VAT',
        );

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (TaxRule $rule): bool {
                return $rule->rate()->percentage() === 7.0;
            }));

        ($this->handler)($command);
    }

    public function testHandleWithReverseCharge(): void
    {
        $command = new CreateTaxRule(
            id: TaxRuleId::generate(),
            tenantId: TenantId::generate(),
            countryCode: 'FR',
            regionCode: null,
            category: 'standard',
            ratePercentage: 0.0,
            name: 'EU B2B Reverse Charge FR',
            isReverseCharge: true,
        );

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (TaxRule $rule): bool {
                return $rule->isReverseCharge()
                    && $rule->rate()->isZero();
            }));

        ($this->handler)($command);
    }

    public function testHandleWithDateRange(): void
    {
        $validFrom = new \DateTimeImmutable('2026-01-01');
        $validTo = new \DateTimeImmutable('2026-12-31');

        $command = new CreateTaxRule(
            id: TaxRuleId::generate(),
            tenantId: TenantId::generate(),
            countryCode: 'DE',
            regionCode: null,
            category: 'standard',
            ratePercentage: 16.0,
            name: 'Temporary Reduced Rate',
            priority: 10,
            validFrom: $validFrom,
            validTo: $validTo,
        );

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (TaxRule $rule) use ($validFrom, $validTo): bool {
                return $rule->priority() === 10
                    && $rule->validFrom() == $validFrom
                    && $rule->validTo() == $validTo;
            }));

        ($this->handler)($command);
    }

    public function testHandleFailsWithInvalidCategory(): void
    {
        $command = new CreateTaxRule(
            id: TaxRuleId::generate(),
            tenantId: TenantId::generate(),
            countryCode: 'DE',
            regionCode: null,
            category: 'invalid_category',
            ratePercentage: 19.0,
            name: 'Invalid',
        );

        $this->expectException(\ValueError::class);

        ($this->handler)($command);
    }
}
