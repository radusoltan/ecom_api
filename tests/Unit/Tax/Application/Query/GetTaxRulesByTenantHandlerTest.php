<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Query\GetTaxRulesByTenant;
use App\Tax\Application\Query\GetTaxRulesByTenantHandler;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetTaxRulesByTenantHandlerTest extends TestCase
{
    public function testItReturnsRulesForTenant(): void
    {
        $tenantId = TenantId::generate();
        $rule = TaxRule::create(TaxRuleId::generate(), $tenantId, TaxJurisdiction::fromCountryCode('FR'), TaxCategory::STANDARD, TaxRate::fromPercentage(20.0), 'French VAT');

        $repo = $this->createStub(TaxRuleRepositoryInterface::class);
        $repo->method('findByTenantId')->willReturn([$rule]);

        $result = (new GetTaxRulesByTenantHandler($repo))(new GetTaxRulesByTenant($tenantId));

        self::assertCount(1, $result);
    }

    public function testItReturnsEmptyWhenNoRules(): void
    {
        $repo = $this->createStub(TaxRuleRepositoryInterface::class);
        $repo->method('findByTenantId')->willReturn([]);

        $result = (new GetTaxRulesByTenantHandler($repo))(new GetTaxRulesByTenant(TenantId::generate()));

        self::assertSame([], $result);
    }
}
