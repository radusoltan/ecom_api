<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Domain\ValueObject;

use App\Review\Domain\ValueObject\ReviewStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviewStatus::class)]
final class ReviewStatusTest extends TestCase
{
    #[Test]
    public function pendingIsPending(): void
    {
        self::assertTrue(ReviewStatus::PENDING->isPending());
        self::assertFalse(ReviewStatus::PENDING->isApproved());
        self::assertFalse(ReviewStatus::PENDING->isRejected());
    }

    #[Test]
    public function approvedIsApproved(): void
    {
        self::assertFalse(ReviewStatus::APPROVED->isPending());
        self::assertTrue(ReviewStatus::APPROVED->isApproved());
        self::assertFalse(ReviewStatus::APPROVED->isRejected());
    }

    #[Test]
    public function rejectedIsRejected(): void
    {
        self::assertFalse(ReviewStatus::REJECTED->isPending());
        self::assertFalse(ReviewStatus::REJECTED->isApproved());
        self::assertTrue(ReviewStatus::REJECTED->isRejected());
    }

    #[Test]
    public function valuesMatchExpectedStrings(): void
    {
        self::assertSame('pending', ReviewStatus::PENDING->value);
        self::assertSame('approved', ReviewStatus::APPROVED->value);
        self::assertSame('rejected', ReviewStatus::REJECTED->value);
    }
}
