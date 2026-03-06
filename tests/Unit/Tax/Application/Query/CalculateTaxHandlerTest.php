<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\Query;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\Query\CalculateTax;
use App\Tax\Application\Query\CalculateTaxHandler;
use App\Tax\Domain\Service\TaxCalculationService;
use App\Tax\Domain\ValueObject\TaxJurisdiction;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CalculateTaxHandler.
 */
final class CalculateTaxHandlerTest extends TestCase
{
    private TaxCalculationService $calculationService;
    private CalculateTaxHandler $handler;

    protected function setUp(): void
    {
        $this->calculationService = $this->createMock(TaxCalculationService::class);
        $this->handler = new CalculateTaxHandler($this->calculationService);
    }

    public function testHandleDelegatesCountryOnlyToService(): void
    {
        $tenantId = TenantId::generate();
        $query = new CalculateTax(
            amountInCents: 10000,
            countryCode: 'DE',
            regionCode: null,
            tenantId: $tenantId,
        );

        $expectedResult = [
            'taxAmount' => 1900,
            'taxRate' => 19.0,
            'jurisdiction' => 'DE',
            'taxRuleId' => 'some-id',
            'taxRuleName' => 'German VAT',
        ];

        $this->calculationService
            ->expects($this->once())
            ->method('calculateTax')
            ->with(
                10000,
                $this->callback(function (TaxJurisdiction $j): bool {
                    return 'DE' === $j->getCountryCode() && !$j->hasRegion();
                }),
                $tenantId,
            )
            ->willReturn($expectedResult);

        $result = ($this->handler)($query);

        $this->assertSame($expectedResult, $result);
    }

    public function testHandleDelegatesCountryAndRegionToService(): void
    {
        $tenantId = TenantId::generate();
        $query = new CalculateTax(
            amountInCents: 5000,
            countryCode: 'US',
            regionCode: 'CA',
            tenantId: $tenantId,
        );

        $expectedResult = [
            'taxAmount' => 363,
            'taxRate' => 7.25,
            'jurisdiction' => 'US-CA',
            'taxRuleId' => 'ca-id',
            'taxRuleName' => 'California Sales Tax',
        ];

        $this->calculationService
            ->expects($this->once())
            ->method('calculateTax')
            ->with(
                5000,
                $this->callback(function (TaxJurisdiction $j): bool {
                    return 'US' === $j->getCountryCode()
                        && 'CA' === $j->getRegionCode();
                }),
                $tenantId,
            )
            ->willReturn($expectedResult);

        $result = ($this->handler)($query);

        $this->assertSame($expectedResult, $result);
    }

    public function testHandleReturnsZeroWhenNoRuleFound(): void
    {
        $tenantId = TenantId::generate();
        $query = new CalculateTax(
            amountInCents: 10000,
            countryCode: 'JP',
            regionCode: null,
            tenantId: $tenantId,
        );

        $expectedResult = [
            'taxAmount' => 0,
            'taxRate' => 0.0,
            'jurisdiction' => 'JP',
            'taxRuleId' => null,
            'taxRuleName' => null,
        ];

        $this->calculationService
            ->expects($this->once())
            ->method('calculateTax')
            ->willReturn($expectedResult);

        $result = ($this->handler)($query);

        $this->assertSame(0, $result['taxAmount']);
        $this->assertNull($result['taxRuleId']);
    }
}
