<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Query\GetAllTaxRules;
use App\Tax\Application\Query\GetAllTaxRulesHandler;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetAllTaxRulesHandlerTest extends TestCase
{
    public function testItReturnsAllTaxRulesAsDtos(): void
    {
        $tenantId = TenantId::generate();
        $rule = TaxRule::create(TaxRuleId::generate(), $tenantId, TaxJurisdiction::fromCountryCode('US'), TaxCategory::STANDARD, TaxRate::fromPercentage(10.0), 'US Standard Tax');

        $repo = $this->createStub(TaxRuleRepositoryInterface::class);
        $repo->method('findByTenantId')->willReturn([$rule]);

        $result = (new GetAllTaxRulesHandler($repo))(new GetAllTaxRules($tenantId));

        self::assertCount(1, $result);
        self::assertSame('US Standard Tax', $result[0]->name);
    }

    public function testItReturnsEmptyWhenNoRules(): void
    {
        $repo = $this->createStub(TaxRuleRepositoryInterface::class);
        $repo->method('findByTenantId')->willReturn([]);

        $result = (new GetAllTaxRulesHandler($repo))(new GetAllTaxRules(TenantId::generate()));

        self::assertSame([], $result);
    }
}
