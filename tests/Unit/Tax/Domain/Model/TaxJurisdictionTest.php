<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\Model;

use App\Tax\Domain\Model\TaxJurisdiction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TaxJurisdiction domain model (ISO 3166-1 + region support).
 */
final class TaxJurisdictionTest extends TestCase
{
    public function testFromCountryCodeCreatesValidJurisdiction(): void
    {
        $jurisdiction = TaxJurisdiction::fromCountryCode('DE');

        $this->assertSame('DE', $jurisdiction->countryCode());
        $this->assertNull($jurisdiction->regionCode());
    }

    public function testFromCountryCodeNormalizesCase(): void
    {
        $jurisdiction = TaxJurisdiction::fromCountryCode('de');

        $this->assertSame('DE', $jurisdiction->countryCode());
    }

    public function testFromCountryCodeFailsWithInvalidCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISO country code');

        TaxJurisdiction::fromCountryCode('DEU');
    }

    public function testFromCountryCodeFailsWithSingleChar(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TaxJurisdiction::fromCountryCode('D');
    }

    public function testFromCountryCodeFailsWithNumeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TaxJurisdiction::fromCountryCode('12');
    }

    public function testFromCountryAndRegionCreatesRegionalJurisdiction(): void
    {
        $jurisdiction = TaxJurisdiction::fromCountryAndRegion('US', 'CA');

        $this->assertSame('US', $jurisdiction->countryCode());
        $this->assertSame('CA', $jurisdiction->regionCode());
    }

    public function testFromCountryAndRegionNormalizesCase(): void
    {
        $jurisdiction = TaxJurisdiction::fromCountryAndRegion('us', 'ca');

        $this->assertSame('US', $jurisdiction->countryCode());
        $this->assertSame('CA', $jurisdiction->regionCode());
    }

    #[DataProvider('euCountriesProvider')]
    public function testIsEuReturnsTrueForEuCountries(string $countryCode): void
    {
        $jurisdiction = TaxJurisdiction::fromCountryCode($countryCode);

        $this->assertTrue($jurisdiction->isEu());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function euCountriesProvider(): iterable
    {
        $euCountries = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];

        foreach ($euCountries as $code) {
            yield $code => [$code];
        }
    }

    public function testIsEuReturnsFalseForNonEuCountries(): void
    {
        $this->assertFalse(TaxJurisdiction::fromCountryCode('US')->isEu());
        $this->assertFalse(TaxJurisdiction::fromCountryCode('JP')->isEu());
        $this->assertFalse(TaxJurisdiction::fromCountryCode('CN')->isEu());
        $this->assertFalse(TaxJurisdiction::fromCountryCode('GB')->isEu());
    }

    public function testIsUsReturnsTrueForUs(): void
    {
        $this->assertTrue(TaxJurisdiction::fromCountryCode('US')->isUs());
    }

    public function testIsUsReturnsFalseForNonUs(): void
    {
        $this->assertFalse(TaxJurisdiction::fromCountryCode('DE')->isUs());
    }

    public function testEqualsForIdenticalJurisdictions(): void
    {
        $a = TaxJurisdiction::fromCountryCode('DE');
        $b = TaxJurisdiction::fromCountryCode('DE');

        $this->assertTrue($a->equals($b));
    }

    public function testEqualsForDifferentJurisdictions(): void
    {
        $a = TaxJurisdiction::fromCountryCode('DE');
        $b = TaxJurisdiction::fromCountryCode('FR');

        $this->assertFalse($a->equals($b));
    }

    public function testEqualsWithRegionConsidersRegion(): void
    {
        $usca = TaxJurisdiction::fromCountryAndRegion('US', 'CA');
        $usny = TaxJurisdiction::fromCountryAndRegion('US', 'NY');
        $usca2 = TaxJurisdiction::fromCountryAndRegion('US', 'CA');

        $this->assertFalse($usca->equals($usny));
        $this->assertTrue($usca->equals($usca2));
    }

    public function testEqualsCountryWithAndWithoutRegionDiffer(): void
    {
        $us = TaxJurisdiction::fromCountryCode('US');
        $usca = TaxJurisdiction::fromCountryAndRegion('US', 'CA');

        $this->assertFalse($us->equals($usca));
    }

    public function testToStringForCountryOnly(): void
    {
        $this->assertSame('DE', TaxJurisdiction::fromCountryCode('DE')->toString());
    }

    public function testToStringWithRegion(): void
    {
        $this->assertSame('US-CA', TaxJurisdiction::fromCountryAndRegion('US', 'CA')->toString());
    }
}
