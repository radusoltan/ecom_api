<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\ValueObject;

use App\Privacy\Domain\ValueObject\ConsentPurpose;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsentPurpose::class)]
final class ConsentPurposeTest extends TestCase
{
    #[Test]
    #[TestWith(['marketing'])]
    #[TestWith(['analytics'])]
    #[TestWith(['profiling'])]
    #[TestWith(['necessary'])]
    #[TestWith(['legal'])]
    #[TestWith(['third_party_sharing'])]
    public function fromStringCreatesValidPurpose(string $value): void
    {
        $purpose = ConsentPurpose::fromString($value);
        self::assertSame($value, $purpose->value());
    }

    #[Test]
    public function fromStringWithInvalidPurposeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid consent purpose');

        ConsentPurpose::fromString('invalid_purpose');
    }

    #[Test]
    public function marketingRequiresExplicitConsent(): void
    {
        self::assertTrue(ConsentPurpose::marketing()->requiresExplicitConsent());
        self::assertTrue(ConsentPurpose::analytics()->requiresExplicitConsent());
        self::assertTrue(ConsentPurpose::profiling()->requiresExplicitConsent());
        self::assertTrue(ConsentPurpose::thirdPartySharing()->requiresExplicitConsent());
    }

    #[Test]
    public function necessaryAndLegalDoNotRequireExplicitConsent(): void
    {
        self::assertFalse(ConsentPurpose::necessary()->requiresExplicitConsent());
        self::assertFalse(ConsentPurpose::legal()->requiresExplicitConsent());
    }

    #[Test]
    public function equalsReturnsTrueForSamePurpose(): void
    {
        $purpose1 = ConsentPurpose::marketing();
        $purpose2 = ConsentPurpose::marketing();

        self::assertTrue($purpose1->equals($purpose2));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentPurpose(): void
    {
        self::assertFalse(ConsentPurpose::marketing()->equals(ConsentPurpose::analytics()));
    }

    #[Test]
    public function toStringReturnsValue(): void
    {
        self::assertSame('marketing', (string) ConsentPurpose::marketing());
    }
}
