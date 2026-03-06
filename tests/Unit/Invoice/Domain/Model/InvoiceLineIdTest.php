<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Domain\Model;

use App\Invoice\Domain\Model\InvoiceLineId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceLineId::class)]
final class InvoiceLineIdTest extends TestCase
{
    #[Test]
    public function itGeneratesValidInvoiceLineId(): void
    {
        $id = InvoiceLineId::generate();

        self::assertInstanceOf(InvoiceLineId::class, $id);
        self::assertNotEmpty($id->toString());
    }

    #[Test]
    public function itCreatesFromValidUuidV4(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id = InvoiceLineId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
    }

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('InvoiceLineId cannot be empty');

        InvoiceLineId::fromString('');
    }

    #[Test]
    public function itThrowsForZeroString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('InvoiceLineId cannot be empty');

        InvoiceLineId::fromString('0');
    }

    #[Test]
    public function itThrowsForInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid InvoiceLineId format');

        InvoiceLineId::fromString('not-a-valid-uuid');
    }

    #[Test]
    public function itEqualsAnotherIdWithSameValue(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id1 = InvoiceLineId::fromString($uuid);
        $id2 = InvoiceLineId::fromString($uuid);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function itDoesNotEqualDifferentId(): void
    {
        $id1 = InvoiceLineId::generate();
        $id2 = InvoiceLineId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function itImplementsStringableMagicMethod(): void
    {
        $id = InvoiceLineId::generate();

        self::assertSame($id->toString(), (string) $id);
    }

    #[Test]
    public function itGeneratesValidUuidV4Format(): void
    {
        $id = InvoiceLineId::generate();
        $value = $id->toString();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value,
        );
    }
}
