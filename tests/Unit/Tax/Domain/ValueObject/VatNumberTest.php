<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\ValueObject;

use App\Tax\Domain\ValueObject\VatNumber;
use PHPUnit\Framework\TestCase;

/**
 * Tests for VatNumber Value Object (Domain/ValueObject).
 */
final class VatNumberTest extends TestCase
{
    public function testFromStringValidEuCountries(): void
    {
        // Sample valid VAT numbers for major EU countries
        $valid = [
            'DE123456789' => 'DE',
            'FR12345678901' => 'FR',
            'NL123456789B01' => 'NL',
            'ES12345678A' => 'ES',  // Spanish format: letter + 7 digits + letter
        ];

        foreach ($valid as $vatString => $expectedCountry) {
            $vat = VatNumber::fromString($vatString);
            $this->assertSame($expectedCountry, $vat->getCountryCode(), "Country for $vatString");
        }
    }

    public function testFromPartsCreatesVatNumber(): void
    {
        $vat = VatNumber::fromParts('DE', '123456789');

        $this->assertSame('DE', $vat->getCountryCode());
        $this->assertSame('123456789', $vat->getNumber());
        $this->assertSame('DE123456789', $vat->getFull());
    }

    public function testFromStringRemovesSpaces(): void
    {
        $vat = VatNumber::fromString('DE 123 456 789');

        $this->assertSame('DE123456789', $vat->getFull());
    }

    public function testFromStringRemovesDotsAndDashes(): void
    {
        $vat = VatNumber::fromString('DE-123.456.789');

        $this->assertSame('DE123456789', $vat->getFull());
    }

    public function testFromStringNormalizesToUpperCase(): void
    {
        $vat = VatNumber::fromString('de123456789');

        $this->assertSame('DE', $vat->getCountryCode());
    }

    public function testFromStringFailsWithNonEuCountry(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid VAT country code');

        VatNumber::fromString('US123456789');
    }

    public function testFromStringFailsTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('too short');

        VatNumber::fromString('DE');
    }

    public function testFromPartsFailsWithNumberTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between 2 and 15');

        VatNumber::fromParts('DE', 'A');
    }

    public function testFromPartsFailsWithNumberTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between 2 and 15');

        VatNumber::fromParts('DE', '1234567890123456');
    }

    public function testFromPartsFailsWithNonAlphanumeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('alphanumeric');

        VatNumber::fromParts('DE', '123!456');
    }

    public function testEquals(): void
    {
        $a = VatNumber::fromParts('DE', '123456789');
        $b = VatNumber::fromParts('DE', '123456789');
        $c = VatNumber::fromParts('DE', '987654321');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testToString(): void
    {
        $vat = VatNumber::fromParts('DE', '123456789');

        $this->assertSame('DE123456789', (string) $vat);
    }
}
