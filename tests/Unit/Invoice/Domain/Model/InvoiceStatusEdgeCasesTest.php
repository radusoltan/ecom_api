<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Domain\Model;

use App\Invoice\Domain\Model\InvoiceStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceStatus::class)]
final class InvoiceStatusEdgeCasesTest extends TestCase
{
    // -------------------------------------------------------
    // isPaid
    // -------------------------------------------------------

    #[Test]
    public function itReturnsTrueForIsPaidOnlyWhenPaid(): void
    {
        self::assertTrue(InvoiceStatus::PAID->isPaid());
        self::assertFalse(InvoiceStatus::DRAFT->isPaid());
        self::assertFalse(InvoiceStatus::ISSUED->isPaid());
        self::assertFalse(InvoiceStatus::CANCELLED->isPaid());
        self::assertFalse(InvoiceStatus::CREDITED->isPaid());
    }

    // -------------------------------------------------------
    // isCancelled
    // -------------------------------------------------------

    #[Test]
    public function itReturnsTrueForIsCancelledOnlyWhenCancelled(): void
    {
        self::assertTrue(InvoiceStatus::CANCELLED->isCancelled());
        self::assertFalse(InvoiceStatus::DRAFT->isCancelled());
        self::assertFalse(InvoiceStatus::ISSUED->isCancelled());
        self::assertFalse(InvoiceStatus::PAID->isCancelled());
        self::assertFalse(InvoiceStatus::CREDITED->isCancelled());
    }

    // -------------------------------------------------------
    // isCredited
    // -------------------------------------------------------

    #[Test]
    public function itReturnsTrueForIsCreditedOnlyWhenCredited(): void
    {
        self::assertTrue(InvoiceStatus::CREDITED->isCredited());
        self::assertFalse(InvoiceStatus::DRAFT->isCredited());
        self::assertFalse(InvoiceStatus::ISSUED->isCredited());
        self::assertFalse(InvoiceStatus::PAID->isCredited());
        self::assertFalse(InvoiceStatus::CANCELLED->isCredited());
    }

    // -------------------------------------------------------
    // canTransitionTo - missing edge: DRAFT cannot go to DRAFT
    // -------------------------------------------------------

    #[Test]
    public function itCannotTransitionFromDraftToDraft(): void
    {
        self::assertFalse(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::DRAFT));
    }

    #[Test]
    public function itCannotTransitionFromCancelledToPaid(): void
    {
        self::assertFalse(InvoiceStatus::CANCELLED->canTransitionTo(InvoiceStatus::PAID));
    }

    #[Test]
    public function itCannotTransitionFromCreditedToPaid(): void
    {
        self::assertFalse(InvoiceStatus::CREDITED->canTransitionTo(InvoiceStatus::PAID));
    }
}
