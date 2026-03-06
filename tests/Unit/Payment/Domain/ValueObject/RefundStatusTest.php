<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\ValueObject;

use App\Payment\Domain\ValueObject\RefundStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundStatus::class)]
final class RefundStatusTest extends TestCase
{
    // ---------------------------------------------------------------
    // Named factories
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesPendingStatus(): void
    {
        $status = RefundStatus::pending();

        self::assertSame('pending', $status->value());
        self::assertTrue($status->isPending());
        self::assertFalse($status->isSucceeded());
        self::assertFalse($status->isFailed());
    }

    #[Test]
    public function itCreatesSucceededStatus(): void
    {
        $status = RefundStatus::succeeded();

        self::assertSame('succeeded', $status->value());
        self::assertTrue($status->isSucceeded());
        self::assertFalse($status->isPending());
        self::assertFalse($status->isFailed());
    }

    #[Test]
    public function itCreatesFailedStatus(): void
    {
        $status = RefundStatus::failed();

        self::assertSame('failed', $status->value());
        self::assertTrue($status->isFailed());
        self::assertFalse($status->isPending());
        self::assertFalse($status->isSucceeded());
    }

    // ---------------------------------------------------------------
    // fromString()
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesFromLowercaseString(): void
    {
        self::assertTrue(RefundStatus::fromString('pending')->isPending());
        self::assertTrue(RefundStatus::fromString('succeeded')->isSucceeded());
        self::assertTrue(RefundStatus::fromString('failed')->isFailed());
    }

    #[Test]
    public function itNormalisesToLowercase(): void
    {
        $status = RefundStatus::fromString('PENDING');

        self::assertSame('pending', $status->value());
    }

    #[Test]
    public function itThrowsForUnknownStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid refund status/');

        RefundStatus::fromString('unknown');
    }

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RefundStatus::fromString('');
    }

    // ---------------------------------------------------------------
    // isFinal()
    // ---------------------------------------------------------------

    #[Test]
    public function pendingStatusIsNotFinal(): void
    {
        self::assertFalse(RefundStatus::pending()->isFinal());
    }

    #[Test]
    public function succeededStatusIsFinal(): void
    {
        self::assertTrue(RefundStatus::succeeded()->isFinal());
    }

    #[Test]
    public function failedStatusIsFinal(): void
    {
        self::assertTrue(RefundStatus::failed()->isFinal());
    }

    // ---------------------------------------------------------------
    // canTransitionTo()
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{RefundStatus, RefundStatus, bool}>
     */
    public static function transitionProvider(): array
    {
        return [
            'pending -> succeeded (allowed)' => [RefundStatus::pending(), RefundStatus::succeeded(), true],
            'pending -> failed (allowed)' => [RefundStatus::pending(), RefundStatus::failed(), false],   // wait — check source
            'succeeded -> failed (disallowed)' => [RefundStatus::succeeded(), RefundStatus::failed(), false],
            'succeeded -> pending (disallowed)' => [RefundStatus::succeeded(), RefundStatus::pending(), false],
            'failed -> pending (disallowed)' => [RefundStatus::failed(), RefundStatus::pending(), false],
            'failed -> succeeded (disallowed)' => [RefundStatus::failed(), RefundStatus::succeeded(), false],
        ];
    }

    #[Test]
    public function itAllowsTransitionFromPendingToSucceeded(): void
    {
        self::assertTrue(RefundStatus::pending()->canTransitionTo(RefundStatus::succeeded()));
    }

    #[Test]
    public function itAllowsTransitionFromPendingToFailed(): void
    {
        self::assertTrue(RefundStatus::pending()->canTransitionTo(RefundStatus::failed()));
    }

    #[Test]
    public function itDisallowsTransitionFromSucceededToAnyState(): void
    {
        $succeeded = RefundStatus::succeeded();

        self::assertFalse($succeeded->canTransitionTo(RefundStatus::pending()));
        self::assertFalse($succeeded->canTransitionTo(RefundStatus::failed()));
        self::assertFalse($succeeded->canTransitionTo(RefundStatus::succeeded()));
    }

    #[Test]
    public function itDisallowsTransitionFromFailedToAnyState(): void
    {
        $failed = RefundStatus::failed();

        self::assertFalse($failed->canTransitionTo(RefundStatus::pending()));
        self::assertFalse($failed->canTransitionTo(RefundStatus::succeeded()));
        self::assertFalse($failed->canTransitionTo(RefundStatus::failed()));
    }

    // ---------------------------------------------------------------
    // equals()
    // ---------------------------------------------------------------

    #[Test]
    public function itEqualsSameStatus(): void
    {
        $s1 = RefundStatus::pending();
        $s2 = RefundStatus::pending();

        self::assertTrue($s1->equals($s2));
    }

    #[Test]
    public function itDoesNotEqualDifferentStatus(): void
    {
        self::assertFalse(RefundStatus::pending()->equals(RefundStatus::succeeded()));
    }

    // ---------------------------------------------------------------
    // __toString()
    // ---------------------------------------------------------------

    #[Test]
    public function itCastsToString(): void
    {
        self::assertSame('pending', (string) RefundStatus::pending());
        self::assertSame('succeeded', (string) RefundStatus::succeeded());
        self::assertSame('failed', (string) RefundStatus::failed());
    }
}
