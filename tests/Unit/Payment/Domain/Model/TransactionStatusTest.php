<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Model;

use App\Payment\Domain\Model\TransactionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionStatus::class)]
final class TransactionStatusTest extends TestCase
{
    #[Test]
    public function successStatus(): void
    {
        $status = TransactionStatus::SUCCESS;

        self::assertSame('success', $status->value);
        self::assertTrue($status->isSuccessful());
        self::assertFalse($status->isFailed());
        self::assertFalse($status->isPending());
        self::assertTrue($status->isFinal());
    }

    #[Test]
    public function failedStatus(): void
    {
        $status = TransactionStatus::FAILED;

        self::assertSame('failed', $status->value);
        self::assertFalse($status->isSuccessful());
        self::assertTrue($status->isFailed());
        self::assertFalse($status->isPending());
        self::assertTrue($status->isFinal());
    }

    #[Test]
    public function pendingStatus(): void
    {
        $status = TransactionStatus::PENDING;

        self::assertSame('pending', $status->value);
        self::assertFalse($status->isSuccessful());
        self::assertFalse($status->isFailed());
        self::assertTrue($status->isPending());
        self::assertFalse($status->isFinal());
    }
}
