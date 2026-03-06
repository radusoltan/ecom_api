<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\ValueObject;

use App\Payment\Domain\ValueObject\RefundId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundId::class)]
final class RefundIdTest extends TestCase
{
    // ---------------------------------------------------------------
    // A known, fixed UUID v4 for deterministic tests
    // ---------------------------------------------------------------

    private const VALID_UUID_V4 = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    // ---------------------------------------------------------------
    // generate()
    // ---------------------------------------------------------------

    #[Test]
    public function itGeneratesAValidUuidV4(): void
    {
        $id = RefundId::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->toString()
        );
    }

    #[Test]
    public function itGeneratesUniqueIds(): void
    {
        $id1 = RefundId::generate();
        $id2 = RefundId::generate();

        self::assertFalse($id1->equals($id2));
    }

    // ---------------------------------------------------------------
    // fromString()
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesFromValidUuidV4(): void
    {
        $id = RefundId::fromString(self::VALID_UUID_V4);

        self::assertSame(self::VALID_UUID_V4, $id->toString());
    }

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/RefundId cannot be empty/');

        RefundId::fromString('');
    }

    #[Test]
    public function itThrowsForZeroString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/RefundId cannot be empty/');

        RefundId::fromString('0');
    }

    #[Test]
    public function itThrowsForNonUuidString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid RefundId format/');

        RefundId::fromString('not-a-uuid-at-all');
    }

    #[Test]
    public function itThrowsForUuidV1(): void
    {
        // UUID v1 — version nibble is 1, not 4
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid RefundId format/');

        RefundId::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
    }

    // ---------------------------------------------------------------
    // toString() / __toString()
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsStringRepresentation(): void
    {
        $id = RefundId::fromString(self::VALID_UUID_V4);

        self::assertSame(self::VALID_UUID_V4, $id->toString());
        self::assertSame(self::VALID_UUID_V4, (string) $id);
    }

    // ---------------------------------------------------------------
    // equals()
    // ---------------------------------------------------------------

    #[Test]
    public function itEqualsSameValue(): void
    {
        $id1 = RefundId::fromString(self::VALID_UUID_V4);
        $id2 = RefundId::fromString(self::VALID_UUID_V4);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function itDoesNotEqualDifferentValue(): void
    {
        $id1 = RefundId::fromString(self::VALID_UUID_V4);
        $id2 = RefundId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itEqualsSelf(): void
    {
        $id = RefundId::generate();

        self::assertTrue($id->equals($id));
    }

    // ---------------------------------------------------------------
    // Stringable interface
    // ---------------------------------------------------------------

    #[Test]
    public function itImplementsStringable(): void
    {
        $id = RefundId::fromString(self::VALID_UUID_V4);

        self::assertInstanceOf(\Stringable::class, $id);
    }
}
