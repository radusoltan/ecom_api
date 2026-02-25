<?php

declare(strict_types=1);

namespace App\Tests\Unit\Privacy\Domain\ValueObject;

use App\Privacy\Domain\ValueObject\RequestStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestStatus::class)]
final class RequestStatusTest extends TestCase
{
    #[Test]
    #[TestWith(['pending'])]
    #[TestWith(['under_review'])]
    #[TestWith(['completed'])]
    #[TestWith(['rejected'])]
    public function fromStringCreatesValidStatus(string $value): void
    {
        $status = RequestStatus::fromString($value);
        self::assertSame($value, $status->value());
    }

    #[Test]
    public function fromStringWithInvalidStatusThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid request status');

        RequestStatus::fromString('cancelled');
    }

    #[Test]
    public function pendingCanTransitionToUnderReviewOrRejected(): void
    {
        $pending = RequestStatus::pending();

        self::assertTrue($pending->canTransitionTo(RequestStatus::underReview()));
        self::assertTrue($pending->canTransitionTo(RequestStatus::rejected()));
        self::assertFalse($pending->canTransitionTo(RequestStatus::completed()));
        self::assertFalse($pending->canTransitionTo(RequestStatus::pending()));
    }

    #[Test]
    public function underReviewCanTransitionToCompletedOrRejected(): void
    {
        $underReview = RequestStatus::underReview();

        self::assertTrue($underReview->canTransitionTo(RequestStatus::completed()));
        self::assertTrue($underReview->canTransitionTo(RequestStatus::rejected()));
        self::assertFalse($underReview->canTransitionTo(RequestStatus::pending()));
        self::assertFalse($underReview->canTransitionTo(RequestStatus::underReview()));
    }

    #[Test]
    public function completedCannotTransitionToAnyStatus(): void
    {
        $completed = RequestStatus::completed();

        self::assertFalse($completed->canTransitionTo(RequestStatus::pending()));
        self::assertFalse($completed->canTransitionTo(RequestStatus::underReview()));
        self::assertFalse($completed->canTransitionTo(RequestStatus::completed()));
        self::assertFalse($completed->canTransitionTo(RequestStatus::rejected()));
    }

    #[Test]
    public function rejectedCannotTransitionToAnyStatus(): void
    {
        $rejected = RequestStatus::rejected();

        self::assertFalse($rejected->canTransitionTo(RequestStatus::pending()));
        self::assertFalse($rejected->canTransitionTo(RequestStatus::underReview()));
        self::assertFalse($rejected->canTransitionTo(RequestStatus::completed()));
        self::assertFalse($rejected->canTransitionTo(RequestStatus::rejected()));
    }

    #[Test]
    public function isFinalReturnsTrueForCompletedAndRejected(): void
    {
        self::assertTrue(RequestStatus::completed()->isFinal());
        self::assertTrue(RequestStatus::rejected()->isFinal());
        self::assertFalse(RequestStatus::pending()->isFinal());
        self::assertFalse(RequestStatus::underReview()->isFinal());
    }

    #[Test]
    public function equalsReturnsTrueForSameStatus(): void
    {
        self::assertTrue(RequestStatus::pending()->equals(RequestStatus::pending()));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentStatus(): void
    {
        self::assertFalse(RequestStatus::pending()->equals(RequestStatus::completed()));
    }

    #[Test]
    public function toStringReturnsValue(): void
    {
        self::assertSame('under_review', (string) RequestStatus::underReview());
    }
}
