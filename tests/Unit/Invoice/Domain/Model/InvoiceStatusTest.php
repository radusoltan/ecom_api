<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Domain\Model;

use App\Invoice\Domain\Model\InvoiceStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceStatus::class)]
final class InvoiceStatusTest extends TestCase
{
    #[Test]
    public function draftCanTransitionToIssuedOnly(): void
    {
        self::assertTrue(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::ISSUED));
        self::assertFalse(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::PAID));
        self::assertFalse(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::CANCELLED));
        self::assertFalse(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::CREDITED));
    }

    #[Test]
    public function issuedCanTransitionToPaidCancelledOrCredited(): void
    {
        self::assertTrue(InvoiceStatus::ISSUED->canTransitionTo(InvoiceStatus::PAID));
        self::assertTrue(InvoiceStatus::ISSUED->canTransitionTo(InvoiceStatus::CANCELLED));
        self::assertTrue(InvoiceStatus::ISSUED->canTransitionTo(InvoiceStatus::CREDITED));
        self::assertFalse(InvoiceStatus::ISSUED->canTransitionTo(InvoiceStatus::DRAFT));
    }

    #[Test]
    public function paidCannotTransitionToAnyStatus(): void
    {
        self::assertFalse(InvoiceStatus::PAID->canTransitionTo(InvoiceStatus::DRAFT));
        self::assertFalse(InvoiceStatus::PAID->canTransitionTo(InvoiceStatus::ISSUED));
        self::assertFalse(InvoiceStatus::PAID->canTransitionTo(InvoiceStatus::CANCELLED));
        self::assertFalse(InvoiceStatus::PAID->canTransitionTo(InvoiceStatus::CREDITED));
    }

    #[Test]
    public function cancelledCannotTransitionToAnyStatus(): void
    {
        self::assertFalse(InvoiceStatus::CANCELLED->canTransitionTo(InvoiceStatus::DRAFT));
        self::assertFalse(InvoiceStatus::CANCELLED->canTransitionTo(InvoiceStatus::ISSUED));
        self::assertFalse(InvoiceStatus::CANCELLED->canTransitionTo(InvoiceStatus::PAID));
    }

    #[Test]
    public function creditedCannotTransitionToAnyStatus(): void
    {
        self::assertFalse(InvoiceStatus::CREDITED->canTransitionTo(InvoiceStatus::DRAFT));
        self::assertFalse(InvoiceStatus::CREDITED->canTransitionTo(InvoiceStatus::ISSUED));
        self::assertFalse(InvoiceStatus::CREDITED->canTransitionTo(InvoiceStatus::PAID));
    }

    #[Test]
    public function isDraftReturnsTrueOnlyForDraft(): void
    {
        self::assertTrue(InvoiceStatus::DRAFT->isDraft());
        self::assertFalse(InvoiceStatus::ISSUED->isDraft());
        self::assertFalse(InvoiceStatus::PAID->isDraft());
    }

    #[Test]
    public function isIssuedReturnsTrueOnlyForIssued(): void
    {
        self::assertTrue(InvoiceStatus::ISSUED->isIssued());
        self::assertFalse(InvoiceStatus::DRAFT->isIssued());
        self::assertFalse(InvoiceStatus::PAID->isIssued());
    }

    #[Test]
    public function isFinalReturnsTrueForTerminalStates(): void
    {
        self::assertTrue(InvoiceStatus::PAID->isFinal());
        self::assertTrue(InvoiceStatus::CANCELLED->isFinal());
        self::assertTrue(InvoiceStatus::CREDITED->isFinal());
        self::assertFalse(InvoiceStatus::DRAFT->isFinal());
        self::assertFalse(InvoiceStatus::ISSUED->isFinal());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        self::assertSame('Draft', InvoiceStatus::DRAFT->label());
        self::assertSame('Issued', InvoiceStatus::ISSUED->label());
        self::assertSame('Paid', InvoiceStatus::PAID->label());
        self::assertSame('Cancelled', InvoiceStatus::CANCELLED->label());
        self::assertSame('Credited', InvoiceStatus::CREDITED->label());
    }
}
