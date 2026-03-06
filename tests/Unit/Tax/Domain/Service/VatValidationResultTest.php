<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain\Service;

use App\Tax\Domain\Service\VatValidationResult;
use App\Tax\Domain\ValueObject\VatNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VatValidationResult::class)]
final class VatValidationResultTest extends TestCase
{
    private function vatNumber(): VatNumber
    {
        return VatNumber::fromString('DE123456789');
    }

    #[Test]
    public function itCreatesValidResult(): void
    {
        $result = VatValidationResult::valid(
            $this->vatNumber(),
            'Acme GmbH',
            'Berlin, Germany',
            'REQ-123',
        );

        self::assertTrue($result->isValid());
        self::assertTrue($result->hasBusinessDetails());
        self::assertFalse($result->hasError());
        self::assertSame('Acme GmbH', $result->businessName);
        self::assertSame('Berlin, Germany', $result->businessAddress);
        self::assertSame('REQ-123', $result->requestId);
        self::assertNull($result->errorMessage);
    }

    #[Test]
    public function itCreatesInvalidResult(): void
    {
        $result = VatValidationResult::invalid(
            $this->vatNumber(),
            'VAT number not found in VIES',
            'REQ-456',
        );

        self::assertFalse($result->isValid());
        self::assertFalse($result->hasBusinessDetails());
        self::assertTrue($result->hasError());
        self::assertSame('VAT number not found in VIES', $result->errorMessage);
        self::assertNull($result->businessName);
    }

    #[Test]
    public function itCreatesServiceUnavailableResult(): void
    {
        $result = VatValidationResult::serviceUnavailable(
            $this->vatNumber(),
            'VIES service is down',
        );

        self::assertFalse($result->isValid());
        self::assertTrue($result->hasError());
        self::assertNull($result->requestId);
        self::assertSame('VIES service is down', $result->errorMessage);
    }

    #[Test]
    public function itChecksFreshness(): void
    {
        $result = VatValidationResult::valid($this->vatNumber(), 'Test', null);

        // Just created - should be fresh
        self::assertTrue($result->isFresh(86400));
        self::assertGreaterThanOrEqual(0, $result->getAge());
    }

    #[Test]
    public function itValidWithNullBusinessDetails(): void
    {
        $result = VatValidationResult::valid($this->vatNumber(), null, null);

        self::assertTrue($result->isValid());
        self::assertFalse($result->hasBusinessDetails());
    }
}
