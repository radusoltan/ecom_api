<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\ValueObject;

use App\Payment\Domain\ValueObject\TransactionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionId::class)]
final class TransactionIdTest extends TestCase
{
    // ---------------------------------------------------------------
    // fromString() — happy paths
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesFromStripeTransactionId(): void
    {
        $txId = TransactionId::fromString('ch_1J4Z5Z2eZvKYlo2C8Z5Z5Z5Z');

        self::assertSame('ch_1J4Z5Z2eZvKYlo2C8Z5Z5Z5Z', $txId->toString());
    }

    #[Test]
    public function itCreatesFromPaypalTransactionId(): void
    {
        $txId = TransactionId::fromString('9HP05742RH8748327');

        self::assertSame('9HP05742RH8748327', $txId->toString());
    }

    #[Test]
    public function itCreatesFromStripePaymentIntentId(): void
    {
        $txId = TransactionId::fromString('pi_3N4EXAMPLE001');

        self::assertSame('pi_3N4EXAMPLE001', $txId->toString());
    }

    #[Test]
    public function itAcceptsMaxLength255Characters(): void
    {
        $value = str_repeat('a', 255);
        $txId = TransactionId::fromString($value);

        self::assertSame($value, $txId->toString());
    }

    // ---------------------------------------------------------------
    // fromString() — validation errors
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/TransactionId cannot be empty/');

        TransactionId::fromString('');
    }

    #[Test]
    public function itThrowsForWhitespaceOnlyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/TransactionId cannot be empty/');

        TransactionId::fromString('   ');
    }

    #[Test]
    public function itThrowsForStringExceeding255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/TransactionId too long/');

        TransactionId::fromString(str_repeat('x', 256));
    }

    // ---------------------------------------------------------------
    // toString() / __toString()
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsStringRepresentation(): void
    {
        $value = 'ch_stripe_abc';
        $txId = TransactionId::fromString($value);

        self::assertSame($value, $txId->toString());
        self::assertSame($value, (string) $txId);
    }

    // ---------------------------------------------------------------
    // equals()
    // ---------------------------------------------------------------

    #[Test]
    public function itEqualsSameValue(): void
    {
        $tx1 = TransactionId::fromString('ch_same_id');
        $tx2 = TransactionId::fromString('ch_same_id');

        self::assertTrue($tx1->equals($tx2));
    }

    #[Test]
    public function itDoesNotEqualDifferentValue(): void
    {
        $tx1 = TransactionId::fromString('ch_one');
        $tx2 = TransactionId::fromString('ch_two');

        self::assertFalse($tx1->equals($tx2));
    }

    #[Test]
    public function itIsCaseSensitiveForEquality(): void
    {
        $tx1 = TransactionId::fromString('ch_ABC');
        $tx2 = TransactionId::fromString('ch_abc');

        self::assertFalse($tx1->equals($tx2));
    }

    #[Test]
    public function itEqualsSelf(): void
    {
        $tx = TransactionId::fromString('ch_self');

        self::assertTrue($tx->equals($tx));
    }

    // ---------------------------------------------------------------
    // Stringable interface
    // ---------------------------------------------------------------

    #[Test]
    public function itImplementsStringable(): void
    {
        $tx = TransactionId::fromString('ch_test');

        self::assertInstanceOf(\Stringable::class, $tx);
    }
}
