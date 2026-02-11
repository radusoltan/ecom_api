<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Domain\ValueObject;

use App\Customer\Domain\ValueObject\ExportStatus;
use PHPUnit\Framework\TestCase;

final class ExportStatusTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = ExportStatus::cases();

        self::assertCount(5, $cases);
        self::assertContains(ExportStatus::PENDING, $cases);
        self::assertContains(ExportStatus::PROCESSING, $cases);
        self::assertContains(ExportStatus::READY, $cases);
        self::assertContains(ExportStatus::EXPIRED, $cases);
        self::assertContains(ExportStatus::FAILED, $cases);
    }

    public function testLabel(): void
    {
        self::assertEquals('Pending', ExportStatus::PENDING->label());
        self::assertEquals('Processing', ExportStatus::PROCESSING->label());
        self::assertEquals('Ready', ExportStatus::READY->label());
        self::assertEquals('Expired', ExportStatus::EXPIRED->label());
        self::assertEquals('Failed', ExportStatus::FAILED->label());
    }

    public function testCanTransitionFromPendingToProcessing(): void
    {
        self::assertTrue(ExportStatus::PENDING->canTransitionTo(ExportStatus::PROCESSING));
    }

    public function testCanTransitionFromPendingToFailed(): void
    {
        self::assertTrue(ExportStatus::PENDING->canTransitionTo(ExportStatus::FAILED));
    }

    public function testCannotTransitionFromPendingToReady(): void
    {
        self::assertFalse(ExportStatus::PENDING->canTransitionTo(ExportStatus::READY));
    }

    public function testCannotTransitionFromPendingToExpired(): void
    {
        self::assertFalse(ExportStatus::PENDING->canTransitionTo(ExportStatus::EXPIRED));
    }

    public function testCanTransitionFromProcessingToReady(): void
    {
        self::assertTrue(ExportStatus::PROCESSING->canTransitionTo(ExportStatus::READY));
    }

    public function testCanTransitionFromProcessingToFailed(): void
    {
        self::assertTrue(ExportStatus::PROCESSING->canTransitionTo(ExportStatus::FAILED));
    }

    public function testCannotTransitionFromProcessingToPending(): void
    {
        self::assertFalse(ExportStatus::PROCESSING->canTransitionTo(ExportStatus::PENDING));
    }

    public function testCanTransitionFromReadyToExpired(): void
    {
        self::assertTrue(ExportStatus::READY->canTransitionTo(ExportStatus::EXPIRED));
    }

    public function testCannotTransitionFromReadyToProcessing(): void
    {
        self::assertFalse(ExportStatus::READY->canTransitionTo(ExportStatus::PROCESSING));
    }

    public function testCannotTransitionFromExpiredToAnyState(): void
    {
        self::assertFalse(ExportStatus::EXPIRED->canTransitionTo(ExportStatus::PENDING));
        self::assertFalse(ExportStatus::EXPIRED->canTransitionTo(ExportStatus::PROCESSING));
        self::assertFalse(ExportStatus::EXPIRED->canTransitionTo(ExportStatus::READY));
        self::assertFalse(ExportStatus::EXPIRED->canTransitionTo(ExportStatus::FAILED));
    }

    public function testCannotTransitionFromFailedToAnyState(): void
    {
        self::assertFalse(ExportStatus::FAILED->canTransitionTo(ExportStatus::PENDING));
        self::assertFalse(ExportStatus::FAILED->canTransitionTo(ExportStatus::PROCESSING));
        self::assertFalse(ExportStatus::FAILED->canTransitionTo(ExportStatus::READY));
        self::assertFalse(ExportStatus::FAILED->canTransitionTo(ExportStatus::EXPIRED));
    }

    public function testIsTerminal(): void
    {
        self::assertFalse(ExportStatus::PENDING->isTerminal());
        self::assertFalse(ExportStatus::PROCESSING->isTerminal());
        self::assertFalse(ExportStatus::READY->isTerminal());
        self::assertTrue(ExportStatus::EXPIRED->isTerminal());
        self::assertTrue(ExportStatus::FAILED->isTerminal());
    }

    public function testIsDownloadable(): void
    {
        self::assertFalse(ExportStatus::PENDING->isDownloadable());
        self::assertFalse(ExportStatus::PROCESSING->isDownloadable());
        self::assertTrue(ExportStatus::READY->isDownloadable());
        self::assertFalse(ExportStatus::EXPIRED->isDownloadable());
        self::assertFalse(ExportStatus::FAILED->isDownloadable());
    }

    public function testValue(): void
    {
        self::assertEquals('pending', ExportStatus::PENDING->value);
        self::assertEquals('processing', ExportStatus::PROCESSING->value);
        self::assertEquals('ready', ExportStatus::READY->value);
        self::assertEquals('expired', ExportStatus::EXPIRED->value);
        self::assertEquals('failed', ExportStatus::FAILED->value);
    }
}
