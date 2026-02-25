<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\ValueObject;

use App\Privacy\Domain\ValueObject\RequestType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestType::class)]
final class RequestTypeTest extends TestCase
{
    #[Test]
    #[TestWith(['access'])]
    #[TestWith(['rectification'])]
    #[TestWith(['erasure'])]
    #[TestWith(['portability'])]
    #[TestWith(['restriction'])]
    #[TestWith(['objection'])]
    public function fromStringCreatesValidType(string $value): void
    {
        $type = RequestType::fromString($value);
        self::assertSame($value, $type->value());
    }

    #[Test]
    public function fromStringWithInvalidTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request type');

        RequestType::fromString('invalid');
    }

    #[Test]
    public function onlyErasureIsDestructive(): void
    {
        self::assertTrue(RequestType::erasure()->isDestructive());
        self::assertFalse(RequestType::access()->isDestructive());
        self::assertFalse(RequestType::portability()->isDestructive());
        self::assertFalse(RequestType::rectification()->isDestructive());
        self::assertFalse(RequestType::restriction()->isDestructive());
        self::assertFalse(RequestType::objection()->isDestructive());
    }

    #[Test]
    public function requiresManualReviewForSpecificTypes(): void
    {
        self::assertTrue(RequestType::rectification()->requiresManualReview());
        self::assertTrue(RequestType::restriction()->requiresManualReview());
        self::assertTrue(RequestType::objection()->requiresManualReview());
        self::assertFalse(RequestType::access()->requiresManualReview());
        self::assertFalse(RequestType::erasure()->requiresManualReview());
        self::assertFalse(RequestType::portability()->requiresManualReview());
    }

    #[Test]
    public function equalsReturnsTrueForSameType(): void
    {
        self::assertTrue(RequestType::access()->equals(RequestType::access()));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentType(): void
    {
        self::assertFalse(RequestType::access()->equals(RequestType::erasure()));
    }

    #[Test]
    public function toStringReturnsValue(): void
    {
        self::assertSame('erasure', (string) RequestType::erasure());
    }
}
