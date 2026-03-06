<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\ValueObject;

use App\Payment\Domain\ValueObject\PaymentId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentId::class)]
final class PaymentIdTest extends TestCase
{
    // ---------------------------------------------------------------
    // generate()
    // ---------------------------------------------------------------

    #[Test]
    public function itGeneratesAValidUuid(): void
    {
        $id = PaymentId::generate();

        self::assertNotEmpty($id->toString());
        // Symfony UUID v7 is still a valid UUID — just check it is a well-formed UUID
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $id->toString()
        );
    }

    #[Test]
    public function itGeneratesUniqueIds(): void
    {
        $id1 = PaymentId::generate();
        $id2 = PaymentId::generate();

        self::assertFalse($id1->equals($id2));
    }

    // ---------------------------------------------------------------
    // fromString()
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesFromValidUuidV7String(): void
    {
        // UUID v7 starts with version nibble 7
        $value = '01959f2a-92d7-7e44-b123-0123456789ab';
        $id = PaymentId::fromString($value);

        self::assertSame($value, $id->toString());
    }

    #[Test]
    public function itCreatesFromValidUuidV4String(): void
    {
        $value = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $id = PaymentId::fromString($value);

        self::assertSame($value, $id->toString());
    }

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid Payment ID/');

        PaymentId::fromString('');
    }

    #[Test]
    public function itThrowsForNonUuidString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid Payment ID/');

        PaymentId::fromString('not-a-valid-uuid');
    }

    #[Test]
    public function itThrowsForMalformedUuidString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentId::fromString('zzzzzzzz-zzzz-zzzz-zzzz-zzzzzzzzzzzz');
    }

    // ---------------------------------------------------------------
    // toString() / __toString()
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsStringRepresentation(): void
    {
        $value = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $id = PaymentId::fromString($value);

        self::assertSame($value, $id->toString());
        self::assertSame($value, (string) $id);
    }

    // ---------------------------------------------------------------
    // equals()
    // ---------------------------------------------------------------

    #[Test]
    public function itEqualsSameValue(): void
    {
        $value = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $id1 = PaymentId::fromString($value);
        $id2 = PaymentId::fromString($value);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function itDoesNotEqualDifferentValue(): void
    {
        $id1 = PaymentId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
        $id2 = PaymentId::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itEqualsSelf(): void
    {
        $id = PaymentId::generate();

        self::assertTrue($id->equals($id));
    }
}
