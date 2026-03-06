<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\ValueObject;

use App\Payment\Domain\ValueObject\PaymentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Extended coverage for statuses/transitions not covered in PaymentStatusTest:
 * processing, succeeded, partially_refunded, isFinal(), __toString(), and all
 * remaining transition matrix entries.
 */
#[CoversClass(PaymentStatus::class)]
final class PaymentStatusExtendedTest extends TestCase
{
    // ---------------------------------------------------------------
    // Factory methods not covered elsewhere
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesProcessingStatus(): void
    {
        $status = PaymentStatus::processing();

        self::assertSame('processing', $status->value());
        self::assertTrue($status->isProcessing());
        self::assertFalse($status->isPending());
        self::assertFalse($status->isAuthorized());
        self::assertFalse($status->isCaptured());
        self::assertFalse($status->isSucceeded());
        self::assertFalse($status->isRefunded());
        self::assertFalse($status->isPartiallyRefunded());
        self::assertFalse($status->isFailed());
        self::assertFalse($status->isCancelled());
    }

    #[Test]
    public function itCreatesSucceededStatus(): void
    {
        $status = PaymentStatus::succeeded();

        self::assertSame('succeeded', $status->value());
        self::assertTrue($status->isSucceeded());
        self::assertFalse($status->isCaptured());
        self::assertFalse($status->isPending());
    }

    #[Test]
    public function itCreatesPartiallyRefundedStatus(): void
    {
        $status = PaymentStatus::partiallyRefunded();

        self::assertSame('partially_refunded', $status->value());
        self::assertTrue($status->isPartiallyRefunded());
        self::assertFalse($status->isRefunded());
    }

    // ---------------------------------------------------------------
    // fromString for extended statuses
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesProcessingFromString(): void
    {
        $status = PaymentStatus::fromString('processing');

        self::assertTrue($status->isProcessing());
    }

    #[Test]
    public function itCreatesSucceededFromString(): void
    {
        $status = PaymentStatus::fromString('succeeded');

        self::assertTrue($status->isSucceeded());
    }

    #[Test]
    public function itCreatesPartiallyRefundedFromString(): void
    {
        $status = PaymentStatus::fromString('partially_refunded');

        self::assertTrue($status->isPartiallyRefunded());
    }

    #[Test]
    public function itCreatesProcessingFromUppercaseString(): void
    {
        $status = PaymentStatus::fromString('PROCESSING');

        self::assertTrue($status->isProcessing());
    }

    // ---------------------------------------------------------------
    // isFinal()
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{PaymentStatus, bool}>
     */
    public static function finalStatusProvider(): array
    {
        return [
            'pending is not final' => [PaymentStatus::pending(), false],
            'processing is not final' => [PaymentStatus::processing(), false],
            'authorized is not final' => [PaymentStatus::authorized(), false],
            'captured is not final' => [PaymentStatus::captured(), false],
            'succeeded is not final' => [PaymentStatus::succeeded(), false],
            'partially_refunded is not final' => [PaymentStatus::partiallyRefunded(), false],
            'refunded is final' => [PaymentStatus::refunded(), true],
            'failed is final' => [PaymentStatus::failed(), true],
            'cancelled is final' => [PaymentStatus::cancelled(), true],
        ];
    }

    #[Test]
    #[DataProvider('finalStatusProvider')]
    public function itDeterminesIfStatusIsFinal(PaymentStatus $status, bool $expectedFinal): void
    {
        self::assertSame($expectedFinal, $status->isFinal());
    }

    // ---------------------------------------------------------------
    // __toString
    // ---------------------------------------------------------------

    #[Test]
    public function itCastsProcessingToString(): void
    {
        self::assertSame('processing', (string) PaymentStatus::processing());
    }

    #[Test]
    public function itCastsSucceededToString(): void
    {
        self::assertSame('succeeded', (string) PaymentStatus::succeeded());
    }

    #[Test]
    public function itCastsPartiallyRefundedToString(): void
    {
        self::assertSame('partially_refunded', (string) PaymentStatus::partiallyRefunded());
    }

    // ---------------------------------------------------------------
    // Transition matrix: processing
    // ---------------------------------------------------------------

    #[Test]
    public function processingCanTransitionToSucceeded(): void
    {
        $processing = PaymentStatus::processing();

        self::assertTrue($processing->canTransitionTo(PaymentStatus::succeeded()));
    }

    #[Test]
    public function processingCanTransitionToAuthorized(): void
    {
        $processing = PaymentStatus::processing();

        self::assertTrue($processing->canTransitionTo(PaymentStatus::authorized()));
    }

    #[Test]
    public function processingCanTransitionToCaptured(): void
    {
        $processing = PaymentStatus::processing();

        self::assertTrue($processing->canTransitionTo(PaymentStatus::captured()));
    }

    #[Test]
    public function processingCanTransitionToFailed(): void
    {
        $processing = PaymentStatus::processing();

        self::assertTrue($processing->canTransitionTo(PaymentStatus::failed()));
    }

    #[Test]
    public function processingCanTransitionToCancelled(): void
    {
        $processing = PaymentStatus::processing();

        self::assertTrue($processing->canTransitionTo(PaymentStatus::cancelled()));
    }

    #[Test]
    public function processingCannotTransitionToPending(): void
    {
        $processing = PaymentStatus::processing();

        self::assertFalse($processing->canTransitionTo(PaymentStatus::pending()));
    }

    #[Test]
    public function processingCannotTransitionToRefunded(): void
    {
        $processing = PaymentStatus::processing();

        self::assertFalse($processing->canTransitionTo(PaymentStatus::refunded()));
    }

    // ---------------------------------------------------------------
    // Transition matrix: authorized -> succeeded
    // ---------------------------------------------------------------

    #[Test]
    public function authorizedCanTransitionToSucceeded(): void
    {
        $authorized = PaymentStatus::authorized();

        self::assertTrue($authorized->canTransitionTo(PaymentStatus::succeeded()));
    }

    // ---------------------------------------------------------------
    // Transition matrix: succeeded
    // ---------------------------------------------------------------

    #[Test]
    public function succeededCanTransitionToRefunded(): void
    {
        $succeeded = PaymentStatus::succeeded();

        self::assertTrue($succeeded->canTransitionTo(PaymentStatus::refunded()));
    }

    #[Test]
    public function succeededCanTransitionToPartiallyRefunded(): void
    {
        $succeeded = PaymentStatus::succeeded();

        self::assertTrue($succeeded->canTransitionTo(PaymentStatus::partiallyRefunded()));
    }

    #[Test]
    public function succeededCannotTransitionToPending(): void
    {
        $succeeded = PaymentStatus::succeeded();

        self::assertFalse($succeeded->canTransitionTo(PaymentStatus::pending()));
    }

    #[Test]
    public function succeededCannotTransitionToFailed(): void
    {
        $succeeded = PaymentStatus::succeeded();

        self::assertFalse($succeeded->canTransitionTo(PaymentStatus::failed()));
    }

    #[Test]
    public function succeededCannotTransitionToCancelled(): void
    {
        $succeeded = PaymentStatus::succeeded();

        self::assertFalse($succeeded->canTransitionTo(PaymentStatus::cancelled()));
    }

    // ---------------------------------------------------------------
    // Transition matrix: captured -> partially_refunded
    // ---------------------------------------------------------------

    #[Test]
    public function capturedCanTransitionToPartiallyRefunded(): void
    {
        $captured = PaymentStatus::captured();

        self::assertTrue($captured->canTransitionTo(PaymentStatus::partiallyRefunded()));
    }

    // ---------------------------------------------------------------
    // Transition matrix: partially_refunded
    // ---------------------------------------------------------------

    #[Test]
    public function partiallyRefundedCanTransitionToRefunded(): void
    {
        $partiallyRefunded = PaymentStatus::partiallyRefunded();

        self::assertTrue($partiallyRefunded->canTransitionTo(PaymentStatus::refunded()));
    }

    #[Test]
    public function partiallyRefundedCannotTransitionToPending(): void
    {
        $partiallyRefunded = PaymentStatus::partiallyRefunded();

        self::assertFalse($partiallyRefunded->canTransitionTo(PaymentStatus::pending()));
    }

    #[Test]
    public function partiallyRefundedCannotTransitionToFailed(): void
    {
        $partiallyRefunded = PaymentStatus::partiallyRefunded();

        self::assertFalse($partiallyRefunded->canTransitionTo(PaymentStatus::failed()));
    }

    #[Test]
    public function partiallyRefundedCannotTransitionToCancelled(): void
    {
        $partiallyRefunded = PaymentStatus::partiallyRefunded();

        self::assertFalse($partiallyRefunded->canTransitionTo(PaymentStatus::cancelled()));
    }

    #[Test]
    public function partiallyRefundedCannotTransitionToCaptured(): void
    {
        $partiallyRefunded = PaymentStatus::partiallyRefunded();

        self::assertFalse($partiallyRefunded->canTransitionTo(PaymentStatus::captured()));
    }

    // ---------------------------------------------------------------
    // pending -> processing
    // ---------------------------------------------------------------

    #[Test]
    public function pendingCanTransitionToProcessing(): void
    {
        $pending = PaymentStatus::pending();

        self::assertTrue($pending->canTransitionTo(PaymentStatus::processing()));
    }

    // ---------------------------------------------------------------
    // equals() for new statuses
    // ---------------------------------------------------------------

    #[Test]
    public function processingEqualsProcessing(): void
    {
        $a = PaymentStatus::processing();
        $b = PaymentStatus::processing();

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function succeededDoesNotEqualCaptured(): void
    {
        $succeeded = PaymentStatus::succeeded();
        $captured = PaymentStatus::captured();

        self::assertFalse($succeeded->equals($captured));
    }

    #[Test]
    public function partiallyRefundedDoesNotEqualRefunded(): void
    {
        $partial = PaymentStatus::partiallyRefunded();
        $refunded = PaymentStatus::refunded();

        self::assertFalse($partial->equals($refunded));
    }

    // ---------------------------------------------------------------
    // Invalid fromString
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function invalidStatusProvider(): array
    {
        return [
            'unknown' => ['unknown'],
            'partial' => ['partial'],
            'success' => ['success'],
            'empty string' => [''],
            'complete' => ['complete'],
        ];
    }

    #[Test]
    #[DataProvider('invalidStatusProvider')]
    public function itThrowsForInvalidStatus(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid payment status/');

        PaymentStatus::fromString($value);
    }
}
