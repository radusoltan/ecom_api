<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Domain\Model;

use App\Invoice\Domain\Model\InvoiceId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceId::class)]
final class InvoiceIdTest extends TestCase
{
    #[Test]
    public function itGeneratesValidInvoiceId(): void
    {
        $id = InvoiceId::generate();

        self::assertInstanceOf(InvoiceId::class, $id);
        self::assertNotEmpty($id->toString());
    }

    #[Test]
    public function itCreatesFromValidUuidV4(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id = InvoiceId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
    }

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('InvoiceId cannot be empty');

        InvoiceId::fromString('');
    }

    #[Test]
    public function itThrowsForZeroString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('InvoiceId cannot be empty');

        InvoiceId::fromString('0');
    }

    #[Test]
    public function itThrowsForInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid InvoiceId format');

        InvoiceId::fromString('not-a-valid-uuid');
    }

    #[Test]
    public function itEqualsAnotherIdWithSameValue(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id1 = InvoiceId::fromString($uuid);
        $id2 = InvoiceId::fromString($uuid);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function itDoesNotEqualDifferentId(): void
    {
        $id1 = InvoiceId::generate();
        $id2 = InvoiceId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itImplementsStringableMagicMethod(): void
    {
        $id = InvoiceId::generate();

        self::assertSame($id->toString(), (string) $id);
    }

    #[Test]
    public function itGeneratesValidUuidV4Format(): void
    {
        $id = InvoiceId::generate();
        $value = $id->toString();

        // UUID v4 format: xxxxxxxx-xxxx-4xxx-[89ab]xxx-xxxxxxxxxxxx
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value,
        );
    }
}
