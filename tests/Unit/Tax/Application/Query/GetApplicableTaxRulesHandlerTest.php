<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Query\GetApplicableTaxRules;
use App\Tax\Application\Query\GetApplicableTaxRulesHandler;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetApplicableTaxRulesHandlerTest extends TestCase
{
    public function testItReturnsApplicableRules(): void
    {
        $tenantId = TenantId::generate();
        $rule = TaxRule::create(TaxRuleId::generate(), $tenantId, TaxJurisdiction::fromCountryCode('US'), TaxCategory::STANDARD, TaxRate::fromPercentage(8.5), 'US Sales Tax');

        $repo = $this->createStub(TaxRuleRepositoryInterface::class);
        $repo->method('findApplicableRules')->willReturn([$rule]);

        $result = (new GetApplicableTaxRulesHandler($repo))(new GetApplicableTaxRules($tenantId, 'US', null, 'standard', new \DateTimeImmutable()));

        self::assertCount(1, $result);
        self::assertSame('US Sales Tax', $result[0]->name);
    }

    public function testItPassesRegionWhenProvided(): void
    {
        $tenantId = TenantId::generate();
        $repo = $this->createMock(TaxRuleRepositoryInterface::class);
        $repo->expects($this->once())->method('findApplicableRules')->with(
            $this->equalTo($tenantId),
            $this->callback(fn ($j) => 'CA' === $j->regionCode()),
            $this->equalTo(TaxCategory::STANDARD),
            $this->isInstanceOf(\DateTimeImmutable::class),
        )->willReturn([]);

        (new GetApplicableTaxRulesHandler($repo))(new GetApplicableTaxRules($tenantId, 'US', 'CA', 'standard', new \DateTimeImmutable()));
    }

    public function testItReturnsEmptyWhenNoApplicableRules(): void
    {
        $repo = $this->createStub(TaxRuleRepositoryInterface::class);
        $repo->method('findApplicableRules')->willReturn([]);

        $result = (new GetApplicableTaxRulesHandler($repo))(new GetApplicableTaxRules(TenantId::generate(), 'XX', null, 'standard', new \DateTimeImmutable()));

        self::assertSame([], $result);
    }
}
