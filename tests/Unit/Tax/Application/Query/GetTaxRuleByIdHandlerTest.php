<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Query\GetTaxRuleById;
use App\Tax\Application\Query\GetTaxRuleByIdHandler;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use App\Tax\Domain\Repository\TaxRuleRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetTaxRuleByIdHandlerTest extends TestCase
{
    public function testItReturnsTaxRuleDto(): void
    {
        $ruleId = TaxRuleId::generate();
        $rule = TaxRule::create($ruleId, TenantId::generate(), TaxJurisdiction::fromCountryCode('DE'), TaxCategory::STANDARD, TaxRate::fromPercentage(19.0), 'German VAT');

        $repo = $this->createStub(TaxRuleRepositoryInterface::class);
        $repo->method('findById')->willReturn($rule);

        $result = (new GetTaxRuleByIdHandler($repo))(new GetTaxRuleById($ruleId, TenantId::generate()));

        self::assertNotNull($result);
        self::assertSame('German VAT', $result->name);
    }

    public function testItReturnsNullWhenNotFound(): void
    {
        $repo = $this->createStub(TaxRuleRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $result = (new GetTaxRuleByIdHandler($repo))(new GetTaxRuleById(TaxRuleId::generate(), TenantId::generate()));

        self::assertNull($result);
    }
}
