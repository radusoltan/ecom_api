<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application\DTO;

use App\Shared\Domain\ValueObject\TenantId;
use App\Tax\Application\DTO\TaxRuleDTO;
use App\Tax\Domain\Model\TaxCategory;
use App\Tax\Domain\Model\TaxJurisdiction;
use App\Tax\Domain\Model\TaxRate;
use App\Tax\Domain\Model\TaxRule;
use App\Tax\Domain\Model\TaxRuleId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxRuleDTO::class)]
final class TaxRuleDTOTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Direct construction
    // -----------------------------------------------------------------------

    #[Test]
    public function constructionHoldsAllFields(): void
    {
        $dto = new TaxRuleDTO(
            id: 'rule-1',
            tenantId: '00000000-0000-4000-8000-000000000001',
            countryCode: 'DE',
            regionCode: null,
            category: 'standard',
            ratePercentage: 19.0,
            name: 'German Standard VAT',
            description: 'Standard 19% VAT for Germany',
            priority: 10,
            isActive: true,
            validFrom: '2025-01-01T00:00:00+00:00',
            validTo: null,
            isReverseCharge: false,
            createdAt: '2025-01-01T00:00:00+00:00',
            updatedAt: '2025-02-01T00:00:00+00:00',
        );

        self::assertSame('rule-1', $dto->id);
        self::assertSame('00000000-0000-4000-8000-000000000001', $dto->tenantId);
        self::assertSame('DE', $dto->countryCode);
        self::assertNull($dto->regionCode);
        self::assertSame('standard', $dto->category);
        self::assertSame(19.0, $dto->ratePercentage);
        self::assertSame('German Standard VAT', $dto->name);
        self::assertSame('Standard 19% VAT for Germany', $dto->description);
        self::assertSame(10, $dto->priority);
        self::assertTrue($dto->isActive);
        self::assertSame('2025-01-01T00:00:00+00:00', $dto->validFrom);
        self::assertNull($dto->validTo);
        self::assertFalse($dto->isReverseCharge);
        self::assertSame('2025-01-01T00:00:00+00:00', $dto->createdAt);
        self::assertSame('2025-02-01T00:00:00+00:00', $dto->updatedAt);
    }

    #[Test]
    public function constructionWithRegionCode(): void
    {
        $dto = new TaxRuleDTO(
            id: 'rule-2',
            tenantId: '00000000-0000-4000-8000-000000000001',
            countryCode: 'US',
            regionCode: 'CA',
            category: 'standard',
            ratePercentage: 8.25,
            name: 'California Sales Tax',
            description: null,
            priority: 5,
            isActive: true,
            validFrom: '2020-01-01T00:00:00+00:00',
            validTo: null,
            isReverseCharge: false,
            createdAt: '2020-01-01T00:00:00+00:00',
            updatedAt: '2020-01-01T00:00:00+00:00',
        );

        self::assertSame('CA', $dto->regionCode);
        self::assertSame(8.25, $dto->ratePercentage);
        self::assertNull($dto->description);
    }

    // -----------------------------------------------------------------------
    // fromDomainModel
    // -----------------------------------------------------------------------

    #[Test]
    public function fromDomainModelMapsAllFields(): void
    {
        $ruleId = TaxRuleId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $taxRule = TaxRule::create(
            id: $ruleId,
            tenantId: $tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('DE'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(19.0),
            name: 'Standard VAT DE',
            description: 'Germany standard rate',
            priority: 0,
            isActive: true,
        );

        $dto = TaxRuleDTO::fromDomainModel($taxRule);

        self::assertSame($ruleId->toString(), $dto->id);
        self::assertSame($tenantId->toString(), $dto->tenantId);
        self::assertSame('DE', $dto->countryCode);
        self::assertNull($dto->regionCode);
        self::assertSame('standard', $dto->category);
        self::assertSame(19.0, $dto->ratePercentage);
        self::assertSame('Standard VAT DE', $dto->name);
        self::assertSame('Germany standard rate', $dto->description);
        self::assertSame(0, $dto->priority);
        self::assertTrue($dto->isActive);
        self::assertNull($dto->validTo);
        self::assertFalse($dto->isReverseCharge);
        // Timestamps are ISO 8601 format strings
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $dto->validFrom);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $dto->createdAt);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $dto->updatedAt);
    }

    #[Test]
    public function fromDomainModelWithRegionAndValidTo(): void
    {
        $ruleId = TaxRuleId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $validFrom = new \DateTimeImmutable('2020-07-01');
        $validTo = new \DateTimeImmutable('2020-12-31');

        $taxRule = TaxRule::create(
            id: $ruleId,
            tenantId: $tenantId,
            jurisdiction: TaxJurisdiction::fromCountryAndRegion('DE', 'BY'),
            category: TaxCategory::STANDARD,
            rate: TaxRate::fromPercentage(16.0),
            name: 'COVID Relief DE',
            priority: 100,
            validFrom: $validFrom,
            validTo: $validTo,
        );

        $dto = TaxRuleDTO::fromDomainModel($taxRule);

        self::assertSame('DE', $dto->countryCode);
        self::assertSame('BY', $dto->regionCode);
        self::assertSame(16.0, $dto->ratePercentage);
        self::assertSame(100, $dto->priority);
        self::assertNotNull($dto->validTo);
        self::assertStringContainsString('2020-12-31', $dto->validTo);
    }

    #[Test]
    public function fromDomainModelWithInactiveRule(): void
    {
        $ruleId = TaxRuleId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $taxRule = TaxRule::create(
            id: $ruleId,
            tenantId: $tenantId,
            jurisdiction: TaxJurisdiction::fromCountryCode('FR'),
            category: TaxCategory::REDUCED,
            rate: TaxRate::fromPercentage(5.5),
            name: 'French Reduced VAT',
            isActive: false,
        );

        $dto = TaxRuleDTO::fromDomainModel($taxRule);

        self::assertFalse($dto->isActive);
        self::assertSame('FR', $dto->countryCode);
        self::assertSame('reduced', $dto->category);
        self::assertSame(5.5, $dto->ratePercentage);
    }

    // -----------------------------------------------------------------------
    // fromArray
    // -----------------------------------------------------------------------

    #[Test]
    public function fromArrayMapsAllRequiredFields(): void
    {
        $data = [
            'id' => 'arr-rule-1',
            'tenantId' => '00000000-0000-4000-8000-000000000001',
            'countryCode' => 'IT',
            'regionCode' => null,
            'category' => 'standard',
            'ratePercentage' => 22.0,
            'name' => 'Italian VAT',
            'description' => null,
            'priority' => 0,
            'isActive' => true,
            'validFrom' => '2025-01-01T00:00:00+00:00',
            'validTo' => null,
            'isReverseCharge' => false,
            'createdAt' => '2025-01-01T00:00:00+00:00',
            'updatedAt' => '2025-01-01T00:00:00+00:00',
        ];

        $dto = TaxRuleDTO::fromArray($data);

        self::assertSame('arr-rule-1', $dto->id);
        self::assertSame('IT', $dto->countryCode);
        self::assertNull($dto->regionCode);
        self::assertSame(22.0, $dto->ratePercentage);
        self::assertSame('Italian VAT', $dto->name);
        self::assertNull($dto->description);
        self::assertSame(0, $dto->priority);
        self::assertTrue($dto->isActive);
        self::assertNull($dto->validTo);
        self::assertFalse($dto->isReverseCharge);
    }

    #[Test]
    public function fromArrayWithOptionalFields(): void
    {
        $data = [
            'id' => 'arr-rule-2',
            'tenantId' => '00000000-0000-4000-8000-000000000001',
            'countryCode' => 'ES',
            'regionCode' => 'CT',
            'category' => 'reduced',
            'ratePercentage' => 4.0,
            'name' => 'Spanish Super Reduced',
            'priority' => 5,
            'isActive' => false,
            'validFrom' => '2024-01-01T00:00:00+00:00',
            'validTo' => '2024-12-31T23:59:59+00:00',
            'isReverseCharge' => false,
            'createdAt' => '2024-01-01T00:00:00+00:00',
            'updatedAt' => '2024-06-01T00:00:00+00:00',
        ];

        $dto = TaxRuleDTO::fromArray($data);

        self::assertSame('CT', $dto->regionCode);
        self::assertFalse($dto->isActive);
        self::assertSame('2024-12-31T23:59:59+00:00', $dto->validTo);
    }

    // -----------------------------------------------------------------------
    // toArray
    // -----------------------------------------------------------------------

    #[Test]
    public function toArrayReturnsAllKeys(): void
    {
        $dto = new TaxRuleDTO(
            id: 'to-arr-1',
            tenantId: '00000000-0000-4000-8000-000000000001',
            countryCode: 'NL',
            regionCode: null,
            category: 'standard',
            ratePercentage: 21.0,
            name: 'Dutch VAT',
            description: 'Netherlands standard VAT rate',
            priority: 0,
            isActive: true,
            validFrom: '2025-01-01T00:00:00+00:00',
            validTo: null,
            isReverseCharge: false,
            createdAt: '2025-01-01T00:00:00+00:00',
            updatedAt: '2025-01-01T00:00:00+00:00',
        );

        $array = $dto->toArray();

        self::assertSame('to-arr-1', $array['id']);
        self::assertSame('00000000-0000-4000-8000-000000000001', $array['tenantId']);
        self::assertSame('NL', $array['countryCode']);
        self::assertNull($array['regionCode']);
        self::assertSame('standard', $array['category']);
        self::assertSame(21.0, $array['ratePercentage']);
        self::assertSame('Dutch VAT', $array['name']);
        self::assertSame('Netherlands standard VAT rate', $array['description']);
        self::assertSame(0, $array['priority']);
        self::assertTrue($array['isActive']);
        self::assertSame('2025-01-01T00:00:00+00:00', $array['validFrom']);
        self::assertNull($array['validTo']);
        self::assertFalse($array['isReverseCharge']);
        self::assertSame('2025-01-01T00:00:00+00:00', $array['createdAt']);
        self::assertSame('2025-01-01T00:00:00+00:00', $array['updatedAt']);
    }

    #[Test]
    public function toArrayWithAllNullOptionals(): void
    {
        $dto = new TaxRuleDTO(
            id: 'to-arr-2',
            tenantId: '00000000-0000-4000-8000-000000000001',
            countryCode: 'PL',
            regionCode: null,
            category: 'exempt',
            ratePercentage: 0.0,
            name: 'Polish VAT Exempt',
            description: null,
            priority: 0,
            isActive: true,
            validFrom: '2025-01-01T00:00:00+00:00',
            validTo: null,
            isReverseCharge: false,
            createdAt: '2025-01-01T00:00:00+00:00',
            updatedAt: '2025-01-01T00:00:00+00:00',
        );

        $array = $dto->toArray();

        self::assertNull($array['regionCode']);
        self::assertNull($array['description']);
        self::assertNull($array['validTo']);
        self::assertSame(0.0, $array['ratePercentage']);
    }
}
