<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Domain\ValueObject;

use App\Shipping\Domain\ValueObject\CarrierCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CarrierCodeTest extends TestCase
{
    #[DataProvider('validCarrierProvider')]
    public function testFromStringValid(string $code): void
    {
        $carrier = CarrierCode::fromString($code);
        $this->assertSame($code, $carrier->toString());
    }

    /** @return iterable<string, array{string}> */
    public static function validCarrierProvider(): iterable
    {
        yield 'ups' => ['ups'];
        yield 'fedex' => ['fedex'];
        yield 'dhl' => ['dhl'];
        yield 'usps' => ['usps'];
        yield 'dpd' => ['dpd'];
        yield 'gls' => ['gls'];
        yield 'mock' => ['mock'];
    }

    public function testRejectsInvalidCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid carrier code');
        CarrierCode::fromString('invalid_carrier');
    }

    public function testFactoryMethods(): void
    {
        $this->assertSame('ups', CarrierCode::ups()->toString());
        $this->assertSame('fedex', CarrierCode::fedex()->toString());
        $this->assertSame('dhl', CarrierCode::dhl()->toString());
        $this->assertSame('usps', CarrierCode::usps()->toString());
        $this->assertSame('dpd', CarrierCode::dpd()->toString());
        $this->assertSame('gls', CarrierCode::gls()->toString());
        $this->assertSame('mock', CarrierCode::mock()->toString());
    }

    public function testEquals(): void
    {
        $this->assertTrue(CarrierCode::ups()->equals(CarrierCode::ups()));
        $this->assertFalse(CarrierCode::ups()->equals(CarrierCode::fedex()));
    }

    public function testToString(): void
    {
        $this->assertSame('dhl', (string) CarrierCode::dhl());
    }
}
