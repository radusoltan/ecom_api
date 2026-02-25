<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\Model;

use App\Tax\Domain\Model\VatNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for VatNumber value object (Domain/Model).
 *
 * Validates EU VAT number format validation per member state.
 */
final class VatNumberTest extends TestCase
{
    #[DataProvider('validVatNumbersProvider')]
    public function testFromStringAcceptsValidFormats(string $input, string $expectedCountry, string $expectedNormalized): void
    {
        $vatNumber = VatNumber::fromString($input);

        $this->assertSame($expectedCountry, $vatNumber->countryCode());
        $this->assertSame($expectedNormalized, $vatNumber->value());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function validVatNumbersProvider(): iterable
    {
        yield 'Austria' => ['ATU12345678', 'AT', 'ATU12345678'];
        yield 'Belgium' => ['BE0123456789', 'BE', 'BE0123456789'];
        yield 'Bulgaria 9 digits' => ['BG123456789', 'BG', 'BG123456789'];
        yield 'Bulgaria 10 digits' => ['BG1234567890', 'BG', 'BG1234567890'];
        yield 'Croatia' => ['HR12345678901', 'HR', 'HR12345678901'];
        yield 'Cyprus' => ['CY12345678A', 'CY', 'CY12345678A'];
        yield 'Czech Republic' => ['CZ12345678', 'CZ', 'CZ12345678'];
        yield 'Denmark' => ['DK12345678', 'DK', 'DK12345678'];
        yield 'Estonia' => ['EE123456789', 'EE', 'EE123456789'];
        yield 'Finland' => ['FI12345678', 'FI', 'FI12345678'];
        yield 'France' => ['FRXX123456789', 'FR', 'FRXX123456789'];
        yield 'Germany' => ['DE123456789', 'DE', 'DE123456789'];
        yield 'Greece (EL prefix)' => ['EL123456789', 'GR', 'EL123456789'];
        yield 'Hungary' => ['HU12345678', 'HU', 'HU12345678'];
        yield 'Ireland' => ['IE1234567A', 'IE', 'IE1234567A'];
        yield 'Italy' => ['IT12345678901', 'IT', 'IT12345678901'];
        yield 'Latvia' => ['LV12345678901', 'LV', 'LV12345678901'];
        yield 'Lithuania 9 digits' => ['LT123456789', 'LT', 'LT123456789'];
        yield 'Lithuania 12 digits' => ['LT123456789012', 'LT', 'LT123456789012'];
        yield 'Luxembourg' => ['LU12345678', 'LU', 'LU12345678'];
        yield 'Malta' => ['MT12345678', 'MT', 'MT12345678'];
        yield 'Netherlands' => ['NL123456789B01', 'NL', 'NL123456789B01'];
        yield 'Poland' => ['PL1234567890', 'PL', 'PL1234567890'];
        yield 'Portugal' => ['PT123456789', 'PT', 'PT123456789'];
        yield 'Romania' => ['RO12345678', 'RO', 'RO12345678'];
        yield 'Slovakia' => ['SK1234567890', 'SK', 'SK1234567890'];
        yield 'Slovenia' => ['SI12345678', 'SI', 'SI12345678'];
        yield 'Spain' => ['ESA12345678', 'ES', 'ESA12345678'];
        yield 'Sweden' => ['SE123456789012', 'SE', 'SE123456789012'];
    }

    public function testFromStringNormalizesSpacesDotsAndDashes(): void
    {
        $vatNumber = VatNumber::fromString('DE 123 456 789');
        $this->assertSame('DE123456789', $vatNumber->value());

        $vatNumber2 = VatNumber::fromString('DE-123.456.789');
        $this->assertSame('DE123456789', $vatNumber2->value());
    }

    public function testFromStringNormalizesCase(): void
    {
        $vatNumber = VatNumber::fromString('de123456789');
        $this->assertSame('DE123456789', $vatNumber->value());
    }

    public function testGreeceMappingElToGr(): void
    {
        $vatNumber = VatNumber::fromString('EL123456789');
        $this->assertSame('GR', $vatNumber->countryCode());
        // Value keeps EL prefix as that's the normalized form
        $this->assertSame('EL123456789', $vatNumber->value());
    }

    public function testFromStringFailsWithUnknownCountry(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown VAT number country code');

        VatNumber::fromString('XX123456789');
    }

    public function testFromStringFailsWithInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid VAT number format');

        // DE requires exactly 9 digits
        VatNumber::fromString('DE12345');
    }

    public function testJurisdictionConversion(): void
    {
        $vatNumber = VatNumber::fromString('DE123456789');
        $jurisdiction = $vatNumber->jurisdiction();

        $this->assertSame('DE', $jurisdiction->countryCode());
    }

    public function testEqualsForIdenticalNumbers(): void
    {
        $a = VatNumber::fromString('DE123456789');
        $b = VatNumber::fromString('DE123456789');

        $this->assertTrue($a->equals($b));
    }

    public function testEqualsForDifferentNumbers(): void
    {
        $a = VatNumber::fromString('DE123456789');
        $b = VatNumber::fromString('DE987654321');

        $this->assertFalse($a->equals($b));
    }

    public function testToStringReturnsNormalizedValue(): void
    {
        $vatNumber = VatNumber::fromString('DE123456789');
        $this->assertSame('DE123456789', $vatNumber->toString());
    }
}
