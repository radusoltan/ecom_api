<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\ValueObject;

use App\Tax\Domain\ValueObject\TaxJurisdiction;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TaxJurisdiction Value Object (Domain/ValueObject).
 */
final class TaxJurisdictionTest extends TestCase
{
    public function testFromCountryCreatesJurisdiction(): void
    {
        $jurisdiction = TaxJurisdiction::fromCountry('DE');

        $this->assertSame('DE', $jurisdiction->getCountryCode());
        $this->assertNull($jurisdiction->getRegionCode());
        $this->assertFalse($jurisdiction->hasRegion());
    }

    public function testFromCountryNormalizesCase(): void
    {
        $jurisdiction = TaxJurisdiction::fromCountry('de');

        $this->assertSame('DE', $jurisdiction->getCountryCode());
    }

    public function testFromCountryFailsWithInvalidLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('2 characters');

        TaxJurisdiction::fromCountry('DEU');
    }

    public function testFromCountryAndRegion(): void
    {
        $jurisdiction = TaxJurisdiction::fromCountryAndRegion('US', 'CA');

        $this->assertSame('US', $jurisdiction->getCountryCode());
        $this->assertSame('CA', $jurisdiction->getRegionCode());
        $this->assertTrue($jurisdiction->hasRegion());
    }

    public function testFromCountryAndRegionFailsWithInvalidRegionLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Region code must be 2-3');

        TaxJurisdiction::fromCountryAndRegion('US', 'CALI');
    }

    public function testMatchesAtCountryLevel(): void
    {
        $de = TaxJurisdiction::fromCountry('DE');
        $deBy = TaxJurisdiction::fromCountryAndRegion('DE', 'BY');

        // Country-level matches regional if either has no region
        $this->assertTrue($de->matches($deBy));
        $this->assertTrue($deBy->matches($de));
    }

    public function testMatchesExactWithRegion(): void
    {
        $usCa = TaxJurisdiction::fromCountryAndRegion('US', 'CA');
        $usNy = TaxJurisdiction::fromCountryAndRegion('US', 'NY');
        $usCa2 = TaxJurisdiction::fromCountryAndRegion('US', 'CA');

        $this->assertFalse($usCa->matches($usNy));
        $this->assertTrue($usCa->matches($usCa2));
    }

    public function testMatchesFailsForDifferentCountries(): void
    {
        $de = TaxJurisdiction::fromCountry('DE');
        $fr = TaxJurisdiction::fromCountry('FR');

        $this->assertFalse($de->matches($fr));
    }

    public function testEqualsRequiresExactMatch(): void
    {
        $de = TaxJurisdiction::fromCountry('DE');
        $deBy = TaxJurisdiction::fromCountryAndRegion('DE', 'BY');

        // equals is strict - different from matches
        $this->assertFalse($de->equals($deBy));
    }

    public function testEqualsForIdentical(): void
    {
        $a = TaxJurisdiction::fromCountryAndRegion('US', 'CA');
        $b = TaxJurisdiction::fromCountryAndRegion('US', 'CA');

        $this->assertTrue($a->equals($b));
    }

    public function testToStringCountryOnly(): void
    {
        $this->assertSame('DE', TaxJurisdiction::fromCountry('DE')->toString());
        $this->assertSame('DE', (string) TaxJurisdiction::fromCountry('DE'));
    }

    public function testToStringWithRegion(): void
    {
        $this->assertSame('US-CA', TaxJurisdiction::fromCountryAndRegion('US', 'CA')->toString());
    }
}
